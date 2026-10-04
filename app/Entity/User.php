<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing a system user.
 */
class User
{
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_SALES = 'Sales';
    public const ROLE_WAREHOUSE_STAFF = 'WarehouseStaff';

    public function __construct(
        private ?int $id,
        private string $name,
        private string $email,
        private string $password,
        private string $role,
        private bool $isActive = true,
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isWarehouseStaff(): bool
    {
        return $this->role === self::ROLE_WAREHOUSE_STAFF;
    }

    public function toArray(bool $includePassword = false): array
    {
        $data = [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'is_active'  => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if ($includePassword) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
