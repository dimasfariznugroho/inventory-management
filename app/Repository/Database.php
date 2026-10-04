<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

/**
 * Encapsulates PDO database connection and transaction management.
 * Designed for constructor injection into repositories, avoiding static/singleton access.
 */
class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password,
        private string $charset = 'utf8mb4'
    ) {
    }

    /**
     * Lazily establish and return the PDO connection instance.
     *
     * @throws PDOException if connection fails
     */
    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->host,
                $this->port,
                $this->database,
                $this->charset
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        }

        return $this->pdo;
    }

    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }
}
