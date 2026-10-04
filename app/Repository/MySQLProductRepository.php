<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductStock;
use PDO;

/**
 * Concrete MySQL repository implementation for Product entity and multi-warehouse stock.
 */
class MySQLProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * @return Product[]
     */
    public function findAllWithStock(
        ?string $search = null,
        ?int $categoryId = null,
        ?string $stockStatus = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $pdo = $this->database->getConnection();

        $conditions = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($categoryId !== null && (int) $categoryId > 0) {
            $conditions[] = 'p.category_id = ?';
            $params[] = (int) $categoryId;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT p.*, c.name AS category_name,
                   COALESCE(SUM(ps.quantity), 0) AS total_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_stock ps ON p.id = ps.product_id
            {$whereClause}
            GROUP BY p.id, c.name
        ";

        if ($stockStatus === 'low') {
            $sql .= ' HAVING total_stock <= p.reorder_point';
        } elseif ($stockStatus === 'normal') {
            $sql .= ' HAVING total_stock > p.reorder_point';
        }

        $sql .= ' ORDER BY p.id ASC';

        if ($perPage > 0) {
            $page = max(1, $page);
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $products = [];
        foreach ($rows as $row) {
            $products[] = $this->mapRowToEntity($row, (int) ($row['total_stock'] ?? 0));
        }

        return $products;
    }

    public function countAllWithStock(?string $search = null, ?int $categoryId = null, ?string $stockStatus = null): int
    {
        $pdo = $this->database->getConnection();

        $conditions = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($categoryId !== null && (int) $categoryId > 0) {
            $conditions[] = 'p.category_id = ?';
            $params[] = (int) $categoryId;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $havingClause = '';
        if ($stockStatus === 'low') {
            $havingClause = 'HAVING total_stock <= p.reorder_point';
        } elseif ($stockStatus === 'normal') {
            $havingClause = 'HAVING total_stock > p.reorder_point';
        }

        $sql = "
            SELECT COUNT(*) FROM (
                SELECT p.id, p.reorder_point, COALESCE(SUM(ps.quantity), 0) AS total_stock
                FROM products p
                LEFT JOIN product_stock ps ON p.id = ps.product_id
                {$whereClause}
                GROUP BY p.id, p.reorder_point
                {$havingClause}
            ) AS subquery
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findBySkuWithWarehouseBreakdown(string $sku): ?Product
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
        $stmt->execute(['sku' => trim($sku)]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            return null;
        }

        return $this->findByIdWithWarehouseBreakdown((int) $id);
    }

    public function getLowStockCount(): int
    {
        $pdo = $this->database->getConnection();
        $sql = "
            SELECT COUNT(*) FROM (
                SELECT p.id, p.reorder_point, COALESCE(SUM(ps.quantity), 0) AS total_stock
                FROM products p
                LEFT JOIN product_stock ps ON p.id = ps.product_id
                WHERE p.is_active = 1
                GROUP BY p.id, p.reorder_point
                HAVING total_stock <= p.reorder_point
            ) AS low_stock_sub
        ";
        return (int) $pdo->query($sql)->fetchColumn();
    }

    public function getTotalInventoryValuation(): float
    {
        $pdo = $this->database->getConnection();
        $sql = "
            SELECT COALESCE(SUM(p.purchase_price * ps.quantity), 0) AS total_valuation
            FROM products p
            JOIN product_stock ps ON p.id = ps.product_id
            WHERE p.is_active = 1
        ";
        return (float) $pdo->query($sql)->fetchColumn();
    }

    public function getLowStockProducts(int $limit = 10): array
    {
        $pdo = $this->database->getConnection();
        $sql = "
            SELECT p.*, c.name AS category_name,
                   COALESCE(SUM(ps.quantity), 0) AS total_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_stock ps ON p.id = ps.product_id
            WHERE p.is_active = 1
            GROUP BY p.id, c.name
            HAVING total_stock <= p.reorder_point
            ORDER BY (total_stock - p.reorder_point) ASC, p.id ASC
            LIMIT " . (int) $limit . "
        ";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $products = [];
        foreach ($rows as $row) {
            $products[] = $this->mapRowToEntity($row, (int) ($row['total_stock'] ?? 0));
        }

        return $products;
    }


    public function findByIdWithWarehouseBreakdown(int $id): ?Product
    {
        $pdo = $this->database->getConnection();

        // 1. Fetch Product
        $stmt = $pdo->prepare('
            SELECT p.*, c.name AS category_name,
                   COALESCE(SUM(ps.quantity), 0) AS total_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_stock ps ON p.id = ps.product_id
            WHERE p.id = :id
            GROUP BY p.id, c.name
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $totalStock = (int) ($row['total_stock'] ?? 0);
        $product = $this->mapRowToEntity($row, $totalStock);

        // 2. Fetch Multi-Warehouse Breakdown (WH-01)
        $stockStmt = $pdo->prepare('
            SELECT w.id AS warehouse_id, w.name AS warehouse_name, w.location AS warehouse_location,
                   COALESCE(ps.id, NULL) AS stock_id,
                   COALESCE(ps.quantity, 0) AS quantity,
                   COALESCE(ps.version, 1) AS version,
                   ps.updated_at
            FROM warehouses w
            LEFT JOIN product_stock ps ON w.id = ps.warehouse_id AND ps.product_id = :product_id
            WHERE w.is_active = 1
            ORDER BY w.id ASC
        ');
        $stockStmt->execute(['product_id' => $id]);
        $stockRows = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

        $warehouseStocks = [];
        foreach ($stockRows as $sRow) {
            $warehouseStocks[] = new ProductStock(
                id: isset($sRow['stock_id']) ? (int) $sRow['stock_id'] : null,
                productId: $id,
                warehouseId: (int) $sRow['warehouse_id'],
                warehouseName: (string) $sRow['warehouse_name'],
                quantity: (int) $sRow['quantity'],
                version: (int) $sRow['version'],
                updatedAt: isset($sRow['updated_at']) ? (string) $sRow['updated_at'] : null,
                warehouseLocation: isset($sRow['warehouse_location']) ? (string) $sRow['warehouse_location'] : null
            );
        }

        $product->setWarehouseStocks($warehouseStocks);

        return $product;
    }

    public function findById(int $id): ?Product
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            SELECT p.*, c.name AS category_name,
                   COALESCE((SELECT SUM(quantity) FROM product_stock WHERE product_id = p.id), 0) AS total_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row, (int) ($row['total_stock'] ?? 0));
    }

    public function skuExists(string $sku, ?int $excludeProductId = null): bool
    {
        $pdo = $this->database->getConnection();

        if ($excludeProductId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM products WHERE sku = :sku AND id != :exclude_id LIMIT 1');
            $stmt->execute([
                'sku'        => $sku,
                'exclude_id' => $excludeProductId,
            ]);
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM products WHERE sku = :sku LIMIT 1');
            $stmt->execute(['sku' => $sku]);
        }

        return (bool) $stmt->fetchColumn();
    }

    public function isUsedInOrders(int $productId): bool
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            SELECT 1 FROM purchase_order_items WHERE product_id = :pid
            UNION ALL
            SELECT 1 FROM sales_order_items WHERE product_id = :pid
            LIMIT 1
        ');
        $stmt->execute(['pid' => $productId]);
        return (bool) $stmt->fetchColumn();
    }

    public function save(Product $product): Product
    {
        $pdo = $this->database->getConnection();

        if ($product->getId() === null) {
            $stmt = $pdo->prepare('
                INSERT INTO products (sku, name, category_id, unit, purchase_price, selling_price, reorder_point, image_path, is_active)
                VALUES (:sku, :name, :category_id, :unit, :purchase_price, :selling_price, :reorder_point, :image_path, :is_active)
            ');

            $stmt->execute([
                'sku'            => $product->getSku(),
                'name'           => $product->getName(),
                'category_id'    => $product->getCategoryId(),
                'unit'           => $product->getUnit(),
                'purchase_price' => $product->getPurchasePrice(),
                'selling_price'  => $product->getSellingPrice(),
                'reorder_point'  => $product->getReorderPoint(),
                'image_path'     => $product->getImagePath(),
                'is_active'      => $product->isActive() ? 1 : 0,
            ]);

            $product->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('
                UPDATE products
                SET sku = :sku,
                    name = :name,
                    category_id = :category_id,
                    unit = :unit,
                    purchase_price = :purchase_price,
                    selling_price = :selling_price,
                    reorder_point = :reorder_point,
                    image_path = :image_path,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id'             => $product->getId(),
                'sku'            => $product->getSku(),
                'name'           => $product->getName(),
                'category_id'    => $product->getCategoryId(),
                'unit'           => $product->getUnit(),
                'purchase_price' => $product->getPurchasePrice(),
                'selling_price'  => $product->getSellingPrice(),
                'reorder_point'  => $product->getReorderPoint(),
                'image_path'     => $product->getImagePath(),
                'is_active'      => $product->isActive() ? 1 : 0,
            ]);
        }

        return $product;
    }

    public function delete(int $id): bool
    {
        $pdo = $this->database->getConnection();
        // Delete any existing initial stock rows if present
        $delStock = $pdo->prepare('DELETE FROM product_stock WHERE product_id = :id');
        $delStock->execute(['id' => $id]);

        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    private function mapRowToEntity(array $row, int $totalStock = 0): Product
    {
        return new Product(
            id: (int) $row['id'],
            sku: (string) $row['sku'],
            name: (string) $row['name'],
            categoryId: (int) $row['category_id'],
            categoryName: isset($row['category_name']) ? (string) $row['category_name'] : null,
            unit: (string) ($row['unit'] ?? 'pcs'),
            purchasePrice: (float) ($row['purchase_price'] ?? 0.0),
            sellingPrice: (float) ($row['selling_price'] ?? 0.0),
            reorderPoint: (int) ($row['reorder_point'] ?? 0),
            imagePath: isset($row['image_path']) ? (string) $row['image_path'] : null,
            isActive: (bool) $row['is_active'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            totalStock: $totalStock
        );
    }
}
