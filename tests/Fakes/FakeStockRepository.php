<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\ProductStock;
use App\Repository\StockRepositoryInterface;

/**
 * Pure in-memory Fake for StockRepositoryInterface with ARCH-02 Optimistic Lock simulation.
 */
class FakeStockRepository implements StockRepositoryInterface
{
    /** @var array<string, ProductStock> key format: "{productId}:{warehouseId}" */
    private array $stocks = [];

    /** If set to true, decreaseStockOptimistic will simulate rowCount = 0 */
    private bool $simulateConflict = false;

    public function setSimulateConflict(bool $simulate): void
    {
        $this->simulateConflict = $simulate;
    }

    public function setStock(int $productId, int $warehouseId, int $quantity, int $version = 1, string $warehouseName = 'Gudang Utama'): void
    {
        $key = "{$productId}:{$warehouseId}";
        $this->stocks[$key] = new ProductStock(
            id: 1,
            productId: $productId,
            warehouseId: $warehouseId,
            warehouseName: $warehouseName,
            quantity: $quantity,
            version: $version
        );
    }

    public function increaseStock(int $productId, int $warehouseId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $key = "{$productId}:{$warehouseId}";
        if (isset($this->stocks[$key])) {
            $existing = $this->stocks[$key];
            $existing->setQuantity($existing->getQuantity() + $quantity);
            $existing->setVersion($existing->getVersion() + 1);
        } else {
            $this->stocks[$key] = new ProductStock(
                id: count($this->stocks) + 1,
                productId: $productId,
                warehouseId: $warehouseId,
                warehouseName: 'Gudang Utama',
                quantity: $quantity,
                version: 1
            );
        }

        return true;
    }

    public function getStock(int $productId, int $warehouseId): ?ProductStock
    {
        $key = "{$productId}:{$warehouseId}";
        if (!isset($this->stocks[$key])) {
            return null;
        }

        // Return a clone so internal mutations mirror separate database reads
        $st = $this->stocks[$key];
        return new ProductStock(
            id: $st->getId(),
            productId: $st->getProductId(),
            warehouseId: $st->getWarehouseId(),
            warehouseName: $st->getWarehouseName(),
            quantity: $st->getQuantity(),
            version: $st->getVersion()
        );
    }

    public function decreaseStockOptimistic(int $productId, int $warehouseId, int $quantity, int $expectedVersion): bool
    {
        if ($this->simulateConflict) {
            // Simulates rowCount() === 0 due to concurrent update
            return false;
        }

        if ($quantity <= 0) {
            return false;
        }

        $key = "{$productId}:{$warehouseId}";
        if (!isset($this->stocks[$key])) {
            return false;
        }

        $stock = $this->stocks[$key];

        // SQL WHERE version = :version AND quantity >= :quantity_check
        if ($stock->getVersion() !== $expectedVersion || $stock->getQuantity() < $quantity) {
            return false; // rowCount() === 0 in MySQL
        }

        $stock->setQuantity($stock->getQuantity() - $quantity);
        $stock->setVersion($stock->getVersion() + 1);

        return true; // rowCount() === 1
    }
}
