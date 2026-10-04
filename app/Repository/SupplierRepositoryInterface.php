<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;

/**
 * Interface contract for Supplier data access.
 */
interface SupplierRepositoryInterface
{
    /**
     * @return Supplier[]
     */
    public function findAll(): array;

    public function findById(int $id): ?Supplier;

    public function save(Supplier $supplier): Supplier;
}
