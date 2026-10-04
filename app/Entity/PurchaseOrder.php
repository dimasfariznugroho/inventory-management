<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing a Purchase Order (PO-01).
 * Lifecycle: Draft -> Ordered -> PartiallyReceived / Received -> Cancelled.
 */
class PurchaseOrder
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ORDERED = 'Ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'PartiallyReceived';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_CANCELLED = 'Cancelled';

    /**
     * @param PurchaseOrderItem[] $items
     */
    public function __construct(
        private ?int $id,
        private string $poNumber,
        private int $supplierId,
        private ?string $supplierName,
        private int $warehouseId,
        private ?string $warehouseName,
        private string $status,
        private int $createdBy,
        private ?string $createdByName,
        private string $orderDate,
        private ?string $createdAt = null,
        private ?string $updatedAt = null,
        private array $items = []
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

    public function getPoNumber(): string
    {
        return $this->poNumber;
    }

    public function setPoNumber(string $poNumber): void
    {
        $this->poNumber = $poNumber;
    }

    public function getSupplierId(): int
    {
        return $this->supplierId;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getWarehouseName(): ?string
    {
        return $this->warehouseName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedByName(): ?string
    {
        return $this->createdByName;
    }

    public function getOrderDate(): string
    {
        return $this->orderDate;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return PurchaseOrderItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param PurchaseOrderItem[] $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function addItem(PurchaseOrderItem $item): void
    {
        $this->items[] = $item;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOrdered(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_RECEIVED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Can receive goods if PO is Ordered or PartiallyReceived.
     */
    public function canReceive(): bool
    {
        return $this->isOrdered() || $this->isPartiallyReceived();
    }

    /**
     * Can cancel if PO is Draft, or Ordered with no goods received yet.
     */
    public function canCancel(): bool
    {
        if ($this->isDraft()) {
            return true;
        }

        if ($this->isOrdered()) {
            return $this->getTotalReceived() === 0;
        }

        return false;
    }

    public function getTotalOrdered(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getQuantityOrdered();
        }
        return $total;
    }

    public function getTotalReceived(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getQuantityReceived();
        }
        return $total;
    }

    public function getTotalRemaining(): int
    {
        return max(0, $this->getTotalOrdered() - $this->getTotalReceived());
    }

    public function getTotalAmount(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'po_number'       => $this->poNumber,
            'supplier_id'     => $this->supplierId,
            'supplier_name'   => $this->supplierName,
            'warehouse_id'    => $this->warehouseId,
            'warehouse_name'  => $this->warehouseName,
            'status'          => $this->status,
            'created_by'      => $this->createdBy,
            'created_by_name' => $this->createdByName,
            'order_date'      => $this->orderDate,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
            'total_amount'    => $this->getTotalAmount(),
            'total_ordered'   => $this->getTotalOrdered(),
            'total_received'  => $this->getTotalReceived(),
            'items'           => array_map(fn($item) => $item->toArray(), $this->items),
        ];
    }
}
