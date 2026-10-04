<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;

/**
 * Interface contract for Product Stock physical inventory updates.
 */
interface StockRepositoryInterface
{
    /**
     * Atomically increase stock for a product in a warehouse.
     * Inserts new row if combination does not exist, or updates quantity if it exists.
     */
    public function increaseStock(int $productId, int $warehouseId, int $quantity): bool;

    public function getStock(int $productId, int $warehouseId): ?ProductStock;

    /**
     * Atomically decrease stock using Optimistic Locking (ARCH-02).
     * Enforces version = expectedVersion AND quantity >= quantity to prevent oversell and lost updates.
     * Returns true if 1 row was updated, false if concurrency conflict or insufficient stock.
     */
    public function decreaseStockOptimistic(int $productId, int $warehouseId, int $quantity, int $expectedVersion): bool;
}

