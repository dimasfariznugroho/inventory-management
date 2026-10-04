<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\SalesOrderService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCustomerRepository;
use Tests\Fakes\FakeDatabase;
use Tests\Fakes\FakeProductRepository;
use Tests\Fakes\FakeSalesOrderRepository;
use Tests\Fakes\FakeStockLedgerRepository;
use Tests\Fakes\FakeStockRepository;
use Tests\Fakes\FakeWarehouseRepository;

/**
 * Isolated Unit Tests for SalesOrderService:
 * - Status transition lifecycle (Draft -> PendingApproval -> Approved -> Fulfilled & illegal transitions)
 * - Segregation of Duties (Sales & Warehouse staff forbidden from approving)
 * - ARCH-02 Optimistic Locking Concurrency Conflict detection via rowCount() = 0 simulation
 *
 * Runs 100% in-memory with zero MySQL/network dependencies.
 */
class SalesOrderServiceTest extends TestCase
{
    private FakeDatabase $database;
    private FakeSalesOrderRepository $soRepo;
    private FakeStockRepository $stockRepo;
    private FakeStockLedgerRepository $ledgerRepo;
    private FakeCustomerRepository $customerRepo;
    private FakeWarehouseRepository $warehouseRepo;
    private FakeProductRepository $productRepo;
    private SalesOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new FakeDatabase();
        $this->soRepo = new FakeSalesOrderRepository();
        $this->stockRepo = new FakeStockRepository();
        $this->ledgerRepo = new FakeStockLedgerRepository();
        $this->customerRepo = new FakeCustomerRepository();
        $this->warehouseRepo = new FakeWarehouseRepository();
        $this->productRepo = new FakeProductRepository();

        // Seed basic in-memory master data
        $this->customerRepo->addCustomer(new Customer(id: 1, name: 'PT Maju Bersama', contact: '08123456789', address: 'Jakarta', isActive: true));
        $this->warehouseRepo->addWarehouse(new Warehouse(id: 1, name: 'Gudang Pusat', location: 'Jakarta Barat', isActive: true));
        $this->productRepo->addProduct(new Product(
            id: 1,
            sku: 'PRD-LAPTOP-01',
            name: 'Laptop Bisnis Pro',
            categoryId: 1,
            unit: 'unit',
            purchasePrice: 10000000.0,
            sellingPrice: 12500000.0,
            reorderPoint: 5,
            isActive: true
        ));

        // Initial stock: 20 units, version 1
        $this->stockRepo->setStock(productId: 1, warehouseId: 1, quantity: 20, version: 1, warehouseName: 'Gudang Pusat');

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

    // =========================================================================
    // Area A: Sales Order Status Transitions & State Machine
    // =========================================================================

    public function test_sales_order_full_valid_lifecycle_transitions_successfully(): void
    {
        // 1. Create order in Draft
        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 5, 'unit_price' => 12500000.0],
            ],
        ], userId: 2);

        $this->assertTrue($createResult['success']);
        /** @var SalesOrder $so */
        $so = $createResult['so'];
        $soId = $so->getId();
        $this->assertNotNull($soId);
        $this->assertSame(SalesOrder::STATUS_DRAFT, $so->getStatus());

        // 2. Draft -> PendingApproval
        $submitResult = $this->service->submitForApproval($soId, userId: 2);
        $this->assertTrue($submitResult['success']);
        $submittedSo = $this->soRepo->findById($soId);
        $this->assertNotNull($submittedSo);
        $this->assertSame(SalesOrder::STATUS_PENDING_APPROVAL, $submittedSo->getStatus());

        // 3. PendingApproval -> Approved (By Admin)
        $approveResult = $this->service->approveSalesOrder($soId, adminUserId: 1, userRole: User::ROLE_ADMIN);
        $this->assertTrue($approveResult['success']);
        $approvedSo = $this->soRepo->findById($soId);
        $this->assertNotNull($approvedSo);
        $this->assertSame(SalesOrder::STATUS_APPROVED, $approvedSo->getStatus());
        $this->assertSame(1, $approvedSo->getApprovedBy());

        // 4. Approved -> Fulfilled (Goods Issue full quantity)
        $items = $approvedSo->getItems();
        $itemId = $items[0]->getId();
        $this->assertNotNull($itemId);

        $issueResult = $this->service->processGoodsIssue(
            soId: $soId,
            issuedQuantities: [$itemId => 5],
            notes: 'Pengiriman kurir ekspres',
            userId: 3
        );

        $this->assertTrue($issueResult['success'], $issueResult['message'] ?? '');
        $fulfilledSo = $this->soRepo->findById($soId);
        $this->assertNotNull($fulfilledSo);
        $this->assertSame(SalesOrder::STATUS_FULFILLED, $fulfilledSo->getStatus());
        $this->assertTrue($fulfilledSo->isFullyFulfilled());
        $this->assertSame(1, $this->database->getCommitCount());
    }

    public function test_invalid_status_transition_direct_draft_to_fulfilled_is_rejected(): void
    {
        // Create an SO in Draft status
        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 2],
            ],
        ], userId: 2);

        $soId = $createResult['so']->getId();
        $this->assertNotNull($soId);

        // Attempt direct goods issue while still in Draft
        $issueResult = $this->service->processGoodsIssue(
            soId: $soId,
            issuedQuantities: [1 => 2],
            notes: 'Bypass attempt',
            userId: 2
        );

        $this->assertFalse($issueResult['success']);
        $this->assertStringContainsString("SO harus berstatus 'Approved'", $issueResult['message']);
        $this->assertSame(1, $this->database->getRollbackCount());

        // Verify order is still Draft
        $fresh = $this->soRepo->findById($soId);
        $this->assertNotNull($fresh);
        $this->assertSame(SalesOrder::STATUS_DRAFT, $fresh->getStatus());
    }

    public function test_cancel_fulfilled_sales_order_is_rejected(): void
    {
        // Setup fulfilled order
        $order = new SalesOrder(
            id: null,
            soNumber: 'SO-TEST-FULFILLED',
            customerId: 1,
            customerName: 'PT Maju Bersama',
            warehouseId: 1,
            warehouseName: 'Gudang Pusat',
            status: SalesOrder::STATUS_FULFILLED,
            createdBy: 2,
            createdByName: 'Sales User',
            approvedBy: 1,
            approvedByName: 'Admin',
            orderDate: '2026-09-25'
        );
        $saved = $this->soRepo->create($order, []);
        $soId = $saved->getId();
        $this->assertNotNull($soId);

        $cancelResult = $this->service->cancelSalesOrder($soId, userId: 1);

        $this->assertFalse($cancelResult['success']);
        $this->assertStringContainsString("tidak dapat dibatalkan karena statusnya sudah 'Fulfilled'", $cancelResult['message']);
    }

    // =========================================================================
    // Area B: Segregation of Duties (SoD)
    // =========================================================================

    public function test_sales_role_cannot_approve_sales_order_including_own_order(): void
    {
        // 1. Create order by Sales User (ID: 2)
        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ], userId: 2);

        $soId = $createResult['so']->getId();
        $this->assertNotNull($soId);
        $this->service->submitForApproval($soId, userId: 2);

        // 2. Sales User attempts to approve their own order
        $approveResult = $this->service->approveSalesOrder($soId, adminUserId: 2, userRole: User::ROLE_SALES);

        $this->assertFalse($approveResult['success']);
        $this->assertStringContainsString('Segregation of Duties', $approveResult['message']);
        $this->assertStringContainsString('Hanya Administrator yang berwenang', $approveResult['message']);

        // Verify status remains PendingApproval
        $so = $this->soRepo->findById($soId);
        $this->assertNotNull($so);
        $this->assertSame(SalesOrder::STATUS_PENDING_APPROVAL, $so->getStatus());
        $this->assertNull($so->getApprovedBy());
    }

    public function test_warehouse_role_cannot_approve_sales_order(): void
    {
        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ], userId: 2);

        $soId = $createResult['so']->getId();
        $this->assertNotNull($soId);
        $this->service->submitForApproval($soId, userId: 2);

        // Warehouse staff (ID: 3) attempts to approve
        $approveResult = $this->service->approveSalesOrder($soId, adminUserId: 3, userRole: User::ROLE_WAREHOUSE_STAFF);

        $this->assertFalse($approveResult['success']);
        $this->assertStringContainsString('Segregation of Duties', $approveResult['message']);
    }

    public function test_cannot_approve_sales_order_when_not_in_pending_approval_status(): void
    {
        // Order is still in Draft (not yet submitted for approval)
        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ], userId: 2);

        $soId = $createResult['so']->getId();
        $this->assertNotNull($soId);

        // Admin attempts to approve Draft directly
        $approveResult = $this->service->approveSalesOrder($soId, adminUserId: 1, userRole: User::ROLE_ADMIN);

        $this->assertFalse($approveResult['success']);
        $this->assertStringContainsString('hanya status PendingApproval yang dapat disetujui', $approveResult['message']);
    }

    // =========================================================================
    // Area C: ARCH-02 Optimistic Locking Concurrency Conflict (Simulated rowCount=0)
    // =========================================================================

    public function test_arch02_optimistic_locking_detects_concurrency_conflict_when_row_count_zero(): void
    {
        // 1. Setup Approved SO
        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 5],
            ],
        ], userId: 2);

        $soId = $createResult['so']->getId();
        $this->assertNotNull($soId);
        $this->service->submitForApproval($soId, userId: 2);
        $this->service->approveSalesOrder($soId, adminUserId: 1, userRole: User::ROLE_ADMIN);

        $approvedSo = $this->soRepo->findById($soId);
        $this->assertNotNull($approvedSo);
        $itemId = $approvedSo->getItems()[0]->getId();
        $this->assertNotNull($itemId);

        // 2. CONCURRENCY SIMULATION:
        // Instruct FakeStockRepository to return false on decreaseStockOptimistic
        // (simulating that another concurrent process updated version column, making MySQL WHERE version = 1 return 0 affected rows)
        $this->stockRepo->setSimulateConflict(true);

        // 3. Process Goods Issue
        $issueResult = $this->service->processGoodsIssue(
            soId: $soId,
            issuedQuantities: [$itemId => 5],
            notes: 'Attempt during concurrent race condition',
            userId: 3
        );

        // 4. Assertions:
        // Must fail with explicit optimistic lock conflict message
        $this->assertFalse($issueResult['success']);
        $this->assertStringContainsString('Konflik konkurensi terdeteksi (Optimistic Lock)', $issueResult['message']);
        $this->assertStringContainsString('mencegah oversell / stok negatif', $issueResult['message']);

        // Transaction must have rolled back
        $this->assertSame(1, $this->database->getRollbackCount());

        // Stock in warehouse must NOT have decreased (must still be 20)
        $stock = $this->stockRepo->getStock(1, 1);
        $this->assertNotNull($stock);
        $this->assertSame(20, $stock->getQuantity());

        // Stock Ledger must NOT have recorded any issue entries
        $this->assertCount(0, $this->ledgerRepo->getAllRecords());

        // SO status must still be Approved, NOT Fulfilled
        $currentSo = $this->soRepo->findById($soId);
        $this->assertNotNull($currentSo);
        $this->assertSame(SalesOrder::STATUS_APPROVED, $currentSo->getStatus());
        $this->assertSame(0, $currentSo->getItems()[0]->getQuantityFulfilled());
    }

    public function test_goods_issue_fails_and_rolls_back_when_requested_qty_exceeds_available_stock(): void
    {
        // Set physical stock to only 3 units
        $this->stockRepo->setStock(productId: 1, warehouseId: 1, quantity: 3, version: 1);

        $createResult = $this->service->createSalesOrder([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 10],
            ],
        ], userId: 2);

        $soId = $createResult['so']->getId();
        $this->assertNotNull($soId);
        $this->service->submitForApproval($soId, userId: 2);
        $this->service->approveSalesOrder($soId, adminUserId: 1, userRole: User::ROLE_ADMIN);

        $approvedSo = $this->soRepo->findById($soId);
        $this->assertNotNull($approvedSo);
        $itemId = $approvedSo->getItems()[0]->getId();
        $this->assertNotNull($itemId);

        // Attempt to issue 5 units when only 3 are available in stock
        $issueResult = $this->service->processGoodsIssue(
            soId: $soId,
            issuedQuantities: [$itemId => 5],
            notes: 'Over-issue attempt',
            userId: 3
        );

        $this->assertFalse($issueResult['success']);
        $this->assertStringContainsString('Stok fisik tidak mencukupi', $issueResult['message']);
        $this->assertStringContainsString('Tersedia: 3', $issueResult['message']);
        $this->assertSame(1, $this->database->getRollbackCount());
    }
}
