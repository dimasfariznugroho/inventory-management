<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\Warehouse;
use App\Repository\WarehouseRepositoryInterface;

class FakeWarehouseRepository implements WarehouseRepositoryInterface
{
    /** @var array<int, Warehouse> */
    private array $warehouses = [];

    public function addWarehouse(Warehouse $warehouse): void
    {
        $id = $warehouse->getId() ?? (count($this->warehouses) + 1);
        $warehouse->setId($id);
        $this->warehouses[$id] = $warehouse;
    }

    public function findById(int $id): ?Warehouse
    {
        return $this->warehouses[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->warehouses);
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        $this->addWarehouse($warehouse);
        return $warehouse;
    }
}
