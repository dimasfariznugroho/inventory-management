<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;

/**
 * Interface contract for Purchase Order data access.
 */
interface PurchaseOrderRepositoryInterface
{
    /**
     * @param PurchaseOrderItem[] $items
     */
    public function create(PurchaseOrder $order, array $items): PurchaseOrder;

    public function findById(int $id): ?PurchaseOrder;

    public function findByPoNumber(string $poNumber): ?PurchaseOrder;

    /**
     * @return PurchaseOrder[]
     */
    public function findAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array;

    public function countAll(?string $status = null, ?int $warehouseId = null, ?string $search = null): int;

    public function updateStatus(int $id, string $status): bool;

    public function updateItemReceivedQuantity(int $itemId, int $quantityReceived): bool;

    public function getNextPoNumber(): string;

    /**
     * @return array<string, int>
     */
    public function getOrderCountsByStatus(): array;

    /**
     * @return PurchaseOrder[]
     */
    public function getPendingReceiptQueue(int $limit = 10): array;

    /**
     * @return PurchaseOrder[]
     */
    public function findForReport(?string $startDate = null, ?string $endDate = null, ?string $status = null): array;
}
