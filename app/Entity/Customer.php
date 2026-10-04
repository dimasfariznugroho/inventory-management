<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing an order customer.
 */
class Customer extends Partner
{
    /**
     * Hydrate a Customer entity from a database record.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: (string) ($data['name'] ?? ''),
            contact: isset($data['contact']) ? (string) $data['contact'] : null,
            address: isset($data['address']) ? (string) $data['address'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null
        );
    }
}
