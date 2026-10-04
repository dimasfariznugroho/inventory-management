<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Service\PurchaseOrderService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDatabase;
use Tests\Fakes\FakeProductRepository;
use Tests\Fakes\FakePurchaseOrderRepository;
use Tests\Fakes\FakeStockLedgerRepository;
use Tests\Fakes\FakeStockRepository;
use Tests\Fakes\FakeSupplierRepository;
use Tests\Fakes\FakeWarehouseRepository;

/**
 * Isolated Unit Tests for PurchaseOrderService (PO-01).
 * Tests business rules for order creation, date/quantity validations, and cancellation rules.
 */
class PurchaseOrderServiceTest extends TestCase
{
    private FakeDatabase $database;
    private FakePurchaseOrderRepository $poRepo;
    private FakeStockRepository $stockRepo;
    private FakeStockLedgerRepository $ledgerRepo;
    private FakeSupplierRepository $supplierRepo;
    private FakeWarehouseRepository $warehouseRepo;
    private FakeProductRepository $productRepo;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new FakeDatabase();
        $this->poRepo = new FakePurchaseOrderRepository();
        $this->stockRepo = new FakeStockRepository();
        $this->ledgerRepo = new FakeStockLedgerRepository();
        $this->supplierRepo = new FakeSupplierRepository();
        $this->warehouseRepo = new FakeWarehouseRepository();
        $this->productRepo = new FakeProductRepository();

        $this->supplierRepo->addSupplier(new Supplier(id: 1, name: 'PT Distributor Utama', contact: '021-12345', address: 'Jakarta', isActive: true));
        $this->warehouseRepo->addWarehouse(new Warehouse(id: 1, name: 'Gudang Pusat', location: 'Jakarta', isActive: true));
        $this->productRepo->addProduct(new Product(id: 1, sku: 'PRD-RAW-01', name: 'Bahan Baku A', categoryId: 1));

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

    public function test_create_purchase_order_validation_rejects_missing_supplier_and_empty_items(): void
    {
        $result = $this->service->createPurchaseOrder([
            'supplier_id'  => 999, // nonexistent
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [],
        ], userId: 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('supplier_id', $result['errors']);
        $this->assertArrayHasKey('items', $result['errors']);
    }

    public function test_create_purchase_order_rejects_zero_or_negative_quantity_and_negative_price(): void
    {
        $result = $this->service->createPurchaseOrder([
            'supplier_id'  => 1,
            'warehouse_id' => 1,
            'order_date'   => '2026-09-25',
            'items'        => [
                ['product_id' => 1, 'quantity' => 0, 'unit_price' => 50000.0],
                ['product_id' => 1, 'quantity' => 10, 'unit_price' => -1000.0],
            ],
        ], userId: 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('item_0_quantity', $result['errors']);
        $this->assertArrayHasKey('item_1_price', $result['errors']);
    }

    public function test_purchase_order_cannot_be_cancelled_if_already_partially_received(): void
    {
        $order = new PurchaseOrder(
            id: null,
            poNumber: 'PO-20260925-001',
            supplierId: 1,
            supplierName: 'PT Distributor Utama',
            warehouseId: 1,
            warehouseName: 'Gudang Pusat',
            status: PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            createdBy: 1,
            createdByName: 'Admin',
            orderDate: '2026-09-25'
        );

        $saved = $this->poRepo->create($order, []);
        $poId = $saved->getId();
        $this->assertNotNull($poId);

        $cancelResult = $this->service->cancelPurchaseOrder($poId, userId: 1);

        $this->assertFalse($cancelResult['success']);
        $this->assertStringContainsString('tidak dapat dibatalkan karena barang sudah pernah diterima sebagian', $cancelResult['message']);
    }
}
