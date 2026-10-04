<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\Customer;
use App\Repository\CustomerRepositoryInterface;

class FakeCustomerRepository implements CustomerRepositoryInterface
{
    /** @var array<int, Customer> */
    private array $customers = [];

    public function addCustomer(Customer $customer): void
    {
        $id = $customer->getId() ?? (count($this->customers) + 1);
        $customer->setId($id);
        $this->customers[$id] = $customer;
    }

    public function findById(int $id): ?Customer
    {
        return $this->customers[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->customers);
    }

    public function save(Customer $customer): Customer
    {
        $this->addCustomer($customer);
        return $customer;
    }
}
