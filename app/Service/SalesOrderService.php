<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\User;
use App\Repository\CustomerRepositoryInterface;
use App\Repository\Database;
use App\Repository\ProductRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Repository\StockRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;
use RuntimeException;
use Throwable;

/**
 * Domain Service for Sales Order lifecycle, Segregation of Duties approval,
 * and Atomic Goods Issue with Optimistic Locking concurrency protection (SO-01, ARCH-02).
 */
class SalesOrderService
{
    public function __construct(
        private Database $database,
        private SalesOrderRepositoryInterface $soRepository,
        private StockRepositoryInterface $stockRepository,
        private StockLedgerRepositoryInterface $stockLedgerRepository,
        private CustomerRepositoryInterface $customerRepository,
        private WarehouseRepositoryInterface $warehouseRepository,
        private ProductRepositoryInterface $productRepository
    ) {
    }

    public function getSalesOrderById(int $id): ?SalesOrder
    {
        return $this->soRepository->findById($id);
    }

    /**
     * @return SalesOrder[]
     */
    public function getAllSalesOrders(?string $status = null, ?int $warehouseId = null): array
    {
        return $this->soRepository->findAll($status, $warehouseId, null, null, 'DESC', 1, 1000);
    }

    /**
     * @return array{items: SalesOrder[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function getPaginatedSalesOrders(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $total = $this->soRepository->countAll($status, $warehouseId, $search, $createdBy);
        $items = $this->soRepository->findAll($status, $warehouseId, $search, $createdBy, $sortOrder, $page, $perPage);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, $totalPages),
        ];
    }

    /**
     * Create a new Sales Order with initial Draft status.
     *
     * @param array{
     *     customer_id?: int|string,
     *     warehouse_id?: int|string,
     *     order_date?: string,
     *     items?: array<int, array{product_id?: int|string, quantity?: int|string, unit_price?: float|string}>
     * } $data
     * @return array{success: bool, message: string, so?: SalesOrder, errors?: array<string, string>}
     */
    public function createSalesOrder(array $data, int $userId): array
    {
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $warehouseId = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : 0;
        $orderDate = !empty($data['order_date']) ? trim((string) $data['order_date']) : date('Y-m-d');
        $rawItems = $data['items'] ?? [];

        $errors = [];

        $customer = $this->customerRepository->findById($customerId);
        if ($customer === null || !$customer->isActive()) {
            $errors['customer_id'] = 'Pelanggan tidak valid atau berstatus nonaktif.';
        }

        $warehouse = $this->warehouseRepository->findById($warehouseId);
        if ($warehouse === null || !$warehouse->isActive()) {
            $errors['warehouse_id'] = 'Gudang asal tidak valid atau berstatus nonaktif.';
        }

        if (empty($rawItems) || !is_array($rawItems)) {
            $errors['items'] = 'Sales Order harus memiliki minimal 1 item produk.';
        }

        $items = [];
        foreach ($rawItems as $idx => $rawItem) {
            $prodId = isset($rawItem['product_id']) ? (int) $rawItem['product_id'] : 0;
            $qty = isset($rawItem['quantity']) ? (int) $rawItem['quantity'] : 0;

            if ($prodId <= 0) {
                $errors["items_{$idx}_product"] = "Pilih produk yang valid untuk baris ke-" . ($idx + 1) . ".";
                continue;
            }

            $product = $this->productRepository->findById($prodId);
            if ($product === null || !$product->isActive()) {
                $errors["items_{$idx}_product"] = "Produk pada baris ke-" . ($idx + 1) . " tidak ditemukan atau nonaktif.";
                continue;
            }

            if ($qty <= 0) {
                $errors["items_{$idx}_qty"] = "Jumlah pesanan untuk '{$product->getName()}' harus lebih besar dari 0.";
            }

            $unitPrice = isset($rawItem['unit_price']) && $rawItem['unit_price'] !== ''
                ? (float) $rawItem['unit_price']
                : $product->getSellingPrice();

            if ($unitPrice < 0) {
                $errors["items_{$idx}_price"] = "Harga jual untuk '{$product->getName()}' tidak boleh negatif.";
            }

            $items[] = new SalesOrderItem(
                id: null,
                salesOrderId: null,
                productId: $prodId,
                productSku: $product->getSku(),
                productName: $product->getName(),
                productUnit: $product->getUnit(),
                quantityOrdered: $qty,
                quantityFulfilled: 0,
                unitPrice: $unitPrice
            );
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada data Sales Order.',
                'errors'  => $errors,
            ];
        }

        $soNumber = $this->soRepository instanceof \App\Repository\MySQLSalesOrderRepository
            ? $this->soRepository->generateNextSoNumber()
            : 'SO-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));

        $order = new SalesOrder(
            id: null,
            soNumber: $soNumber,
            customerId: $customerId,
            customerName: null,
            warehouseId: $warehouseId,
            warehouseName: null,
            status: SalesOrder::STATUS_DRAFT,
            createdBy: $userId,
            createdByName: null,
            approvedBy: null,
            approvedByName: null,
            orderDate: $orderDate,
            items: $items
        );

        $created = $this->soRepository->create($order, $items);

        return [
            'success' => true,
            'message' => "Sales Order #{$soNumber} berhasil dibuat dengan status Draft.",
            'so'      => $created,
        ];
    }

    /**
     * Transition status Draft -> PendingApproval.
     *
     * @return array{success: bool, message: string}
     */
    public function submitForApproval(int $soId, int $userId): array
    {
        $so = $this->soRepository->findById($soId);
        if ($so === null) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }

        if (!$so->canSubmitForApproval()) {
            return [
                'success' => false,
                'message' => "Sales Order tidak dapat diajukan untuk persetujuan karena statusnya saat ini '{$so->getStatus()}'.",
            ];
        }

        $this->soRepository->updateStatus($soId, SalesOrder::STATUS_PENDING_APPROVAL);

        return [
            'success' => true,
            'message' => "Sales Order #{$so->getSoNumber()} berhasil diajukan untuk persetujuan (Status: PendingApproval).",
        ];
    }

    /**
     * Transition status PendingApproval -> Approved.
     * STRICT SEGREGATION OF DUTIES: ONLY Admin can approve. Sales staff is strictly forbidden.
     *
     * @return array{success: bool, message: string}
     */
    public function approveSalesOrder(int $soId, int $adminUserId, string $userRole): array
    {
        if ($userRole !== User::ROLE_ADMIN) {
            return [
                'success' => false,
                'message' => 'Segregation of Duties: Hanya Administrator yang berwenang menyetujui Sales Order. Sales tidak diperkenankan menyetujui order apa pun termasuk miliknya sendiri.',
            ];
        }

        $so = $this->soRepository->findById($soId);
        if ($so === null) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }

        if (!$so->canApprove()) {
            return [
                'success' => false,
                'message' => "Sales Order tidak dapat disetujui karena statusnya '{$so->getStatus()}' (hanya status PendingApproval yang dapat disetujui).",
            ];
        }

        $this->soRepository->updateStatus($soId, SalesOrder::STATUS_APPROVED, $adminUserId);

        return [
            'success' => true,
            'message' => "Sales Order #{$so->getSoNumber()} berhasil disetujui (Status: Approved).",
        ];
    }

    /**
     * Transition status PendingApproval -> Cancelled (Reject).
     * STRICT SEGREGATION OF DUTIES: ONLY Admin can reject.
     *
     * @return array{success: bool, message: string}
     */
    public function rejectSalesOrder(int $soId, int $adminUserId, string $userRole): array
    {
        if ($userRole !== User::ROLE_ADMIN) {
            return [
                'success' => false,
                'message' => 'Segregation of Duties: Hanya Administrator yang berwenang menolak Sales Order.',
            ];
        }

        $so = $this->soRepository->findById($soId);
        if ($so === null) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }

        if (!$so->canReject()) {
            return [
                'success' => false,
                'message' => "Sales Order tidak dapat ditolak karena statusnya '{$so->getStatus()}'.",
            ];
        }

        $this->soRepository->updateStatus($soId, SalesOrder::STATUS_CANCELLED);

        return [
            'success' => true,
            'message' => "Sales Order #{$so->getSoNumber()} telah ditolak dan dibatalkan.",
        ];
    }

    /**
     * Transition status -> Cancelled before Fulfilled.
     *
     * @return array{success: bool, message: string}
     */
    public function cancelSalesOrder(int $soId, int $userId): array
    {
        $so = $this->soRepository->findById($soId);
        if ($so === null) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }

        if (!$so->canCancel()) {
            return [
                'success' => false,
                'message' => "Sales Order tidak dapat dibatalkan karena statusnya sudah '{$so->getStatus()}'.",
            ];
        }

        $this->soRepository->updateStatus($soId, SalesOrder::STATUS_CANCELLED);

        return [
            'success' => true,
            'message' => "Sales Order #{$so->getSoNumber()} berhasil dibatalkan.",
        ];
    }

    /**
     * Process Goods Issue with Optimistic Locking & Atomic Transaction (ARCH-02).
     *
     * In a single atomic database transaction:
     * 1. Verifies that the SO is in Approved status.
     * 2. Reads current product_stock (including version column).
     * 3. Validates stock availability: rejects if requested qty > current physical stock.
     * 4. Executes Optimistic Lock update:
     *    UPDATE product_stock SET quantity = quantity - ?, version = version + 1
     *    WHERE product_id = ? AND warehouse_id = ? AND version = ? AND quantity >= ?
     *    If affected rows = 0 -> Concurrency conflict / oversell detected -> ROLLBACK.
     * 5. Writes 1 row per issued item into stock_ledger (type: Issue, reference: SalesOrder).
     * 6. Updates quantity_fulfilled on sales_order_items.
     * 7. Transitions SO to Fulfilled if all items are fully fulfilled.
     *
     * @param array<int, int|string> $issuedQuantities [itemId => issuedQty]
     * @return array{success: bool, message: string, so?: SalesOrder}
     */
    public function processGoodsIssue(int $soId, array $issuedQuantities, ?string $notes, int $userId): array
    {
        $this->database->beginTransaction();

        try {
            $so = $this->soRepository->findById($soId);
            if ($so === null) {
                throw new RuntimeException('Sales Order tidak ditemukan.');
            }

            if (!$so->canIssue()) {
                throw new RuntimeException("Pengeluaran barang (Goods Issue) tidak dapat diproses untuk status SO '{$so->getStatus()}'. SO harus berstatus 'Approved'.");
            }

            $totalIssuedInBatch = 0;
            $items = $so->getItems();
            $updatedItems = [];

            foreach ($items as $item) {
                $itemId = $item->getId();
                $qtyToIssue = isset($issuedQuantities[$itemId]) ? (int) $issuedQuantities[$itemId] : 0;

                if ($qtyToIssue < 0) {
                    throw new RuntimeException("Jumlah pengeluaran barang untuk produk '{$item->getProductName()}' tidak boleh negatif.");
                }

                $remaining = $item->getRemainingQuantity();
                if ($qtyToIssue > $remaining) {
                    throw new RuntimeException("Jumlah pengeluaran ({$qtyToIssue}) melebihi sisa pesanan ({$remaining}) untuk produk '{$item->getProductName()}'.");
                }

                if ($qtyToIssue > 0) {
                    $totalIssuedInBatch += $qtyToIssue;

                    // 1. Read current stock & version
                    $stock = $this->stockRepository->getStock($item->getProductId(), $so->getWarehouseId());
                    $availableQty = $stock !== null ? $stock->getQuantity() : 0;

                    if ($stock === null || $availableQty < $qtyToIssue) {
                        throw new RuntimeException(
                            "Stok fisik tidak mencukupi di {$so->getWarehouseName()} untuk produk '{$item->getProductName()}'. " .
                            "Tersedia: {$availableQty} {$item->getProductUnit()}, diminta keluar: {$qtyToIssue} {$item->getProductUnit()}."
                        );
                    }

                    // 2. Execute Optimistic Locking update on product_stock
                    $stockDecremented = $this->stockRepository->decreaseStockOptimistic(
                        productId: $item->getProductId(),
                        warehouseId: $so->getWarehouseId(),
                        quantity: $qtyToIssue,
                        expectedVersion: $stock->getVersion()
                    );

                    if (!$stockDecremented) {
                        // Concurrency conflict: another transaction modified version or depleted stock in parallel
                        throw new RuntimeException(
                            "Konflik konkurensi terdeteksi (Optimistic Lock): Stok atau versi produk '{$item->getProductName()}' " .
                            "telah berubah oleh transaksi lain secara bersamaan. Pengeluaran barang dibatalkan untuk mencegah oversell / stok negatif."
                        );
                    }

                    // 3. Write immutable log to stock_ledger (type: Issue)
                    $itemNote = trim(($notes ?? '') . " [Pengeluaran SO #{$so->getSoNumber()}: {$qtyToIssue} {$item->getProductUnit()}]");
                    $this->stockLedgerRepository->recordIssue(
                        productId: $item->getProductId(),
                        warehouseId: $so->getWarehouseId(),
                        quantity: $qtyToIssue,
                        referenceType: 'SalesOrder',
                        referenceId: $so->getId(),
                        notes: $itemNote,
                        createdBy: $userId
                    );

                    // 4. Update quantity_fulfilled on SO item
                    $newFulfilledQty = $item->getQuantityFulfilled() + $qtyToIssue;
                    $this->soRepository->updateItemFulfilledQuantity($itemId, $newFulfilledQty);
                    $item->setQuantityFulfilled($newFulfilledQty);
                }

                $updatedItems[] = $item;
            }

            if ($totalIssuedInBatch <= 0) {
                throw new RuntimeException('Tidak ada jumlah barang yang dikeluarkan (minimal 1 produk dengan kuantitas > 0).');
            }

            // 5. Evaluate if SO is fully fulfilled
            $allFulfilled = true;
            foreach ($updatedItems as $item) {
                if (!$item->isFullyFulfilled()) {
                    $allFulfilled = false;
                    break;
                }
            }

            $newStatus = $allFulfilled ? SalesOrder::STATUS_FULFILLED : SalesOrder::STATUS_APPROVED;
            if ($newStatus !== $so->getStatus()) {
                $this->soRepository->updateStatus($so->getId(), $newStatus);
            }

            $this->database->commit();

            $freshSo = $this->soRepository->findById($so->getId());

            return [
                'success' => true,
                'message' => "Pengeluaran {$totalIssuedInBatch} unit barang berhasil dicatat ke stok dan stock ledger. Status SO: {$newStatus}.",
                'so'      => $freshSo,
            ];
        } catch (Throwable $e) {
            $this->database->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
