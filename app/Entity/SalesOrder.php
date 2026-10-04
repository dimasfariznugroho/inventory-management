<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing a Sales Order (SO-01).
 * Lifecycle: Draft -> PendingApproval -> Approved -> Fulfilled, or Cancelled before Fulfilled.
 */
class SalesOrder
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING_APPROVAL = 'PendingApproval';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_FULFILLED = 'Fulfilled';
    public const STATUS_CANCELLED = 'Cancelled';

    /**
     * @param SalesOrderItem[] $items
     */
    public function __construct(
        private ?int $id,
        private string $soNumber,
        private int $customerId,
        private ?string $customerName,
        private int $warehouseId,
        private ?string $warehouseName,
        private string $status,
        private int $createdBy,
        private ?string $createdByName,
        private ?int $approvedBy,
        private ?string $approvedByName,
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

    public function getSoNumber(): string
    {
        return $this->soNumber;
    }

    public function setSoNumber(string $soNumber): void
    {
        $this->soNumber = $soNumber;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
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

    public function getApprovedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?int $approvedBy): void
    {
        $this->approvedBy = $approvedBy;
    }

    public function getApprovedByName(): ?string
    {
        return $this->approvedByName;
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
     * @return SalesOrderItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param SalesOrderItem[] $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function addItem(SalesOrderItem $item): void
    {
        $this->items[] = $item;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isFulfilled(): bool
    {
        return $this->status === self::STATUS_FULFILLED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Can submit for approval if status is Draft and has items.
     */
    public function canSubmitForApproval(): bool
    {
        return $this->isDraft() && !empty($this->items);
    }

    /**
     * Can be approved/rejected if status is PendingApproval.
     */
    public function canApprove(): bool
    {
        return $this->isPendingApproval();
    }

    public function canReject(): bool
    {
        return $this->isPendingApproval();
    }

    /**
     * Can process Goods Issue if status is Approved.
     */
    public function canIssue(): bool
    {
        return $this->isApproved();
    }

    /**
     * Can cancel at any stage before Fulfilled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL, self::STATUS_APPROVED], true);
    }

    public function getTotalOrdered(): int
    {
        return (int) array_sum(array_map(fn(SalesOrderItem $item) => $item->getQuantityOrdered(), $this->items));
    }

    public function getTotalFulfilled(): int
    {
        return (int) array_sum(array_map(fn(SalesOrderItem $item) => $item->getQuantityFulfilled(), $this->items));
    }

    public function getTotalAmount(): float
    {
        return (float) array_sum(array_map(fn(SalesOrderItem $item) => $item->getSubtotal(), $this->items));
    }

    public function getTotalFulfilledAmount(): float
    {
        return (float) array_sum(array_map(fn(SalesOrderItem $item) => $item->getFulfilledSubtotal(), $this->items));
    }

    public function isFullyFulfilled(): bool
    {
        if (empty($this->items)) {
            return false;
        }

        foreach ($this->items as $item) {
            if (!$item->isFullyFulfilled()) {
                return false;
            }
        }

        return true;
    }
}
