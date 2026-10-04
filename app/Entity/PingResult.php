<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Domain entity representing health check and database ping metrics.
 */
class PingResult
{
    public function __construct(
        private string $currentTime,
        private string $mysqlVersion,
        private string $databaseName,
        private bool $isAlive
    ) {
    }

    public function getCurrentTime(): string
    {
        return $this->currentTime;
    }

    public function getMysqlVersion(): string
    {
        return $this->mysqlVersion;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function isAlive(): bool
    {
        return $this->isAlive;
    }

    public function toArray(): array
    {
        return [
            'current_time'  => $this->currentTime,
            'mysql_version' => $this->mysqlVersion,
            'database_name' => $this->databaseName,
            'is_alive'      => $this->isAlive,
        ];
    }
}
