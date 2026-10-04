<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PingResult;
use App\Repository\PingRepositoryInterface;

/**
 * Service encapsulating application health verification business logic.
 * Depends strictly on PingRepositoryInterface via constructor injection.
 */
class PingService
{
    public function __construct(
        private PingRepositoryInterface $repository
    ) {
    }

    /**
     * Inspect system health status and database connectivity.
     */
    public function checkHealth(): PingResult
    {
        return $this->repository->ping();
    }
}
