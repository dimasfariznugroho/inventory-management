<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Repository\SalesOrderRepositoryInterface;

/**
 * Pure in-memory Fake for SalesOrderRepositoryInterface.
 */
class FakeSalesOrderRepository implements SalesOrderRepositoryInterface
{
    /** @var array<int, SalesOrder> */
    private array $orders = [];

    /** @var array<int, SalesOrderItem> */
    private array $items = [];

    private int $nextOrderId = 1;
    private int $nextItemId = 1;

    public function create(SalesOrder $order, array $items): SalesOrder
    {
        $id = $order->getId() ?? $this->nextOrderId++;
        $order->setId($id);

        $savedItems = [];
        foreach ($items as $item) {
            $itemId = $item->getId() ?? $this->nextItemId++;
            $item->setId($itemId);
            $item->setSalesOrderId($id);
            $this->items[$itemId] = $item;
            $savedItems[] = $item;
        }

        $order->setItems($savedItems);
        $this->orders[$id] = $order;

        return $order;
    }

    public function findById(int $id): ?SalesOrder
    {
        if (!isset($this->orders[$id])) {
            return null;
        }

        $order = $this->orders[$id];
        $orderItems = [];
        foreach ($this->items as $item) {
            if ($item->getSalesOrderId() === $id) {
                $orderItems[] = $item;
            }
        }
        $order->setItems($orderItems);

        return $order;
    }

    public function findAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        $filtered = array_values(array_filter($this->orders, function (SalesOrder $so) use ($status, $warehouseId, $createdBy) {
            if ($status !== null && $so->getStatus() !== $status) {
                return false;
            }
            if ($warehouseId !== null && $so->getWarehouseId() !== $warehouseId) {
                return false;
            }
            if ($createdBy !== null && $so->getCreatedBy() !== $createdBy) {
                return false;
            }
            return true;
        }));

        $offset = ($page - 1) * $perPage;
        return array_slice($filtered, $offset, $perPage);
    }

    public function countAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?int $createdBy = null
    ): int {
        return count($this->findAll($status, $warehouseId, $search, $createdBy, 'DESC', 1, PHP_INT_MAX));
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null): bool
    {
        if (!isset($this->orders[$id])) {
            return false;
        }

        $order = $this->orders[$id];
        $order->setStatus($status);
        if ($approvedBy !== null) {
            $order->setApprovedBy($approvedBy);
        }

        return true;
    }

    public function updateItemFulfilledQuantity(int $itemId, int $quantity): bool
    {
        if (!isset($this->items[$itemId])) {
            return false;
        }

        $this->items[$itemId]->setQuantityFulfilled($quantity);
        return true;
    }

    public function findItemById(int $itemId): ?SalesOrderItem
    {
        return $this->items[$itemId] ?? null;
    }

    public function getOrderCountsByStatus(?int $createdBy = null): array
    {
        $counts = [
            'Draft'           => 0,
            'PendingApproval' => 0,
            'Approved'        => 0,
            'Fulfilled'       => 0,
            'Cancelled'       => 0,
        ];

        foreach ($this->orders as $so) {
            if ($createdBy !== null && $so->getCreatedBy() !== $createdBy) {
                continue;
            }
            if (isset($counts[$so->getStatus()])) {
                $counts[$so->getStatus()]++;
            }
        }

        return $counts;
    }

    public function getTotalSalesAmount(?int $createdBy = null): float
    {
        $total = 0.0;
        foreach ($this->orders as $so) {
            if ($createdBy !== null && $so->getCreatedBy() !== $createdBy) {
                continue;
            }
            $total += $so->getTotalAmount();
        }
        return $total;
    }

    public function getPendingIssueQueue(int $limit = 10): array
    {
        $pending = [];
        foreach ($this->orders as $so) {
            if ($so->getStatus() === SalesOrder::STATUS_APPROVED) {
                $pending[] = $so;
            }
        }
        return array_slice($pending, 0, $limit);
    }

    public function findForReport(?string $startDate = null, ?string $endDate = null, ?string $status = null): array
    {
        return array_values($this->orders);
    }
}
