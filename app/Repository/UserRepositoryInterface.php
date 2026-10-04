<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

/**
 * Interface contract for User data access.
 */
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /**
     * @return User[]
     */
    public function findAll(): array;

    public function save(User $user): User;

    public function emailExists(string $email, ?int $excludeUserId = null): bool;
}
