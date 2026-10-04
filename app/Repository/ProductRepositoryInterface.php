<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

/**
 * Interface contract for Product and Multi-Warehouse Stock data access (PRD-01, WH-01).
 */
interface ProductRepositoryInterface
{
    /**
     * @return Product[]
     */
    public function findAllWithStock(
        ?string $search = null,
        ?int $categoryId = null,
        ?string $stockStatus = null,
        int $page = 1,
        int $perPage = 10
    ): array;

    public function countAllWithStock(?string $search = null, ?int $categoryId = null, ?string $stockStatus = null): int;

    /**
     * Retrieve single product with total aggregated stock and per-warehouse breakdown (WH-01, API-01).
     */
    public function findByIdWithWarehouseBreakdown(int $id): ?Product;

    public function findBySkuWithWarehouseBreakdown(string $sku): ?Product;

    public function findById(int $id): ?Product;

    public function skuExists(string $sku, ?int $excludeProductId = null): bool;

    /**
     * Check if product is referenced in PO or SO items (protecting from permanent delete).
     */
    public function isUsedInOrders(int $productId): bool;

    public function save(Product $product): Product;

    public function delete(int $id): bool;

    public function getLowStockCount(): int;

    public function getTotalInventoryValuation(): float;

    public function getLowStockProducts(int $limit = 10): array;
}
