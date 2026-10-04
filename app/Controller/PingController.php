<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PingService;

/**
 * Controller handling HTTP requests for system health checks.
 * Injected with PingService via constructor.
 */
class PingController
{
    public function __construct(
        private PingService $service
    ) {
    }

    /**
     * Display database connectivity and architectural skeleton status.
     */
    public function index(): void
    {
        $pingResult = $this->service->checkHealth();

        // Pass variables to view
        $title = 'System Health & Skeleton Status — Phase 0';
        $health = $pingResult;

        $viewPath = dirname(__DIR__, 2) . '/views/ping/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once $viewPath;
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }
}
