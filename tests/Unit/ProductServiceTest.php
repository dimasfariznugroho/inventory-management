<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStock;
use App\Service\ProductService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeProductRepository;

/**
 * Isolated Unit Tests for Product & Inventory Valuation / Low-Stock calculation (PRD-01, WH-01).
 * Tests critical business rule: total_stock <= reorder_point is low stock.
 */
class ProductServiceTest extends TestCase
{
    private FakeProductRepository $productRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepo = new FakeProductRepository();
    }

    public function test_product_entity_low_stock_boundary_evaluations(): void
    {
        // 1. Below reorder point (totalStock < reorderPoint) -> low stock
        $productBelow = new Product(
            id: 1,
            sku: 'SKU-001',
            name: 'Item A',
            categoryId: 1,
            reorderPoint: 10,
            totalStock: 9
        );
        $this->assertTrue($productBelow->isLowStock(), 'Stock strictly less than reorder point must be low stock.');

        // 2. CRITICAL BOUNDARY: Exactly at reorder point (totalStock == reorderPoint) -> MUST be low stock (<=)
        $productEqual = new Product(
            id: 2,
            sku: 'SKU-002',
            name: 'Item B',
            categoryId: 1,
            reorderPoint: 10,
            totalStock: 10
        );
        $this->assertTrue($productEqual->isLowStock(), 'Stock exactly equal to reorder point must be low stock (boundary condition <=).');

        // 3. Above reorder point (totalStock > reorderPoint) -> normal stock
        $productAbove = new Product(
            id: 3,
            sku: 'SKU-003',
            name: 'Item C',
            categoryId: 1,
            reorderPoint: 10,
            totalStock: 11
        );
        $this->assertFalse($productAbove->isLowStock(), 'Stock strictly greater than reorder point must NOT be low stock.');
    }

    public function test_product_service_evaluates_multi_warehouse_availability_and_low_stock_status(): void
    {
        // Create mock category repo for ProductService
        $categoryRepo = new class implements \App\Repository\CategoryRepositoryInterface {
            public function findAll(): array { return []; }
            public function findById(int $id): ?Category { return null; }
            public function save(Category $cat): Category { return $cat; }
            public function delete(int $id): bool { return true; }
            public function nameExists(string $name, ?int $excludeId = null): bool { return false; }
            public function isUsedByProducts(int $id): bool { return false; }
        };

        $service = new ProductService($this->productRepo, $categoryRepo);

        // Product with reorder_point = 15, stock in Gudang A = 8, stock in Gudang B = 7 (Total = 15)
        $stocks = [
            new ProductStock(id: 1, productId: 10, warehouseId: 1, warehouseName: 'Gudang A', quantity: 8, version: 1),
            new ProductStock(id: 2, productId: 10, warehouseId: 2, warehouseName: 'Gudang B', quantity: 7, version: 1),
        ];

        $product = new Product(
            id: 10,
            sku: 'SKU-MULTI-01',
            name: 'Komponen Mesin',
            categoryId: 1,
            unit: 'pcs',
            reorderPoint: 15,
            totalStock: 15,
            warehouseStocks: $stocks
        );

        $this->productRepo->addProduct($product);

        $availability = $service->getProductAvailabilityBySku('SKU-MULTI-01');

        $this->assertNotNull($availability);
        $this->assertSame(15, $availability['total_stock']);
        $this->assertSame(15, $availability['reorder_point']);
        $this->assertSame('low_stock', $availability['stock_status']);
        $this->assertCount(2, $availability['warehouses']);
    }
}
