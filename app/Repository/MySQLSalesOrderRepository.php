<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use PDO;

/**
 * MySQL implementation for Sales Order persistence (SO-01).
 * Adheres strictly to the dynamic parameter binding guidelines (positional ?) in tech-debt.md.
 */
class MySQLSalesOrderRepository implements SalesOrderRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    public function generateNextSoNumber(): string
    {
        $pdo = $this->database->getConnection();
        $date = date('Ymd');
        $prefix = "SO-{$date}-";

        $stmt = $pdo->prepare('
            SELECT so_number 
            FROM sales_orders 
            WHERE so_number LIKE :prefix 
            ORDER BY id DESC 
            LIMIT 1
        ');
        $stmt->execute(['prefix' => "{$prefix}%"]);
        $latest = $stmt->fetchColumn();

        $seq = 1;
        if ($latest && is_string($latest)) {
            $lastNum = (int) substr($latest, strlen($prefix));
            $seq = $lastNum + 1;
        }

        return sprintf('%s%04d', $prefix, $seq);
    }

    /**
     * @param SalesOrderItem[] $items
     */
    public function create(SalesOrder $order, array $items): SalesOrder
    {
        $pdo = $this->database->getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO sales_orders (
                so_number, customer_id, warehouse_id, status, created_by, approved_by, order_date
            ) VALUES (
                :so_number, :customer_id, :warehouse_id, :status, :created_by, :approved_by, :order_date
            )
        ');

        $stmt->execute([
            'so_number'    => $order->getSoNumber(),
            'customer_id'  => $order->getCustomerId(),
            'warehouse_id' => $order->getWarehouseId(),
            'status'       => $order->getStatus(),
            'created_by'   => $order->getCreatedBy(),
            'approved_by'  => $order->getApprovedBy(),
            'order_date'   => $order->getOrderDate(),
        ]);

        $soId = (int) $pdo->lastInsertId();
        $order->setId($soId);

        // Insert items
        $itemStmt = $pdo->prepare('
            INSERT INTO sales_order_items (
                sales_order_id, product_id, quantity_ordered, quantity_fulfilled, unit_price
            ) VALUES (
                :sales_order_id, :product_id, :quantity_ordered, :quantity_fulfilled, :unit_price
            )
        ');

        $savedItems = [];
        foreach ($items as $item) {
            $itemStmt->execute([
                'sales_order_id'     => $soId,
                'product_id'         => $item->getProductId(),
                'quantity_ordered'   => $item->getQuantityOrdered(),
                'quantity_fulfilled' => $item->getQuantityFulfilled(),
                'unit_price'         => $item->getUnitPrice(),
            ]);

            $itemId = (int) $pdo->lastInsertId();
            $item->setId($itemId);
            $item->setSalesOrderId($soId);
            $savedItems[] = $item;
        }

        $order->setItems($savedItems);
        return $this->findById($soId) ?? $order;
    }

    public function findById(int $id): ?SalesOrder
    {
        $pdo = $this->database->getConnection();

        $stmt = $pdo->prepare('
            SELECT so.*,
                   c.name AS customer_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name,
                   ua.name AS approved_by_name
            FROM sales_orders so
            JOIN customers c ON c.id = so.customer_id
            JOIN warehouses w ON w.id = so.warehouse_id
            JOIN users u ON u.id = so.created_by
            LEFT JOIN users ua ON ua.id = so.approved_by
            WHERE so.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $items = $this->findItemsBySalesOrderId($id);

        return new SalesOrder(
            id: (int) $row['id'],
            soNumber: (string) $row['so_number'],
            customerId: (int) $row['customer_id'],
            customerName: (string) ($row['customer_name'] ?? ''),
            warehouseId: (int) $row['warehouse_id'],
            warehouseName: (string) ($row['warehouse_name'] ?? ''),
            status: (string) $row['status'],
            createdBy: (int) $row['created_by'],
            createdByName: (string) ($row['created_by_name'] ?? ''),
            approvedBy: isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            approvedByName: isset($row['approved_by_name']) ? (string) $row['approved_by_name'] : null,
            orderDate: (string) $row['order_date'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            items: $items
        );
    }

    /**
     * @return SalesOrder[]
     */
    public function findAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        $pdo = $this->database->getConnection();

        $conditions = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(so.so_number LIKE ? OR c.name LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'so.status = ?';
            $params[] = trim($status);
        }

        if ($warehouseId !== null && (int) $warehouseId > 0) {
            $conditions[] = 'so.warehouse_id = ?';
            $params[] = (int) $warehouseId;
        }

        if ($createdBy !== null && (int) $createdBy > 0) {
            $conditions[] = 'so.created_by = ?';
            $params[] = (int) $createdBy;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $dir = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT so.*,
                   c.name AS customer_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name,
                   ua.name AS approved_by_name
            FROM sales_orders so
            JOIN customers c ON c.id = so.customer_id
            JOIN warehouses w ON w.id = so.warehouse_id
            JOIN users u ON u.id = so.created_by
            LEFT JOIN users ua ON ua.id = so.approved_by
            {$whereClause}
            ORDER BY so.order_date {$dir}, so.id {$dir}
        ";

        if ($perPage > 0) {
            $page = max(1, $page);
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        // Fetch all items for retrieved orders
        $orderIds = array_column($rows, 'id');
        $itemsMap = $this->findItemsForOrderIds($orderIds);

        $orders = [];
        foreach ($rows as $row) {
            $soId = (int) $row['id'];
            $items = $itemsMap[$soId] ?? [];

            $orders[] = new SalesOrder(
                id: $soId,
                soNumber: (string) $row['so_number'],
                customerId: (int) $row['customer_id'],
                customerName: (string) ($row['customer_name'] ?? ''),
                warehouseId: (int) $row['warehouse_id'],
                warehouseName: (string) ($row['warehouse_name'] ?? ''),
                status: (string) $row['status'],
                createdBy: (int) $row['created_by'],
                createdByName: (string) ($row['created_by_name'] ?? ''),
                approvedBy: isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
                approvedByName: isset($row['approved_by_name']) ? (string) $row['approved_by_name'] : null,
                orderDate: (string) $row['order_date'],
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                items: $items
            );
        }

        return $orders;
    }

    public function countAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null
    ): int {
        $pdo = $this->database->getConnection();

        $conditions = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(so.so_number LIKE ? OR c.name LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'so.status = ?';
            $params[] = trim($status);
        }

        if ($warehouseId !== null && (int) $warehouseId > 0) {
            $conditions[] = 'so.warehouse_id = ?';
            $params[] = (int) $warehouseId;
        }

        if ($createdBy !== null && (int) $createdBy > 0) {
            $conditions[] = 'so.created_by = ?';
            $params[] = (int) $createdBy;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT COUNT(*)
            FROM sales_orders so
            JOIN customers c ON c.id = so.customer_id
            {$whereClause}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getOrderCountsByStatus(?int $createdBy = null): array
    {
        $pdo = $this->database->getConnection();
        $conditions = [];
        $params = [];

        if ($createdBy !== null && (int) $createdBy > 0) {
            $conditions[] = 'created_by = ?';
            $params[] = (int) $createdBy;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT status, COUNT(*) AS cnt FROM sales_orders {$where} GROUP BY status";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }

    public function getTotalSalesAmount(?int $createdBy = null): float
    {
        $pdo = $this->database->getConnection();
        $conditions = ["so.status != 'Cancelled'"];
        $params = [];

        if ($createdBy !== null && (int) $createdBy > 0) {
            $conditions[] = 'so.created_by = ?';
            $params[] = (int) $createdBy;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "
            SELECT COALESCE(SUM(soi.quantity_ordered * soi.unit_price), 0) AS total_sales
            FROM sales_orders so
            JOIN sales_order_items soi ON so.id = soi.sales_order_id
            {$where}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    public function getPendingIssueQueue(int $limit = 10): array
    {
        $pdo = $this->database->getConnection();
        $sql = "
            SELECT so.*,
                   c.name AS customer_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name,
                   ua.name AS approved_by_name
            FROM sales_orders so
            JOIN customers c ON c.id = so.customer_id
            JOIN warehouses w ON w.id = so.warehouse_id
            JOIN users u ON u.id = so.created_by
            LEFT JOIN users ua ON ua.id = so.approved_by
            WHERE so.status = 'Approved'
            ORDER BY so.order_date ASC, so.id ASC
            LIMIT " . (int) $limit . "
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return [];
        }

        $orderIds = array_column($rows, 'id');
        $itemsMap = $this->findItemsForOrderIds($orderIds);

        $orders = [];
        foreach ($rows as $row) {
            $soId = (int) $row['id'];
            $items = $itemsMap[$soId] ?? [];

            $orders[] = new SalesOrder(
                id: $soId,
                soNumber: (string) $row['so_number'],
                customerId: (int) $row['customer_id'],
                customerName: (string) ($row['customer_name'] ?? ''),
                warehouseId: (int) $row['warehouse_id'],
                warehouseName: (string) ($row['warehouse_name'] ?? ''),
                status: (string) $row['status'],
                createdBy: (int) $row['created_by'],
                createdByName: (string) ($row['created_by_name'] ?? ''),
                approvedBy: isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
                approvedByName: isset($row['approved_by_name']) ? (string) $row['approved_by_name'] : null,
                orderDate: (string) $row['order_date'],
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                items: $items
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
            $conditions[] = 'so.order_date >= ?';
            $params[] = trim($startDate);
        }

        if ($endDate !== null && trim($endDate) !== '') {
            $conditions[] = 'so.order_date <= ?';
            $params[] = trim($endDate);
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'so.status = ?';
            $params[] = trim($status);
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT so.*,
                   c.name AS customer_name,
                   w.name AS warehouse_name,
                   u.name AS created_by_name,
                   ua.name AS approved_by_name
            FROM sales_orders so
            JOIN customers c ON c.id = so.customer_id
            JOIN warehouses w ON w.id = so.warehouse_id
            JOIN users u ON u.id = so.created_by
            LEFT JOIN users ua ON ua.id = so.approved_by
            {$whereClause}
            ORDER BY so.order_date DESC, so.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        $orderIds = array_column($rows, 'id');
        $itemsMap = $this->findItemsForOrderIds($orderIds);

        $orders = [];
        foreach ($rows as $row) {
            $soId = (int) $row['id'];
            $items = $itemsMap[$soId] ?? [];

            $orders[] = new SalesOrder(
                id: $soId,
                soNumber: (string) $row['so_number'],
                customerId: (int) $row['customer_id'],
                customerName: (string) ($row['customer_name'] ?? ''),
                warehouseId: (int) $row['warehouse_id'],
                warehouseName: (string) ($row['warehouse_name'] ?? ''),
                status: (string) $row['status'],
                createdBy: (int) $row['created_by'],
                createdByName: (string) ($row['created_by_name'] ?? ''),
                approvedBy: isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
                approvedByName: isset($row['approved_by_name']) ? (string) $row['approved_by_name'] : null,
                orderDate: (string) $row['order_date'],
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                items: $items
            );
        }

        return $orders;
    }


    public function updateStatus(int $id, string $status, ?int $approvedBy = null): bool
    {
        $pdo = $this->database->getConnection();
        if ($approvedBy !== null) {
            $stmt = $pdo->prepare('
                UPDATE sales_orders 
                SET status = :status, approved_by = :approved_by, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ');
            return $stmt->execute([
                'status'      => $status,
                'approved_by' => $approvedBy,
                'id'          => $id,
            ]);
        }

        $stmt = $pdo->prepare('
            UPDATE sales_orders 
            SET status = :status, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ');
        return $stmt->execute([
            'status' => $status,
            'id'     => $id,
        ]);
    }

    public function updateItemFulfilledQuantity(int $itemId, int $quantity): bool
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            UPDATE sales_order_items
            SET quantity_fulfilled = :quantity
            WHERE id = :id
        ');
        return $stmt->execute([
            'quantity' => $quantity,
            'id'       => $itemId,
        ]);
    }

    public function findItemById(int $itemId): ?SalesOrderItem
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            SELECT soi.*,
                   p.sku AS product_sku,
                   p.name AS product_name,
                   p.unit AS product_unit
            FROM sales_order_items soi
            JOIN products p ON p.id = soi.product_id
            WHERE soi.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new SalesOrderItem(
            id: (int) $row['id'],
            salesOrderId: (int) $row['sales_order_id'],
            productId: (int) $row['product_id'],
            productSku: (string) ($row['product_sku'] ?? ''),
            productName: (string) ($row['product_name'] ?? ''),
            productUnit: (string) ($row['product_unit'] ?? 'pcs'),
            quantityOrdered: (int) $row['quantity_ordered'],
            quantityFulfilled: (int) $row['quantity_fulfilled'],
            unitPrice: (float) $row['unit_price']
        );
    }

    /**
     * @return SalesOrderItem[]
     */
    private function findItemsBySalesOrderId(int $salesOrderId): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            SELECT soi.*,
                   p.sku AS product_sku,
                   p.name AS product_name,
                   p.unit AS product_unit
            FROM sales_order_items soi
            JOIN products p ON p.id = soi.product_id
            WHERE soi.sales_order_id = :so_id
            ORDER BY soi.id ASC
        ');
        $stmt->execute(['so_id' => $salesOrderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = new SalesOrderItem(
                id: (int) $row['id'],
                salesOrderId: (int) $row['sales_order_id'],
                productId: (int) $row['product_id'],
                productSku: (string) ($row['product_sku'] ?? ''),
                productName: (string) ($row['product_name'] ?? ''),
                productUnit: (string) ($row['product_unit'] ?? 'pcs'),
                quantityOrdered: (int) $row['quantity_ordered'],
                quantityFulfilled: (int) $row['quantity_fulfilled'],
                unitPrice: (float) $row['unit_price']
            );
        }

        return $items;
    }

    /**
     * @param int[] $orderIds
     * @return array<int, SalesOrderItem[]>
     */
    private function findItemsForOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $pdo = $this->database->getConnection();
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmt = $pdo->prepare("
            SELECT soi.*,
                   p.sku AS product_sku,
                   p.name AS product_name,
                   p.unit AS product_unit
            FROM sales_order_items soi
            JOIN products p ON p.id = soi.product_id
            WHERE soi.sales_order_id IN ({$placeholders})
            ORDER BY soi.id ASC
        ");
        $stmt->execute($orderIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $itemsMap = [];
        foreach ($rows as $row) {
            $soId = (int) $row['sales_order_id'];
            if (!isset($itemsMap[$soId])) {
                $itemsMap[$soId] = [];
            }
            $itemsMap[$soId][] = new SalesOrderItem(
                id: (int) $row['id'],
                salesOrderId: $soId,
                productId: (int) $row['product_id'],
                productSku: (string) ($row['product_sku'] ?? ''),
                productName: (string) ($row['product_name'] ?? ''),
                productUnit: (string) ($row['product_unit'] ?? 'pcs'),
                quantityOrdered: (int) $row['quantity_ordered'],
                quantityFulfilled: (int) $row['quantity_fulfilled'],
                unitPrice: (float) $row['unit_price']
            );
        }

        return $itemsMap;
    }
}
