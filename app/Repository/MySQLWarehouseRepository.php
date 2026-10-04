<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;
use PDO;

/**
 * Concrete MySQL repository implementation for Warehouse entity using prepared statements.
 */
class MySQLWarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * @return Warehouse[]
     */
    public function findAll(): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM warehouses ORDER BY id ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $warehouses = [];
        foreach ($rows as $row) {
            $warehouses[] = $this->mapRowToEntity($row);
        }

        return $warehouses;
    }

    public function findById(int $id): ?Warehouse
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        $pdo = $this->database->getConnection();

        if ($warehouse->getId() === null) {
            $stmt = $pdo->prepare('
                INSERT INTO warehouses (name, location, is_active)
                VALUES (:name, :location, :is_active)
            ');

            $stmt->execute([
                'name'      => $warehouse->getName(),
                'location'  => $warehouse->getLocation(),
                'is_active' => $warehouse->isActive() ? 1 : 0,
            ]);

            $warehouse->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('
                UPDATE warehouses
                SET name = :name,
                    location = :location,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id'        => $warehouse->getId(),
                'name'      => $warehouse->getName(),
                'location'  => $warehouse->getLocation(),
                'is_active' => $warehouse->isActive() ? 1 : 0,
            ]);
        }

        return $warehouse;
    }

    private function mapRowToEntity(array $row): Warehouse
    {
        return new Warehouse(
            id: (int) $row['id'],
            name: (string) $row['name'],
            location: (string) $row['location'],
            isActive: (bool) $row['is_active'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }
}
