<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing an immutable inventory mutation audit entry (Stock Ledger).
 */
class StockLedger
{
    public const TYPE_RECEIPT = 'Receipt';
    public const TYPE_ISSUE = 'Issue';
    public const TYPE_ADJUSTMENT = 'Adjustment';

    public function __construct(
        private ?int $id,
        private int $productId,
        private ?string $productSku,
        private ?string $productName,
        private ?string $productUnit,
        private int $warehouseId,
        private ?string $warehouseName,
        private string $transactionType,
        private int $quantity,
        private string $referenceType,
        private int $referenceId,
        private ?string $notes,
        private int $createdBy,
        private ?string $createdByName,
        private ?string $createdAt = null
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

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getWarehouseName(): ?string
    {
        return $this->warehouseName;
    }

    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getReferenceType(): string
    {
        return $this->referenceType;
    }

    public function getReferenceId(): int
    {
        return $this->referenceId;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedByName(): ?string
    {
        return $this->createdByName;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'product_id'       => $this->productId,
            'product_sku'      => $this->productSku,
            'product_name'     => $this->productName,
            'product_unit'     => $this->productUnit,
            'warehouse_id'     => $this->warehouseId,
            'warehouse_name'   => $this->warehouseName,
            'transaction_type' => $this->transactionType,
            'quantity'         => $this->quantity,
            'reference_type'   => $this->referenceType,
            'reference_id'     => $this->referenceId,
            'notes'            => $this->notes,
            'created_by'       => $this->createdBy,
            'created_by_name'  => $this->createdByName,
            'created_at'       => $this->createdAt,
        ];
    }
}
