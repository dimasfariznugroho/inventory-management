<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use PDO;

/**
 * Concrete MySQL repository implementation for User entity using prepared statements.
 */
class MySQLUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findById(int $id): ?User
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users ORDER BY id ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $users = [];
        foreach ($rows as $row) {
            $users[] = $this->mapRowToEntity($row);
        }

        return $users;
    }

    public function save(User $user): User
    {
        $pdo = $this->database->getConnection();

        if ($user->getId() === null) {
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, password, role, is_active)
                VALUES (:name, :email, :password, :role, :is_active)
            ');

            $stmt->execute([
                'name'      => $user->getName(),
                'email'     => $user->getEmail(),
                'password'  => $user->getPassword(),
                'role'      => $user->getRole(),
                'is_active' => $user->isActive() ? 1 : 0,
            ]);

            $user->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('
                UPDATE users
                SET name = :name,
                    email = :email,
                    password = :password,
                    role = :role,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id'        => $user->getId(),
                'name'      => $user->getName(),
                'email'     => $user->getEmail(),
                'password'  => $user->getPassword(),
                'role'      => $user->getRole(),
                'is_active' => $user->isActive() ? 1 : 0,
            ]);
        }

        return $user;
    }

    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $pdo = $this->database->getConnection();

        if ($excludeUserId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email AND id != :exclude_id LIMIT 1');
            $stmt->execute([
                'email'      => $email,
                'exclude_id' => $excludeUserId,
            ]);
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
        }

        return (bool) $stmt->fetchColumn();
    }

    private function mapRowToEntity(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            password: (string) $row['password'],
            role: (string) $row['role'],
            isActive: (bool) $row['is_active'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }
}
