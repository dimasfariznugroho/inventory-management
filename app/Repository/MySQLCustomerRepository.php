<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use PDO;

/**
 * Concrete MySQL repository implementation for Customer entity.
 */
class MySQLCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * @return Customer[]
     */
    public function findAll(): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM customers ORDER BY id ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $customers = [];
        foreach ($rows as $row) {
            $customers[] = $this->mapRowToEntity($row);
        }

        return $customers;
    }

    public function findById(int $id): ?Customer
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(Customer $customer): Customer
    {
        $pdo = $this->database->getConnection();

        if ($customer->getId() === null) {
            $stmt = $pdo->prepare('
                INSERT INTO customers (name, contact, address, is_active)
                VALUES (:name, :contact, :address, :is_active)
            ');

            $stmt->execute([
                'name'      => $customer->getName(),
                'contact'   => $customer->getContact(),
                'address'   => $customer->getAddress(),
                'is_active' => $customer->isActive() ? 1 : 0,
            ]);

            $customer->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('
                UPDATE customers
                SET name = :name,
                    contact = :contact,
                    address = :address,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id'        => $customer->getId(),
                'name'      => $customer->getName(),
                'contact'   => $customer->getContact(),
                'address'   => $customer->getAddress(),
                'is_active' => $customer->isActive() ? 1 : 0,
            ]);
        }

        return $customer;
    }

    private function mapRowToEntity(array $row): Customer
    {
        return new Customer(
            id: (int) $row['id'],
            name: (string) $row['name'],
            contact: isset($row['contact']) ? (string) $row['contact'] : null,
            address: isset($row['address']) ? (string) $row['address'] : null,
            isActive: (bool) $row['is_active'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }
}
