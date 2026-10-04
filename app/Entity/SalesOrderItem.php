<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing an item line in a Sales Order (SO-01).
 */
class SalesOrderItem
{
    public function __construct(
        private ?int $id,
        private ?int $salesOrderId,
        private int $productId,
        private ?string $productSku,
        private ?string $productName,
        private ?string $productUnit,
        private int $quantityOrdered,
        private int $quantityFulfilled = 0,
        private float $unitPrice = 0.0
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

    public function getSalesOrderId(): ?int
    {
        return $this->salesOrderId;
    }

    public function setSalesOrderId(int $salesOrderId): void
    {
        $this->salesOrderId = $salesOrderId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getProductSku(): ?string
    {
        return $this->productSku;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function getProductUnit(): ?string
    {
        return $this->productUnit;
    }

    public function getQuantityOrdered(): int
    {
        return $this->quantityOrdered;
    }

    public function getQuantityFulfilled(): int
    {
        return $this->quantityFulfilled;
    }

    public function setQuantityFulfilled(int $quantityFulfilled): void
    {
        $this->quantityFulfilled = $quantityFulfilled;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantityOrdered - $this->quantityFulfilled);
    }

    public function isFullyFulfilled(): bool
    {
        return $this->quantityFulfilled >= $this->quantityOrdered;
    }

    public function getSubtotal(): float
    {
        return (float) ($this->quantityOrdered * $this->unitPrice);
    }

    public function getFulfilledSubtotal(): float
    {
        return (float) ($this->quantityFulfilled * $this->unitPrice);
    }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'sales_order_id'      => $this->salesOrderId,
            'product_id'          => $this->productId,
            'product_sku'         => $this->productSku,
            'product_name'        => $this->productName,
            'product_unit'        => $this->productUnit,
            'quantity_ordered'    => $this->quantityOrdered,
            'quantity_fulfilled'  => $this->quantityFulfilled,
            'remaining_quantity'  => $this->getRemainingQuantity(),
            'unit_price'          => $this->unitPrice,
            'subtotal'            => $this->getSubtotal(),
        ];
    }
}
