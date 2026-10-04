<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Repository\MySQLProductRepository;
use App\Repository\MySQLPurchaseOrderRepository;
use App\Repository\MySQLStockLedgerRepository;
use App\Repository\MySQLStockRepository;
use App\Repository\MySQLSupplierRepository;
use App\Repository\MySQLWarehouseRepository;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\PurchaseOrderService;
use PDO;
use RuntimeException;

/**
 * Integration Test: Real MySQL Goods Receipt End-to-End & Transaction Atomicity (PO-01, ARCH-01).
 *
 * Verifies that:
 * 1. Physical stock in product_stock genuinely increments in MySQL after receipt, and stock_ledger is written.
 * 2. If an error occurs mid-transaction (simulated ledger failure), rollback occurs and stock remains untouched.
 */
class GoodsReceiptEndToEndTest extends IntegrationTestCase
{
    private MySQLPurchaseOrderRepository $poRepo;
    private MySQLStockRepository $stockRepo;
    private MySQLStockLedgerRepository $ledgerRepo;
    private MySQLSupplierRepository $supplierRepo;
    private MySQLWarehouseRepository $warehouseRepo;
    private MySQLProductRepository $productRepo;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poRepo = new MySQLPurchaseOrderRepository($this->database);
        $this->stockRepo = new MySQLStockRepository($this->database);
        $this->ledgerRepo = new MySQLStockLedgerRepository($this->database);
        $this->supplierRepo = new MySQLSupplierRepository($this->database);
        $this->warehouseRepo = new MySQLWarehouseRepository($this->database);
        $this->productRepo = new MySQLProductRepository($this->database);

        $this->service = new PurchaseOrderService(
            $this->database,
            $this->poRepo,
            $this->stockRepo,
            $this->ledgerRepo,
            $this->supplierRepo,
            $this->warehouseRepo,
            $this->productRepo
        );
    }

    public function test_goods_receipt_end_to_end_increases_stock_and_records_ledger_in_real_mysql(): void
    {
        // 1. Arrange: Create isolated test product with initial stock = 5 in Gudang Utama (ID: 1)
        $testProduct = $this->createTestProduct('INT-REC', initialStock: 5, warehouseId: 1);
        $productId = $testProduct['id'];

        // Create PO for 15 units
        $poNumber = 'PO-INT-TEST-' . bin2hex(random_bytes(4));
        $po = new PurchaseOrder(
            id: null,
            poNumber: $poNumber,
            supplierId: 1, // Seeded supplier: PT Maju Bersama Komputindo
            supplierName: null,
            warehouseId: 1, // Seeded warehouse: Gudang Utama Jakarta
            warehouseName: null,
            status: PurchaseOrder::STATUS_ORDERED,
            createdBy: 1,
            createdByName: null,
            orderDate: date('Y-m-d')
        );

        $item = new PurchaseOrderItem(
            id: null,
            purchaseOrderId: null,
            productId: $productId,
            productSku: $testProduct['sku'],
            productName: 'Test Product',
            productUnit: 'pcs',
            quantityOrdered: 15,
            quantityReceived: 0,
            unitPrice: 100000.0
        );

        $createdPo = $this->poRepo->create($po, [$item]);
        $poId = (int) $createdPo->getId();
        $this->trackCleanup('purchase_orders', $poId);

        $items = $createdPo->getItems();
        $this->assertNotEmpty($items);
        $itemId = (int) $items[0]->getId();
        $this->trackCleanup('purchase_order_items', $itemId);

        // 2. Act: Process Goods Receipt for 15 units
        $result = $this->service->processGoodsReceipt(
            poId: $poId,
            receivedQuantities: [$itemId => 15],
            notes: 'Penerimaan kontainer batch 1',
            userId: 4 // Seeded warehouse staff user
        );

        // 3. Assert:
        $this->assertTrue($result['success'], $result['message'] ?? 'Receipt should succeed');

        // Verify product_stock in real MySQL
        $stockStmt = $this->pdo->prepare('SELECT quantity, version FROM product_stock WHERE product_id = ? AND warehouse_id = ?');
        $stockStmt->execute([$productId, 1]);
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($stockRow);
        $this->assertSame(20, (int) $stockRow['quantity'], 'Initial 5 + Received 15 must equal 20 in MySQL.');
        $this->assertSame(2, (int) $stockRow['version'], 'Version must be incremented in MySQL.');

        // Verify stock_ledger in real MySQL
        $ledgerStmt = $this->pdo->prepare('
            SELECT * FROM stock_ledger 
            WHERE product_id = ? AND warehouse_id = ? AND reference_type = "PurchaseOrder" AND reference_id = ?
        ');
        $ledgerStmt->execute([$productId, 1, $poId]);
        $ledgerRows = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $ledgerRows);
        $this->assertSame('Receipt', $ledgerRows[0]['transaction_type']);
        $this->assertSame(15, (int) $ledgerRows[0]['quantity']);
        $this->trackCleanup('stock_ledger', (int) $ledgerRows[0]['id']);

        // Verify PO status updated to Received
        $poStmt = $this->pdo->prepare('SELECT status FROM purchase_orders WHERE id = ?');
        $poStmt->execute([$poId]);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $poStmt->fetchColumn());
    }

    public function test_transaction_rollback_maintains_atomicity_when_ledger_insert_fails(): void
    {
        // 1. Arrange: Test product with initial stock = 10
        $testProduct = $this->createTestProduct('INT-ROLL', initialStock: 10, warehouseId: 1);
        $productId = $testProduct['id'];

        $poNumber = 'PO-ROLL-' . bin2hex(random_bytes(4));
        $po = new PurchaseOrder(
            id: null,
            poNumber: $poNumber,
            supplierId: 1,
            supplierName: null,
            warehouseId: 1,
            warehouseName: null,
            status: PurchaseOrder::STATUS_ORDERED,
            createdBy: 1,
            createdByName: null,
            orderDate: date('Y-m-d')
        );

        $item = new PurchaseOrderItem(
            id: null,
            purchaseOrderId: null,
            productId: $productId,
            productSku: $testProduct['sku'],
            productName: 'Test Product Rollback',
            productUnit: 'pcs',
            quantityOrdered: 10,
            quantityReceived: 0,
            unitPrice: 100000.0
        );

        $createdPo = $this->poRepo->create($po, [$item]);
        $poId = (int) $createdPo->getId();
        $this->trackCleanup('purchase_orders', $poId);

        $items = $createdPo->getItems();
        $itemId = (int) $items[0]->getId();
        $this->trackCleanup('purchase_order_items', $itemId);

        // 2. Create Faulty StockLedgerRepository to simulate sudden database constraint error / exception
        $faultyLedgerRepo = new class implements StockLedgerRepositoryInterface {
            public function recordReceipt(
                int $productId,
                int $warehouseId,
                int $quantity,
                string $referenceType,
                int $referenceId,
                ?string $notes,
                int $createdBy
            ): int {
                throw new RuntimeException('Simulated database disk full / constraint error in StockLedger.');
            }

            public function recordIssue(int $productId, int $warehouseId, int $quantity, string $referenceType, int $referenceId, ?string $notes, int $createdBy): int { return 0; }
            public function findByReference(string $referenceType, int $referenceId): array { return []; }
            public function findAll(?int $limit = 100): array { return []; }
            public function findForReport(?string $startDate = null, ?string $endDate = null, ?string $transactionType = null, ?int $warehouseId = null): array { return []; }
        };

        $faultyService = new PurchaseOrderService(
            $this->database,
            $this->poRepo,
            $this->stockRepo,
            $faultyLedgerRepo,
            $this->supplierRepo,
            $this->warehouseRepo,
            $this->productRepo
        );

        // 3. Act: Attempt goods receipt - will fail at step 2 after stock was temporarily bumped in transaction
        $result = $faultyService->processGoodsReceipt(
            poId: $poId,
            receivedQuantities: [$itemId => 10],
            notes: 'Rollback test',
            userId: 4
        );

        // 4. Assert:
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Simulated database disk full', $result['message']);

        // VERIFY REAL MYSQL ATOMICITY:
        // Stock quantity in MySQL must STILL be exactly 10, NOT 20!
        $stockStmt = $this->pdo->prepare('SELECT quantity, version FROM product_stock WHERE product_id = ? AND warehouse_id = ?');
        $stockStmt->execute([$productId, 1]);
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(10, (int) $stockRow['quantity'], 'Stock MUST NOT be incremented if transaction rolled back.');
        $this->assertSame(1, (int) $stockRow['version'], 'Version MUST remain 1.');

        // Ledger must have 0 rows
        $ledgerStmt = $this->pdo->prepare('SELECT COUNT(*) FROM stock_ledger WHERE product_id = ?');
        $ledgerStmt->execute([$productId]);
        $this->assertSame(0, (int) $ledgerStmt->fetchColumn());

        // PO status must remain Ordered
        $poStmt = $this->pdo->prepare('SELECT status FROM purchase_orders WHERE id = ?');
        $poStmt->execute([$poId]);
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $poStmt->fetchColumn());
    }
}
