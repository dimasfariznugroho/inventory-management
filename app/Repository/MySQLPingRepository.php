<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PingResult;
use PDOException;

/**
 * Concrete MySQL implementation of PingRepositoryInterface.
 * Injected with Database instance via constructor.
 */
class MySQLPingRepository implements PingRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    public function ping(): PingResult
    {
        try {
            $pdo = $this->database->getConnection();
            $stmt = $pdo->prepare('SELECT NOW() AS current_time, VERSION() AS mysql_version, COALESCE(DATABASE(), "") AS db_name, 1 AS is_alive');
            $stmt->execute();

            $row = $stmt->fetch();
            if ($row === false) {
                return new PingResult('N/A', 'N/A', 'N/A', false);
            }

            return new PingResult(
                (string) ($row['current_time'] ?? 'N/A'),
                (string) ($row['mysql_version'] ?? 'N/A'),
                (string) ($row['db_name'] ?? 'N/A'),
                (bool) ($row['is_alive'] ?? false)
            );
        } catch (PDOException $e) {
            return new PingResult('Error: ' . $e->getMessage(), 'N/A', 'N/A', false);
        }
    }
}
