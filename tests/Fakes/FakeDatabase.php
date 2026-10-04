<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Repository\Database;
use PDO;
use RuntimeException;

/**
 * In-memory test fake for Database transaction boundary.
 * Completely isolates Unit Tests from PDO / network MySQL sockets.
 */
class FakeDatabase extends Database
{
    private bool $inTx = false;
    private int $commitCount = 0;
    private int $rollbackCount = 0;

    public function __construct()
    {
        parent::__construct('127.0.0.1', 3306, 'fake_db', 'fake_user', 'secret');
    }

    public function getConnection(): PDO
    {
        throw new RuntimeException('Unit test fake database must never attempt a real PDO connection.');
    }

    public function beginTransaction(): bool
    {
        $this->inTx = true;
        return true;
    }

    public function commit(): bool
    {
        $this->inTx = false;
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool
    {
        $this->inTx = false;
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }

    public function getCommitCount(): int
    {
        return $this->commitCount;
    }

    public function getRollbackCount(): int
    {
        return $this->rollbackCount;
    }
}
