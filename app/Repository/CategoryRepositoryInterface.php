<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

/**
 * Interface contract for Category data access.
 */
interface CategoryRepositoryInterface
{
    /**
     * @return Category[]
     */
    public function findAll(): array;

    public function findById(int $id): ?Category;

    public function save(Category $category): Category;

    public function delete(int $id): bool;

    /**
     * Check whether the category is still referenced by any product in the database.
     */
    public function isUsedByProducts(int $categoryId): bool;
}
