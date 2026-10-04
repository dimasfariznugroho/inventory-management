<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing product inventory in a specific warehouse (WH-01).
 * Includes version column for future optimistic concurrency checks (ARCH-02).
 */
class ProductStock
{
    public function __construct(
        private ?int $id,
        private int $productId,
        private int $warehouseId,
        private string $warehouseName,
        private int $quantity = 0,
        private int $version = 1,
        private ?string $updatedAt = null,
        private ?string $warehouseLocation = null
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getWarehouseName(): string
    {
        return $this->warehouseName;
    }

    public function getWarehouseLocation(): ?string
    {
        return $this->warehouseLocation;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getReservedQuantity(): int
    {
        return 0; // Reserved quantity will be implemented with Sales Orders in Phase 4
    }

    public function getAvailableQuantity(): int
    {
        return max(0, $this->quantity - $this->getReservedQuantity());
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->productId,
            'warehouse_id'       => $this->warehouseId,
            'warehouse_name'     => $this->warehouseName,
            'warehouse_location' => $this->warehouseLocation,
            'quantity'           => $this->quantity,
            'reserved_quantity'  => $this->getReservedQuantity(),
            'available_quantity' => $this->getAvailableQuantity(),
            'version'            => $this->version,
            'updated_at'         => $this->updatedAt,
        ];
    }
}
