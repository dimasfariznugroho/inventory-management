<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\Supplier;
use App\Repository\SupplierRepositoryInterface;

class FakeSupplierRepository implements SupplierRepositoryInterface
{
    /** @var array<int, Supplier> */
    private array $suppliers = [];

    public function addSupplier(Supplier $supplier): void
    {
        $id = $supplier->getId() ?? (count($this->suppliers) + 1);
        $supplier->setId($id);
        $this->suppliers[$id] = $supplier;
    }

    public function findById(int $id): ?Supplier
    {
        return $this->suppliers[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->suppliers);
    }

    public function save(Supplier $supplier): Supplier
    {
        $this->addSupplier($supplier);
        return $supplier;
    }
}
