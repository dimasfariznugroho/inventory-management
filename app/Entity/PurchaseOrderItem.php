<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing an item line in a Purchase Order (PO-01).
 */
class PurchaseOrderItem
{
    public function __construct(
        private ?int $id,
        private ?int $purchaseOrderId,
        private int $productId,
        private ?string $productSku,
        private ?string $productName,
        private ?string $productUnit,
        private int $quantityOrdered,
        private int $quantityReceived = 0,
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

    public function getPurchaseOrderId(): ?int
    {
        return $this->purchaseOrderId;
    }

    public function setPurchaseOrderId(int $purchaseOrderId): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
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

    public function getQuantityReceived(): int
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(int $quantityReceived): void
    {
        $this->quantityReceived = $quantityReceived;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantityOrdered - $this->quantityReceived);
    }

    public function isFullyReceived(): bool
    {
        return $this->quantityReceived >= $this->quantityOrdered;
    }

    public function getSubtotal(): float
    {
        return (float) ($this->quantityOrdered * $this->unitPrice);
    }

    public function getReceivedSubtotal(): float
    {
        return (float) ($this->quantityReceived * $this->unitPrice);
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'purchase_order_id'  => $this->purchaseOrderId,
            'product_id'         => $this->productId,
            'product_sku'        => $this->productSku,
            'product_name'       => $this->productName,
            'product_unit'       => $this->productUnit,
            'quantity_ordered'   => $this->quantityOrdered,
            'quantity_received'  => $this->quantityReceived,
            'remaining_quantity' => $this->getRemainingQuantity(),
            'unit_price'         => $this->unitPrice,
            'subtotal'           => $this->getSubtotal(),
        ];
    }
}
