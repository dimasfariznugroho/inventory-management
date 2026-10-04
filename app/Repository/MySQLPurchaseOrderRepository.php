<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use PDO;

/**
 * MySQL implementation for Purchase Order storage and item relationships.
 */
class MySQLPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    public function getNextPoNumber(): string
    {
        $pdo = $this->database->getConnection();
        $prefix = 'PO-' . date('Ym') . '-';

        $stmt = $pdo->prepare('
            SELECT po_number FROM purchase_orders
            WHERE po_number LIKE :prefix
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if ($last) {
            $numPart = (int) substr((string) $last, strlen($prefix));
            $seq = $numPart + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s%04d', $prefix, $seq);
    }

    /**
     * @param PurchaseOrderItem[] $items
     */
    public function create(PurchaseOrder $order, array $items): PurchaseOrder
    {
        $pdo = $this->database->getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO purchase_orders (
                po_number, supplier_id, warehouse_id, status, created_by, order_date
            ) VALUES (
                :po_number, :supplier_id, :warehouse_id, :status, :created_by, :order_date
            )
        ');

        $stmt->execute([
            'po_number'    => $order->getPoNumber(),
            'supplier_id'  => $order->getSupplierId(),
            'warehouse_id' => $order->getWarehouseId(),
            'status'       => $order->getStatus(),
            'created_by'   => $order->getCreatedBy(),
            'order_date'   => $order->getOrderDate(),
        ]);

        $poId = (int) $pdo->lastInsertId();
        $order->setId($poId);

        // Insert items
        $itemStmt = $pdo->prepare('
            INSERT INTO purchase_order_items (
                purchase_order_id, product_id, quantity_ordered, quantity_received, unit_price
            ) VALUES (
                :purchase_order_id, :product_id, :quantity_ordered, :quantity_received, :unit_price
            )
        ');

        $savedItems = [];
        foreach ($items as $item) {
            $itemStmt->execute([
                'purchase_order_id' => $poId,
                'product_id'        => $item->getProductId(),
                'quantity_ordered'  => $item->getQuantityOrdered(),
                'quantity_received' => $item->getQuantityReceived(),
                'unit_price'        => $item->getUnitPrice(),
            ]);

            $itemId = (int) $pdo->lastInsertId();
            $item->setId($itemId);
            $item->setPurchaseOrderId($poId);
            $savedItems[] = $item;
        }

        $order->setItems($savedItems);
        return $this->findById($poId) ?? $order;
    }

    public function findById(int $id): ?PurchaseOrder
    {
        $pdo = $this->database->getConnection();

        $stmt = $pdo->prepare('
            SELECT po.*,
                   s.name AS supplier_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            JOIN warehouses w ON w.id = po.warehouse_id
            JOIN users u ON u.id = po.created_by
            WHERE po.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Fetch items
        $itemStmt = $pdo->prepare('
            SELECT poi.*,
                   p.sku AS product_sku,
                   p.name AS product_name,
                   p.unit AS product_unit
            FROM purchase_order_items poi
            JOIN products p ON p.id = poi.product_id
            WHERE poi.purchase_order_id = :po_id
            ORDER BY poi.id ASC
        ');
        $itemStmt->execute(['po_id' => $id]);
        $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($itemRows as $iRow) {
            $items[] = new PurchaseOrderItem(
                id: (int) $iRow['id'],
                purchaseOrderId: (int) $iRow['purchase_order_id'],
                productId: (int) $iRow['product_id'],
                productSku: (string) ($iRow['product_sku'] ?? ''),
                productName: (string) ($iRow['product_name'] ?? ''),
                productUnit: (string) ($iRow['product_unit'] ?? 'pcs'),
                quantityOrdered: (int) $iRow['quantity_ordered'],
                quantityReceived: (int) $iRow['quantity_received'],
                unitPrice: (float) $iRow['unit_price']
            );
        }

        return new PurchaseOrder(
            id: (int) $row['id'],
            poNumber: (string) $row['po_number'],
            supplierId: (int) $row['supplier_id'],
            supplierName: (string) $row['supplier_name'],
            warehouseId: (int) $row['warehouse_id'],
            warehouseName: (string) $row['warehouse_name'],
            status: (string) $row['status'],
            createdBy: (int) $row['created_by'],
            createdByName: (string) $row['created_by_name'],
            orderDate: (string) $row['order_date'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            items: $items
        );
    }

    public function findByPoNumber(string $poNumber): ?PurchaseOrder
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT id FROM purchase_orders WHERE po_number = :po_number LIMIT 1');
        $stmt->execute(['po_number' => $poNumber]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            return null;
        }

        return $this->findById((int) $id);
    }

    /**
     * @return PurchaseOrder[]
     */
    public function findAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        $pdo = $this->database->getConnection();

        $conditions = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(po.po_number LIKE ? OR s.name LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'po.status = ?';
            $params[] = trim($status);
        }

        if ($warehouseId !== null && (int) $warehouseId > 0) {
            $conditions[] = 'po.warehouse_id = ?';
            $params[] = (int) $warehouseId;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $dir = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT po.*,
                   s.name AS supplier_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            JOIN warehouses w ON w.id = po.warehouse_id
            JOIN users u ON u.id = po.created_by
            {$whereClause}
            ORDER BY po.order_date {$dir}, po.id {$dir}
        ";

        if ($perPage > 0) {
            $page = max(1, $page);
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $orders = [];
        foreach ($rows as $row) {
            // Load items for each order
            $itemStmt = $pdo->prepare('
                SELECT poi.*,
                       p.sku AS product_sku,
                       p.name AS product_name,
                       p.unit AS product_unit
                FROM purchase_order_items poi
                JOIN products p ON p.id = poi.product_id
                WHERE poi.purchase_order_id = :po_id
                ORDER BY poi.id ASC
            ');
            $itemStmt->execute(['po_id' => $row['id']]);
            $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            $items = [];
            foreach ($itemRows as $iRow) {
                $items[] = new PurchaseOrderItem(
                    id: (int) $iRow['id'],
                    purchaseOrderId: (int) $iRow['purchase_order_id'],
                    productId: (int) $iRow['product_id'],
                    productSku: (string) ($iRow['product_sku'] ?? ''),
                    productName: (string) ($iRow['product_name'] ?? ''),
                    productUnit: (string) ($iRow['product_unit'] ?? 'pcs'),
                    quantityOrdered: (int) $iRow['quantity_ordered'],
                    quantityReceived: (int) $iRow['quantity_received'],
                    unitPrice: (float) $iRow['unit_price']
                );
            }

            $orders[] = new PurchaseOrder(
                id: (int) $row['id'],
                poNumber: (string) $row['po_number'],
                supplierId: (int) $row['supplier_id'],
                supplierName: (string) $row['supplier_name'],
                warehouseId: (int) $row['warehouse_id'],
                warehouseName: (string) $row['warehouse_name'],
                status: (string) $row['status'],
                createdBy: (int) $row['created_by'],
                createdByName: (string) $row['created_by_name'],
                orderDate: (string) $row['order_date'],
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                items: $items
            );
        }

        return $orders;
    }

    public function countAll(?string $status = null, ?int $warehouseId = null, ?string $search = null): int
    {
        $pdo = $this->database->getConnection();

        $conditions = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(po.po_number LIKE ? OR s.name LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'po.status = ?';
            $params[] = trim($status);
        }

        if ($warehouseId !== null && (int) $warehouseId > 0) {
            $conditions[] = 'po.warehouse_id = ?';
            $params[] = (int) $warehouseId;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT COUNT(*)
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            {$whereClause}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getOrderCountsByStatus(): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->query('SELECT status, COUNT(*) AS cnt FROM purchase_orders GROUP BY status');
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }

    public function getPendingReceiptQueue(int $limit = 10): array
    {
        $pdo = $this->database->getConnection();
        $sql = "
            SELECT po.*,
                   s.name AS supplier_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            JOIN warehouses w ON w.id = po.warehouse_id
            JOIN users u ON u.id = po.created_by
            WHERE po.status IN ('Ordered', 'PartiallyReceived')
            ORDER BY po.order_date ASC, po.id ASC
            LIMIT " . (int) $limit . "
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $orders = [];
        foreach ($rows as $row) {
            $orders[] = new PurchaseOrder(
                id: (int) $row['id'],
                poNumber: (string) $row['po_number'],
                supplierId: (int) $row['supplier_id'],
                supplierName: (string) $row['supplier_name'],
                warehouseId: (int) $row['warehouse_id'],
                warehouseName: (string) $row['warehouse_name'],
                status: (string) $row['status'],
                createdBy: (int) $row['created_by'],
                createdByName: (string) $row['created_by_name'],
                orderDate: (string) $row['order_date'],
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                items: []
            );
        }

        return $orders;
    }

    public function findForReport(?string $startDate = null, ?string $endDate = null, ?string $status = null): array
    {
        $pdo = $this->database->getConnection();
        $conditions = [];
        $params = [];

        if ($startDate !== null && trim($startDate) !== '') {
            $conditions[] = 'po.order_date >= ?';
            $params[] = trim($startDate);
        }

        if ($endDate !== null && trim($endDate) !== '') {
            $conditions[] = 'po.order_date <= ?';
            $params[] = trim($endDate);
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'po.status = ?';
            $params[] = trim($status);
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT po.*,
                   s.name AS supplier_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            JOIN warehouses w ON w.id = po.warehouse_id
            JOIN users u ON u.id = po.created_by
            {$whereClause}
            ORDER BY po.order_date DESC, po.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $orders = [];
        foreach ($rows as $row) {
            $itemStmt = $pdo->prepare('
                SELECT poi.*, p.sku AS product_sku, p.name AS product_name, p.unit AS product_unit
                FROM purchase_order_items poi
                JOIN products p ON p.id = poi.product_id
                WHERE poi.purchase_order_id = :po_id
                ORDER BY poi.id ASC
            ');
            $itemStmt->execute(['po_id' => $row['id']]);
            $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            $items = [];
            foreach ($itemRows as $iRow) {
                $items[] = new PurchaseOrderItem(
                    id: (int) $iRow['id'],
                    purchaseOrderId: (int) $iRow['purchase_order_id'],
                    productId: (int) $iRow['product_id'],
                    productSku: (string) ($iRow['product_sku'] ?? ''),
                    productName: (string) ($iRow['product_name'] ?? ''),
                    productUnit: (string) ($iRow['product_unit'] ?? 'pcs'),
                    quantityOrdered: (int) $iRow['quantity_ordered'],
                    quantityReceived: (int) $iRow['quantity_received'],
                    unitPrice: (float) $iRow['unit_price']
                );
            }

            $orders[] = new PurchaseOrder(
                id: (int) $row['id'],
                poNumber: (string) $row['po_number'],
                supplierId: (int) $row['supplier_id'],
                supplierName: (string) $row['supplier_name'],
                warehouseId: (int) $row['warehouse_id'],
                warehouseName: (string) $row['warehouse_name'],
                status: (string) $row['status'],
                createdBy: (int) $row['created_by'],
                createdByName: (string) $row['created_by_name'],
                orderDate: (string) $row['order_date'],
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                items: $items
            );
        }

        return $orders;
    }


    public function updateStatus(int $id, string $status): bool
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('UPDATE purchase_orders SET status = :status WHERE id = :id');
        return $stmt->execute([
            'id'     => $id,
            'status' => $status,
        ]);
    }

    public function updateItemReceivedQuantity(int $itemId, int $quantityReceived): bool
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('UPDATE purchase_order_items SET quantity_received = :quantity_received WHERE id = :id');
        return $stmt->execute([
            'id'                => $itemId,
            'quantity_received' => $quantityReceived,
        ]);
    }
}
