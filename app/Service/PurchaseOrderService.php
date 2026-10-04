<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Repository\Database;
use App\Repository\ProductRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Repository\StockRepositoryInterface;
use App\Repository\SupplierRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;
use RuntimeException;
use Throwable;

/**
 * Service managing Purchase Orders, atomic Goods Receipts, and Stock Ledger mutations (PO-01).
 */
class PurchaseOrderService
{
    public function __construct(
        private Database $database,
        private PurchaseOrderRepositoryInterface $poRepository,
        private StockRepositoryInterface $stockRepository,
        private StockLedgerRepositoryInterface $stockLedgerRepository,
        private SupplierRepositoryInterface $supplierRepository,
        private WarehouseRepositoryInterface $warehouseRepository,
        private ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @return PurchaseOrder[]
     */
    public function getAllPurchaseOrders(?string $status = null, ?int $warehouseId = null): array
    {
        return $this->poRepository->findAll($status, $warehouseId, null, 'DESC', 1, 1000);
    }

    /**
     * @return array{items: PurchaseOrder[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function getPaginatedPurchaseOrders(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $total = $this->poRepository->countAll($status, $warehouseId, $search);
        $items = $this->poRepository->findAll($status, $warehouseId, $search, $sortOrder, $page, $perPage);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, $totalPages),
        ];
    }

    public function getPurchaseOrderById(int $id): ?PurchaseOrder
    {
        return $this->poRepository->findById($id);
    }

    public function getStockLedgerByPo(int $poId): array
    {
        return $this->stockLedgerRepository->findByReference('PurchaseOrder', $poId);
    }

    public function getAllStockLedger(?int $limit = 100): array
    {
        return $this->stockLedgerRepository->findAll($limit);
    }

    /**
     * Create a new Purchase Order in Draft status.
     *
     * @param array{
     *     supplier_id?: int|string,
     *     warehouse_id?: int|string,
     *     order_date?: string,
     *     items?: array<int, array{product_id?: int|string, quantity?: int|string, unit_price?: float|string}>
     * } $data
     * @return array{success: bool, errors?: array<string, string>, message?: string, po?: PurchaseOrder}
     */
    public function createPurchaseOrder(array $data, int $userId): array
    {
        $errors = [];

        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $orderDate = trim((string) ($data['order_date'] ?? date('Y-m-d')));
        $rawItems = $data['items'] ?? [];

        if ($supplierId <= 0 || $this->supplierRepository->findById($supplierId) === null) {
            $errors['supplier_id'] = 'Pemasok (supplier) wajib dipilih.';
        }

        if ($warehouseId <= 0 || $this->warehouseRepository->findById($warehouseId) === null) {
            $errors['warehouse_id'] = 'Gudang tujuan wajib dipilih.';
        }

        if (empty($orderDate)) {
            $errors['order_date'] = 'Tanggal pemesanan wajib diisi.';
        }

        if (empty($rawItems) || !is_array($rawItems)) {
            $errors['items'] = 'Minimal 1 item produk harus ditambahkan ke dalam Purchase Order.';
        }

        $items = [];
        foreach ($rawItems as $index => $itemData) {
            $productId = (int) ($itemData['product_id'] ?? 0);
            $qty = (int) ($itemData['quantity'] ?? 0);
            $price = (float) ($itemData['unit_price'] ?? 0.0);

            if ($productId <= 0 || $this->productRepository->findById($productId) === null) {
                $errors["item_{$index}_product"] = 'Produk pada baris ' . ($index + 1) . ' tidak valid.';
                continue;
            }

            if ($qty <= 0) {
                $errors["item_{$index}_quantity"] = 'Jumlah pesanan harus lebih dari 0 pada baris ' . ($index + 1) . '.';
            }

            if ($price < 0) {
                $errors["item_{$index}_price"] = 'Harga beli satuan tidak boleh negatif pada baris ' . ($index + 1) . '.';
            }

            $items[] = new PurchaseOrderItem(
                id: null,
                purchaseOrderId: null,
                productId: $productId,
                productSku: null,
                productName: null,
                productUnit: null,
                quantityOrdered: $qty,
                quantityReceived: 0,
                unitPrice: $price
            );
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $poNumber = $this->poRepository->getNextPoNumber();
        $order = new PurchaseOrder(
            id: null,
            poNumber: $poNumber,
            supplierId: $supplierId,
            supplierName: null,
            warehouseId: $warehouseId,
            warehouseName: null,
            status: PurchaseOrder::STATUS_DRAFT,
            createdBy: $userId,
            createdByName: null,
            orderDate: $orderDate,
            items: $items
        );

        $created = $this->poRepository->create($order, $items);

        return [
            'success' => true,
            'message' => "Purchase Order #{$poNumber} berhasil dibuat dengan status Draft.",
            'po'      => $created,
        ];
    }

    /**
     * Transition status Draft -> Ordered.
     *
     * @return array{success: bool, message: string}
     */
    public function markAsOrdered(int $poId, int $userId): array
    {
        $po = $this->poRepository->findById($poId);
        if ($po === null) {
            return ['success' => false, 'message' => 'Purchase Order tidak ditemukan.'];
        }

        if (!$po->isDraft()) {
            return ['success' => false, 'message' => "Purchase Order tidak dapat dikonfirmasi karena statusnya '{$po->getStatus()}'."];
        }

        if (empty($po->getItems())) {
            return ['success' => false, 'message' => 'Purchase Order tidak memiliki item barang.'];
        }

        $this->poRepository->updateStatus($poId, PurchaseOrder::STATUS_ORDERED);

        return ['success' => true, 'message' => "Purchase Order #{$po->getPoNumber()} berhasil dikonfirmasi (Status: Ordered)."];
    }

    /**
     * Transition status -> Cancelled.
     *
     * @return array{success: bool, message: string}
     */
    public function cancelPurchaseOrder(int $poId, int $userId): array
    {
        $po = $this->poRepository->findById($poId);
        if ($po === null) {
            return ['success' => false, 'message' => 'Purchase Order tidak ditemukan.'];
        }

        if (!$po->canCancel()) {
            return ['success' => false, 'message' => 'Purchase Order tidak dapat dibatalkan karena barang sudah pernah diterima sebagian.'];
        }

        $this->poRepository->updateStatus($poId, PurchaseOrder::STATUS_CANCELLED);

        return ['success' => true, 'message' => "Purchase Order #{$po->getPoNumber()} berhasil dibatalkan."];
    }

    /**
     * Process Goods Receipt (Atomic Transaction).
     *
     * In a single database transaction:
     * 1. Increases product_stock for destination warehouse.
     * 2. Writes 1 row per received item into stock_ledger (type: Receipt).
     * 3. Updates quantity_received on purchase_order_items.
     * 4. Updates purchase_orders status (PartiallyReceived or Received).
     * If any operation fails, rollBack() is triggered.
     *
     * @param array<int, int|string> $receivedQuantities [itemId => receivedQty]
     * @return array{success: bool, message: string, po?: PurchaseOrder}
     */
    public function processGoodsReceipt(int $poId, array $receivedQuantities, ?string $notes, int $userId): array
    {
        $this->database->beginTransaction();

        try {
            $po = $this->poRepository->findById($poId);
            if ($po === null) {
                throw new RuntimeException('Purchase Order tidak ditemukan.');
            }

            if (!$po->canReceive()) {
                throw new RuntimeException("Penerimaan barang tidak dapat diproses untuk status PO '{$po->getStatus()}'.");
            }

            $totalReceivedInBatch = 0;
            $items = $po->getItems();
            $updatedItems = [];

            foreach ($items as $item) {
                $itemId = $item->getId();
                $qtyToReceive = isset($receivedQuantities[$itemId]) ? (int) $receivedQuantities[$itemId] : 0;

                if ($qtyToReceive < 0) {
                    throw new RuntimeException("Jumlah penerimaan untuk produk '{$item->getProductName()}' tidak boleh negatif.");
                }

                $remaining = $item->getRemainingQuantity();
                if ($qtyToReceive > $remaining) {
                    throw new RuntimeException("Jumlah penerimaan ({$qtyToReceive}) melebihi sisa pesanan ({$remaining}) untuk produk '{$item->getProductName()}'.");
                }

                if ($qtyToReceive > 0) {
                    $totalReceivedInBatch += $qtyToReceive;

                    // 1. Atomically increase product_stock in target warehouse
                    $stockUpdated = $this->stockRepository->increaseStock($item->getProductId(), $po->getWarehouseId(), $qtyToReceive);
                    if (!$stockUpdated) {
                        throw new RuntimeException("Gagal memperbarui stok produk '{$item->getProductName()}'.");
                    }

                    // 2. Write 1 row into stock_ledger (type Receipt, reference to PO)
                    $itemNote = trim(($notes ?? '') . " [Penerimaan PO #{$po->getPoNumber()}: {$qtyToReceive} {$item->getProductUnit()}]");
                    $this->stockLedgerRepository->recordReceipt(
                        productId: $item->getProductId(),
                        warehouseId: $po->getWarehouseId(),
                        quantity: $qtyToReceive,
                        referenceType: 'PurchaseOrder',
                        referenceId: $po->getId(),
                        notes: $itemNote,
                        createdBy: $userId
                    );

                    // 3. Update quantity_received on PO item
                    $newReceivedQty = $item->getQuantityReceived() + $qtyToReceive;
                    $this->poRepository->updateItemReceivedQuantity($itemId, $newReceivedQty);
                    $item->setQuantityReceived($newReceivedQty);
                }

                $updatedItems[] = $item;
            }

            if ($totalReceivedInBatch <= 0) {
                throw new RuntimeException('Tidak ada jumlah barang yang diterima (minimal 1 produk dengan kuantitas > 0).');
            }

            // 4. Evaluate overall PO status
            $allFullyReceived = true;
            foreach ($updatedItems as $item) {
                if (!$item->isFullyReceived()) {
                    $allFullyReceived = false;
                    break;
                }
            }

            $newStatus = $allFullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
            $this->poRepository->updateStatus($po->getId(), $newStatus);

            $this->database->commit();

            $freshPo = $this->poRepository->findById($po->getId());

            return [
                'success' => true,
                'message' => "Penerimaan {$totalReceivedInBatch} unit barang berhasil dicatat ke stok dan stock ledger. Status PO kini: {$newStatus}.",
                'po'      => $freshPo,
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
