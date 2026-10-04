<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use PDO;

/**
 * Concrete MySQL repository implementation for Supplier entity.
 */
class MySQLSupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * @return Supplier[]
     */
    public function findAll(): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM suppliers ORDER BY id ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $suppliers = [];
        foreach ($rows as $row) {
            $suppliers[] = $this->mapRowToEntity($row);
        }

        return $suppliers;
    }

    public function findById(int $id): ?Supplier
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(Supplier $supplier): Supplier
    {
        $pdo = $this->database->getConnection();

        if ($supplier->getId() === null) {
            $stmt = $pdo->prepare('
                INSERT INTO suppliers (name, contact, address, is_active)
                VALUES (:name, :contact, :address, :is_active)
            ');

            $stmt->execute([
                'name'      => $supplier->getName(),
                'contact'   => $supplier->getContact(),
                'address'   => $supplier->getAddress(),
                'is_active' => $supplier->isActive() ? 1 : 0,
            ]);

            $supplier->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('
                UPDATE suppliers
                SET name = :name,
                    contact = :contact,
                    address = :address,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id'        => $supplier->getId(),
                'name'      => $supplier->getName(),
                'contact'   => $supplier->getContact(),
                'address'   => $supplier->getAddress(),
                'is_active' => $supplier->isActive() ? 1 : 0,
            ]);
        }

        return $supplier;
    }

    private function mapRowToEntity(array $row): Supplier
    {
        return new Supplier(
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
