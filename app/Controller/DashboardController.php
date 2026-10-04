<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthSession;
use App\Service\DashboardService;

/**
 * Controller displaying role-specific dashboard views with real-time aggregated metrics (DASH-01).
 */
class DashboardController
{
    public function __construct(
        private ?DashboardService $dashboardService = null
    ) {
    }

    public function adminDashboard(): void
    {
        $title = 'Dashboard Administrator — InventoryHub';
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        $metrics = $this->dashboardService ? $this->dashboardService->getAdminDashboardMetrics() : [];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/dashboard/admin.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function salesDashboard(): void
    {
        $title = 'Dashboard Sales — InventoryHub';
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        $userId = (int) ($user['id'] ?? 0);
        $metrics = $this->dashboardService ? $this->dashboardService->getSalesDashboardMetrics($userId) : [];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/dashboard/sales.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function warehouseDashboard(): void
    {
        $title = 'Dashboard Staf Gudang — InventoryHub';
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        $metrics = $this->dashboardService ? $this->dashboardService->getWarehouseDashboardMetrics() : [];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/dashboard/warehouse.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }
}
