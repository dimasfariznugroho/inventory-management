<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;

/**
 * Interface contract for Warehouse data access (WH-01).
 */
interface WarehouseRepositoryInterface
{
    /**
     * @return Warehouse[]
     */
    public function findAll(): array;

    public function findById(int $id): ?Warehouse;

    public function save(Warehouse $warehouse): Warehouse;
}
