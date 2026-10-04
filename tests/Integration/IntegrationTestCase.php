<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Database;
use Config\Env;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base TestCase for Integration Tests touching real Docker MySQL.
 * Adheres strictly to FIRST principles:
 * - Fast: executes directly over local Docker network without artificial delays or sleeps.
 * - Independent: each test sets up its own unique isolated records and cleans up in tearDown.
 * - Repeatable: runs consistently regardless of execution order.
 * - Self-validating: automated SQL and domain assertions.
 * - Timely: exercises real database foreign keys, unique constraints, transactions, and rowCount().
 */
abstract class IntegrationTestCase extends TestCase
{
    protected ?Database $database = null;
    protected ?PDO $pdo = null;

    /** @var array<string, array<int, int>> Tracking created IDs for teardown [table => [id1, id2]] */
    protected array $cleanupRecords = [
        'stock_ledger'         => [],
        'sales_order_items'    => [],
        'sales_orders'         => [],
        'purchase_order_items' => [],
        'purchase_orders'      => [],
        'product_stock'        => [],
        'products'             => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';

        $host = (string) Env::get('DB_HOST', $dbConfig['host'] ?? 'mysql');
        $port = (int) Env::get('DB_PORT', $dbConfig['port'] ?? 3306);
        $dbname = (string) Env::get('DB_NAME', $dbConfig['database'] ?? 'inventory_db');
        $user = (string) Env::get('DB_USER', $dbConfig['username'] ?? 'inventory_user');
        $pass = (string) Env::get('DB_PASS', $dbConfig['password'] ?? 'secret');

        // Fallback for host runner if running outside container
        if ($host === 'mysql' && gethostbyname('mysql') === 'mysql') {
            $host = '127.0.0.1';
        }

        $this->database = new Database(
            host: $host,
            port: $port,
            database: $dbname,
            username: $user,
            password: $pass,
            charset: 'utf8mb4'
        );

        $this->pdo = $this->database->getConnection();
    }

    protected function trackCleanup(string $table, int $id): void
    {
        $this->cleanupRecords[$table][] = $id;
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $productIds = !empty($this->cleanupRecords['products']) ? array_unique($this->cleanupRecords['products']) : [];

            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));

                // 1. Delete all stock_ledger referencing these products
                $stmt = $this->pdo->prepare("DELETE FROM stock_ledger WHERE product_id IN ({$placeholders})");
                $stmt->execute(array_values($productIds));

                // 2. Delete product_stock
                $stmt = $this->pdo->prepare("DELETE FROM product_stock WHERE product_id IN ({$placeholders})");
                $stmt->execute(array_values($productIds));

                // 3. Delete sales_order_items
                $stmt = $this->pdo->prepare("DELETE FROM sales_order_items WHERE product_id IN ({$placeholders})");
                $stmt->execute(array_values($productIds));

                // 4. Delete purchase_order_items
                $stmt = $this->pdo->prepare("DELETE FROM purchase_order_items WHERE product_id IN ({$placeholders})");
                $stmt->execute(array_values($productIds));
            }

            // 5. Delete sales_orders
            if (!empty($this->cleanupRecords['sales_orders'])) {
                $soIds = array_unique($this->cleanupRecords['sales_orders']);
                $placeholders = implode(',', array_fill(0, count($soIds), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM sales_orders WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($soIds));
            }

            // 6. Delete purchase_orders
            if (!empty($this->cleanupRecords['purchase_orders'])) {
                $poIds = array_unique($this->cleanupRecords['purchase_orders']);
                $placeholders = implode(',', array_fill(0, count($poIds), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM purchase_orders WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($poIds));
            }

            // 7. Finally delete products
            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM products WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($productIds));
            }
        }

        parent::tearDown();
    }

    /**
     * Helper to create an isolated test product in real MySQL.
     *
     * @return array{id: int, sku: string}
     */
    protected function createTestProduct(string $skuPrefix = 'INT-PRD', int $initialStock = 0, int $warehouseId = 1): array
    {
        $sku = $skuPrefix . '-' . bin2hex(random_bytes(4));
        $name = 'Test Integration Product ' . $sku;

        $stmt = $this->pdo->prepare('
            INSERT INTO products (sku, name, category_id, unit, purchase_price, selling_price, reorder_point, is_active)
            VALUES (?, ?, 1, "pcs", 100000, 150000, 10, 1)
        ');
        $stmt->execute([$sku, $name]);
        $productId = (int) $this->pdo->lastInsertId();
        $this->trackCleanup('products', $productId);

        if ($initialStock >= 0) {
            $stockStmt = $this->pdo->prepare('
                INSERT INTO product_stock (product_id, warehouse_id, quantity, version)
                VALUES (?, ?, ?, 1)
            ');
            $stockStmt->execute([$productId, $warehouseId, $initialStock]);
            $stockId = (int) $this->pdo->lastInsertId();
            $this->trackCleanup('product_stock', $stockId);
        }

        return ['id' => $productId, 'sku' => $sku];
    }
}
