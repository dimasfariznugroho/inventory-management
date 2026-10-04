<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing an inventory product (PRD-01).
 */
class Product
{
    /**
     * @param ProductStock[] $warehouseStocks
     */
    public function __construct(
        private ?int $id,
        private string $sku,
        private string $name,
        private int $categoryId,
        private ?string $categoryName = null,
        private string $unit = 'pcs',
        private float $purchasePrice = 0.0,
        private float $sellingPrice = 0.0,
        private int $reorderPoint = 0,
        private ?string $imagePath = null,
        private bool $isActive = true,
        private ?string $createdAt = null,
        private ?string $updatedAt = null,
        private int $totalStock = 0,
        private array $warehouseStocks = []
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getCategoryName(): ?string
    {
        return $this->categoryName;
    }

    public function setCategoryName(?string $categoryName): void
    {
        $this->categoryName = $categoryName;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): void
    {
        $this->unit = $unit;
    }

    public function getPurchasePrice(): float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(float $purchasePrice): void
    {
        $this->purchasePrice = $purchasePrice;
    }

    public function getSellingPrice(): float
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(float $sellingPrice): void
    {
        $this->sellingPrice = $sellingPrice;
    }

    public function getReorderPoint(): int
    {
        return $this->reorderPoint;
    }

    public function setReorderPoint(int $reorderPoint): void
    {
        $this->reorderPoint = $reorderPoint;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function getTotalStock(): int
    {
        return $this->totalStock;
    }

    public function setTotalStock(int $totalStock): void
    {
        $this->totalStock = $totalStock;
    }

    /**
     * @return ProductStock[]
     */
    public function getWarehouseStocks(): array
    {
        return $this->warehouseStocks;
    }

    /**
     * @param ProductStock[] $warehouseStocks
     */
    public function setWarehouseStocks(array $warehouseStocks): void
    {
        $this->warehouseStocks = $warehouseStocks;
    }

    public function isLowStock(): bool
    {
        return $this->totalStock <= $this->reorderPoint;
    }

    public function toArray(): array
    {
        $stocks = [];
        foreach ($this->warehouseStocks as $st) {
            $stocks[] = $st->toArray();
        }

        return [
            'id'               => $this->id,
            'sku'              => $this->sku,
            'name'             => $this->name,
            'category_id'      => $this->categoryId,
            'category_name'    => $this->categoryName,
            'unit'             => $this->unit,
            'purchase_price'   => $this->purchasePrice,
            'selling_price'    => $this->sellingPrice,
            'reorder_point'    => $this->reorderPoint,
            'image_path'       => $this->imagePath,
            'is_active'        => $this->isActive,
            'total_stock'      => $this->totalStock,
            'is_low_stock'     => $this->isLowStock(),
            'warehouse_stocks' => $stocks,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }
}
