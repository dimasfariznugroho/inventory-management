<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PingResult;

/**
 * Interface contract for Ping repository implementations.
 * Enables decoupling business logic from MySQL or in-memory implementations.
 */
interface PingRepositoryInterface
{
    /**
     * Executes a ping against the underlying data source and returns a PingResult entity.
     */
    public function ping(): PingResult;
}
