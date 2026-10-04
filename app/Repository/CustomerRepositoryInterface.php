<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;

/**
 * Interface contract for Customer data access.
 */
interface CustomerRepositoryInterface
{
    /**
     * @return Customer[]
     */
    public function findAll(): array;

    public function findById(int $id): ?Customer;

    public function save(Customer $customer): Customer;
}
