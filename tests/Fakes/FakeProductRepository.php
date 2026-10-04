<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\Product;
use App\Repository\ProductRepositoryInterface;

/**
 * Pure in-memory Fake for ProductRepositoryInterface.
 */
class FakeProductRepository implements ProductRepositoryInterface
{
    /** @var array<int, Product> */
    private array $products = [];

    private int $nextId = 1;

    public function addProduct(Product $product): void
    {
        $id = $product->getId() ?? $this->nextId++;
        $product->setId($id);
        $this->products[$id] = $product;
    }

    public function findAllWithStock(
        ?string $search = null,
        ?int $categoryId = null,
        ?string $stockStatus = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        return array_values($this->products);
    }

    public function countAllWithStock(?string $search = null, ?int $categoryId = null, ?string $stockStatus = null): int
    {
        return count($this->products);
    }

    public function findByIdWithWarehouseBreakdown(int $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findBySkuWithWarehouseBreakdown(string $sku): ?Product
    {
        foreach ($this->products as $product) {
            if ($product->getSku() === $sku) {
                return $product;
            }
        }
        return null;
    }

    public function findById(int $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function skuExists(string $sku, ?int $excludeProductId = null): bool
    {
        foreach ($this->products as $product) {
            if ($excludeProductId !== null && $product->getId() === $excludeProductId) {
                continue;
            }
            if ($product->getSku() === $sku) {
                return true;
            }
        }
        return false;
    }

    public function isUsedInOrders(int $productId): bool
    {
        return false;
    }

    public function save(Product $product): Product
    {
        $this->addProduct($product);
        return $product;
    }

    public function delete(int $id): bool
    {
        if (isset($this->products[$id])) {
            unset($this->products[$id]);
            return true;
        }
        return false;
    }

    public function getLowStockCount(): int
    {
        $count = 0;
        foreach ($this->products as $p) {
            if ($p->isLowStock()) {
                $count++;
            }
        }
        return $count;
    }

    public function getTotalInventoryValuation(): float
    {
        $val = 0.0;
        foreach ($this->products as $p) {
            $val += $p->getTotalStock() * $p->getPurchasePrice();
        }
        return $val;
    }

    public function getLowStockProducts(int $limit = 10): array
    {
        $low = [];
        foreach ($this->products as $p) {
            if ($p->isLowStock()) {
                $low[] = $p;
            }
        }
        return array_slice($low, 0, $limit);
    }
}
