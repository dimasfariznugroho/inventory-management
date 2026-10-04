<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;
use PDO;

/**
 * MySQL implementation for product physical stock updates.
 */
class MySQLStockRepository implements StockRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    public function increaseStock(int $productId, int $warehouseId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO product_stock (product_id, warehouse_id, quantity, version)
            VALUES (:product_id, :warehouse_id, :quantity, 1)
            ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                version = version + 1
        ');

        return $stmt->execute([
            'product_id'   => $productId,
            'warehouse_id' => $warehouseId,
            'quantity'     => $quantity,
        ]);
    }

    public function getStock(int $productId, int $warehouseId): ?ProductStock
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            SELECT ps.*, w.name AS warehouse_name, w.location AS warehouse_location
            FROM product_stock ps
            JOIN warehouses w ON w.id = ps.warehouse_id
            WHERE ps.product_id = :product_id AND ps.warehouse_id = :warehouse_id
            LIMIT 1
        ');
        $stmt->execute([
            'product_id'   => $productId,
            'warehouse_id' => $warehouseId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new ProductStock(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            warehouseId: (int) $row['warehouse_id'],
            warehouseName: (string) $row['warehouse_name'],
            quantity: (int) $row['quantity'],
            version: (int) $row['version'],
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            warehouseLocation: isset($row['warehouse_location']) ? (string) $row['warehouse_location'] : null
        );
    }

    public function decreaseStockOptimistic(int $productId, int $warehouseId, int $quantity, int $expectedVersion): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            UPDATE product_stock
            SET quantity = quantity - :quantity,
                version = version + 1
            WHERE product_id = :product_id
              AND warehouse_id = :warehouse_id
              AND version = :version
              AND quantity >= :quantity_check
        ');

        $stmt->execute([
            'quantity'       => $quantity,
            'product_id'     => $productId,
            'warehouse_id'   => $warehouseId,
            'version'        => $expectedVersion,
            'quantity_check' => $quantity,
        ]);

        return $stmt->rowCount() > 0;
    }
}

