<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;

/**
 * Interface contract for Sales Order persistence operations (SO-01).
 */
interface SalesOrderRepositoryInterface
{
    /**
     * @param SalesOrderItem[] $items
     */
    public function create(SalesOrder $order, array $items): SalesOrder;

    public function findById(int $id): ?SalesOrder;

    /**
     * @return SalesOrder[]
     */
    public function findAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array;

    public function countAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null
    ): int;

    public function updateStatus(int $id, string $status, ?int $approvedBy = null): bool;

    public function updateItemFulfilledQuantity(int $itemId, int $quantity): bool;

    public function findItemById(int $itemId): ?SalesOrderItem;

    /**
     * @return array<string, int>
     */
    public function getOrderCountsByStatus(?int $createdBy = null): array;

    public function getTotalSalesAmount(?int $createdBy = null): float;

    /**
     * @return SalesOrder[]
     */
    public function getPendingIssueQueue(int $limit = 10): array;

    /**
     * @return SalesOrder[]
     */
    public function findForReport(?string $startDate = null, ?string $endDate = null, ?string $status = null): array;
}
