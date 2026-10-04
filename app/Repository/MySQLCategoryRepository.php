<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use PDO;

/**
 * Concrete MySQL repository implementation for Category entity using prepared statements.
 */
class MySQLCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * @return Category[]
     */
    public function findAll(): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories ORDER BY name ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = $this->mapRowToEntity($row);
        }

        return $categories;
    }

    public function findById(int $id): ?Category
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(Category $category): Category
    {
        $pdo = $this->database->getConnection();

        if ($category->getId() === null) {
            $stmt = $pdo->prepare('
                INSERT INTO categories (name, description)
                VALUES (:name, :description)
            ');

            $stmt->execute([
                'name'        => $category->getName(),
                'description' => $category->getDescription(),
            ]);

            $category->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('
                UPDATE categories
                SET name = :name,
                    description = :description
                WHERE id = :id
            ');

            $stmt->execute([
                'id'          => $category->getId(),
                'name'        => $category->getName(),
                'description' => $category->getDescription(),
            ]);
        }

        return $category;
    }

    public function delete(int $id): bool
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function isUsedByProducts(int $categoryId): bool
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM products WHERE category_id = :category_id LIMIT 1');
        $stmt->execute(['category_id' => $categoryId]);
        return (bool) $stmt->fetchColumn();
    }

    private function mapRowToEntity(array $row): Category
    {
        return new Category(
            id: (int) $row['id'],
            name: (string) $row['name'],
            description: isset($row['description']) ? (string) $row['description'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }
}
