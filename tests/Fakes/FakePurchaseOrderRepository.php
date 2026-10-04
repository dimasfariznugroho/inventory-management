<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Repository\PurchaseOrderRepositoryInterface;

class FakePurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /** @var array<int, PurchaseOrder> */
    private array $orders = [];

    /** @var array<int, PurchaseOrderItem> */
    private array $items = [];

    private int $nextOrderId = 1;
    private int $nextItemId = 1;

    public function create(PurchaseOrder $order, array $items): PurchaseOrder
    {
        $id = $order->getId() ?? $this->nextOrderId++;
        $order->setId($id);

        $savedItems = [];
        foreach ($items as $item) {
            $itemId = $item->getId() ?? $this->nextItemId++;
            $item->setId($itemId);
            $item->setPurchaseOrderId($id);
            $this->items[$itemId] = $item;
            $savedItems[] = $item;
        }

        $order->setItems($savedItems);
        $this->orders[$id] = $order;

        return $order;
    }

    public function findById(int $id): ?PurchaseOrder
    {
        if (!isset($this->orders[$id])) {
            return null;
        }

        $order = $this->orders[$id];
        $orderItems = [];
        foreach ($this->items as $item) {
            if ($item->getPurchaseOrderId() === $id) {
                $orderItems[] = $item;
            }
        }
        $order->setItems($orderItems);

        return $order;
    }

    public function findByPoNumber(string $poNumber): ?PurchaseOrder
    {
        foreach ($this->orders as $order) {
            if ($order->getPoNumber() === $poNumber) {
                return $this->findById($order->getId() ?? 0);
            }
        }
        return null;
    }

    public function findAll(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        $filtered = array_values(array_filter($this->orders, function (PurchaseOrder $po) use ($status, $warehouseId) {
            if ($status !== null && $po->getStatus() !== $status) {
                return false;
            }
            if ($warehouseId !== null && $po->getWarehouseId() !== $warehouseId) {
                return false;
            }
            return true;
        }));

        $offset = ($page - 1) * $perPage;
        return array_slice($filtered, $offset, $perPage);
    }

    public function countAll(?string $status = null, ?int $warehouseId = null, ?string $search = null): int
    {
        return count($this->orders);
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!isset($this->orders[$id])) {
            return false;
        }

        $this->orders[$id]->setStatus($status);
        return true;
    }

    public function updateItemReceivedQuantity(int $itemId, int $quantityReceived): bool
    {
        if (!isset($this->items[$itemId])) {
            return false;
        }

        $this->items[$itemId]->setQuantityReceived($quantityReceived);
        return true;
    }

    public function getNextPoNumber(): string
    {
        return 'PO-' . date('Ymd') . '-' . sprintf('%04d', $this->nextOrderId);
    }

    public function getOrderCountsByStatus(): array
    {
        $counts = [
            'Draft'             => 0,
            'Ordered'           => 0,
            'PartiallyReceived' => 0,
            'Received'          => 0,
            'Cancelled'         => 0,
        ];

        foreach ($this->orders as $po) {
            if (isset($counts[$po->getStatus()])) {
                $counts[$po->getStatus()]++;
            }
        }

        return $counts;
    }

    public function getPendingReceiptQueue(int $limit = 10): array
    {
        $queue = [];
        foreach ($this->orders as $po) {
            if (in_array($po->getStatus(), [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                $queue[] = $po;
            }
        }
        return array_slice($queue, 0, $limit);
    }

    public function findForReport(?string $startDate = null, ?string $endDate = null, ?string $status = null): array
    {
        return array_values($this->orders);
    }
}
