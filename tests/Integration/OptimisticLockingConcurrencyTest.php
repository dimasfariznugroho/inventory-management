<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\User;
use App\Repository\MySQLCustomerRepository;
use App\Repository\MySQLProductRepository;
use App\Repository\MySQLSalesOrderRepository;
use App\Repository\MySQLStockLedgerRepository;
use App\Repository\MySQLStockRepository;
use App\Repository\MySQLWarehouseRepository;
use App\Service\SalesOrderService;
use PDO;

/**
 * Integration Test: Real MySQL ARCH-02 Optimistic Locking Concurrency Control.
 *
 * Demonstrates on the genuine Docker MySQL 8.0 instance:
 * 1. Sequential / concurrent depletion: Goods Issue 1 depletes stock; Goods Issue 2 is rejected.
 * 2. Underlying SQL engine behavior: UPDATE ... WHERE version = expectedVersion returns rowCount() = 0 on conflict.
 */
class OptimisticLockingConcurrencyTest extends IntegrationTestCase
{
    private MySQLSalesOrderRepository $soRepo;
    private MySQLStockRepository $stockRepo;
    private MySQLStockLedgerRepository $ledgerRepo;
    private MySQLCustomerRepository $customerRepo;
    private MySQLWarehouseRepository $warehouseRepo;
    private MySQLProductRepository $productRepo;
    private SalesOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soRepo = new MySQLSalesOrderRepository($this->database);
        $this->stockRepo = new MySQLStockRepository($this->database);
        $this->ledgerRepo = new MySQLStockLedgerRepository($this->database);
        $this->customerRepo = new MySQLCustomerRepository($this->database);
        $this->warehouseRepo = new MySQLWarehouseRepository($this->database);
        $this->productRepo = new MySQLProductRepository($this->database);

        $this->service = new SalesOrderService(
            $this->database,
            $this->soRepo,
            $this->stockRepo,
            $this->ledgerRepo,
            $this->customerRepo,
            $this->warehouseRepo,
            $this->productRepo
        );
    }

    public function test_second_goods_issue_rejected_when_stock_depleted_by_first_issue_in_real_mysql(): void
    {
        // 1. Arrange: Create product with exactly 5 units in stock, version = 1 in Warehouse 1
        $testProduct = $this->createTestProduct('INT-SO', initialStock: 5, warehouseId: 1);
        $productId = $testProduct['id'];

        // Create Sales Order 1 (Orders 5 units)
        $so1 = $this->createApprovedSalesOrder($productId, 5, 'SO-CONCUR-1-');
        $soId1 = (int) $so1['id'];
        $itemId1 = (int) $so1['item_id'];

        // Create Sales Order 2 (Also orders 5 units)
        $so2 = $this->createApprovedSalesOrder($productId, 5, 'SO-CONCUR-2-');
        $soId2 = (int) $so2['id'];
        $itemId2 = (int) $so2['item_id'];

        // 2. Act 1: Process Goods Issue for SO 1 (Depletes all 5 units)
        $result1 = $this->service->processGoodsIssue(
            soId: $soId1,
            issuedQuantities: [$itemId1 => 5],
            notes: 'Goods Issue SO 1',
            userId: 4 // Warehouse staff
        );

        $this->assertTrue($result1['success'], 'First issue must succeed.');

        // Verify MySQL stock after Issue 1: quantity = 0, version = 2
        $stockStmt = $this->pdo->prepare('SELECT quantity, version FROM product_stock WHERE product_id = ? AND warehouse_id = ?');
        $stockStmt->execute([$productId, 1]);
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $stockRow['quantity']);
        $this->assertSame(2, (int) $stockRow['version']);

        // SO 1 status in MySQL must be Fulfilled
        $soStmt1 = $this->pdo->prepare('SELECT status FROM sales_orders WHERE id = ?');
        $soStmt1->execute([$soId1]);
        $this->assertSame(SalesOrder::STATUS_FULFILLED, $soStmt1->fetchColumn());

        // 3. Act 2: Process Goods Issue for SO 2 (Tries to issue 5 units from depleted stock)
        $result2 = $this->service->processGoodsIssue(
            soId: $soId2,
            issuedQuantities: [$itemId2 => 5],
            notes: 'Goods Issue SO 2 (Oversell attempt)',
            userId: 4
        );

        // 4. Assert: SO 2 MUST be rejected by ARCH-02 / inventory guard
        $this->assertFalse($result2['success'], 'Second issue must be rejected to prevent negative inventory.');
        $this->assertThat(
            $result2['message'],
            $this->logicalOr(
                $this->stringContains('Stok fisik tidak mencukupi'),
                $this->stringContains('Konflik konkurensi terdeteksi (Optimistic Lock)')
            )
        );

        // Stock in real MySQL must STILL be 0, NEVER negative (-5)
        $stockStmt->execute([$productId, 1]);
        $finalStockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $finalStockRow['quantity'], 'Stock must remain 0 and never drop below zero.');

        // SO 2 status must remain Approved, NOT Fulfilled
        $soStmt2 = $this->pdo->prepare('SELECT status FROM sales_orders WHERE id = ?');
        $soStmt2->execute([$soId2]);
        $this->assertSame(SalesOrder::STATUS_APPROVED, $soStmt2->fetchColumn());

        // Stock ledger must have ONLY ONE Issue entry (from SO 1), none from SO 2
        $ledgerStmt = $this->pdo->prepare('SELECT COUNT(*) FROM stock_ledger WHERE product_id = ? AND transaction_type = "Issue"');
        $ledgerStmt->execute([$productId]);
        $this->assertSame(1, (int) $ledgerStmt->fetchColumn());
    }

    public function test_mysql_stock_repository_optimistic_update_returns_zero_affected_rows_on_stale_version(): void
    {
        // 1. Arrange: Test product with stock = 10, version = 1
        $testProduct = $this->createTestProduct('INT-OPT', initialStock: 10, warehouseId: 1);
        $productId = $testProduct['id'];

        // 2. Transaction A executes update with expectedVersion = 1 -> SUCCESS (rowCount = 1)
        $successA = $this->stockRepo->decreaseStockOptimistic(
            productId: $productId,
            warehouseId: 1,
            quantity: 3,
            expectedVersion: 1
        );
        $this->assertTrue($successA, 'First decrement with current version must return true.');

        // In MySQL: quantity is now 7, version is now 2
        $stock = $this->stockRepo->getStock($productId, 1);
        $this->assertNotNull($stock);
        $this->assertSame(7, $stock->getQuantity());
        $this->assertSame(2, $stock->getVersion());

        // 3. Transaction B attempts update using STALE expectedVersion = 1 -> MUST FAIL (rowCount = 0)
        // Because MySQL WHERE version = 1 no longer matches (current version is 2)!
        $successB = $this->stockRepo->decreaseStockOptimistic(
            productId: $productId,
            warehouseId: 1,
            quantity: 3,
            expectedVersion: 1 // Stale!
        );

        $this->assertFalse($successB, 'ARCH-02: Stale expectedVersion=1 must return false (rowCount = 0 in MySQL).');

        // Stock in MySQL must remain untouched by Transaction B
        $finalStock = $this->stockRepo->getStock($productId, 1);
        $this->assertNotNull($finalStock);
        $this->assertSame(7, $finalStock->getQuantity());
        $this->assertSame(2, $finalStock->getVersion());
    }

    /**
     * Helper to create and approve an SO in real MySQL.
     *
     * @return array{id: int, item_id: int}
     */
    private function createApprovedSalesOrder(int $productId, int $qty, string $prefix): array
    {
        $soNumber = $prefix . bin2hex(random_bytes(4));
        $so = new SalesOrder(
            id: null,
            soNumber: $soNumber,
            customerId: 1, // Seeded customer: PT Retail Nusantara Megah
            customerName: null,
            warehouseId: 1, // Gudang Utama Jakarta
            warehouseName: null,
            status: SalesOrder::STATUS_APPROVED, // Pre-approved for issue testing
            createdBy: 2, // Sales user
            createdByName: null,
            approvedBy: 1, // Admin user
            approvedByName: null,
            orderDate: date('Y-m-d')
        );

        $item = new SalesOrderItem(
            id: null,
            salesOrderId: null,
            productId: $productId,
            productSku: 'SKU-' . $productId,
            productName: 'Product ' . $productId,
            productUnit: 'pcs',
            quantityOrdered: $qty,
            quantityFulfilled: 0,
            unitPrice: 150000.0
        );

        $createdSo = $this->soRepo->create($so, [$item]);
        $soId = (int) $createdSo->getId();
        $this->trackCleanup('sales_orders', $soId);

        $items = $createdSo->getItems();
        $itemId = (int) $items[0]->getId();
        $this->trackCleanup('sales_order_items', $itemId);

        // Ensure status is Approved
        $this->soRepo->updateStatus($soId, SalesOrder::STATUS_APPROVED, 1);

        return ['id' => $soId, 'item_id' => $itemId];
    }
}
