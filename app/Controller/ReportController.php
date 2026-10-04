<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\ReportService;

/**
 * Controller handling CSV export generation (REPORT-01).
 * Supports date range filtering and order status selection.
 */
class ReportController
{
    public function __construct(
        private ReportService $reportService
    ) {
    }

    /**
     * GET /reports/stock-ledger/export
     * Exports stock movement transactions from stock_ledger as CSV.
     */
    public function exportStockLedger(): void
    {
        $this->enforceAuthenticated();
        // Same access rule as the /stock-ledger page: Sales must not bypass it via the CSV export.
        $this->enforceRoles([User::ROLE_ADMIN, User::ROLE_WAREHOUSE_STAFF]);

        $startDate = isset($_GET['start_date']) && trim((string) $_GET['start_date']) !== '' ? trim((string) $_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && trim((string) $_GET['end_date']) !== '' ? trim((string) $_GET['end_date']) : null;
        $transactionType = isset($_GET['transaction_type']) && trim((string) $_GET['transaction_type']) !== '' ? trim((string) $_GET['transaction_type']) : null;
        $warehouseId = isset($_GET['warehouse_id']) && (int) $_GET['warehouse_id'] > 0 ? (int) $_GET['warehouse_id'] : null;

        $csv = $this->reportService->exportStockLedgerCsv($startDate, $endDate, $transactionType, $warehouseId);

        $filename = 'stock_ledger_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }

    /**
     * GET /reports/orders/export
     * Exports Purchase Orders or Sales Orders with status and items as CSV.
     */
    public function exportOrders(): void
    {
        $this->enforceAuthenticated();

        $type = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : 'po';
        $startDate = isset($_GET['start_date']) && trim((string) $_GET['start_date']) !== '' ? trim((string) $_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && trim((string) $_GET['end_date']) !== '' ? trim((string) $_GET['end_date']) : null;
        $status = isset($_GET['status']) && trim((string) $_GET['status']) !== '' ? trim((string) $_GET['status']) : null;

        $csv = $this->reportService->exportOrdersCsv($type, $startDate, $endDate, $status);

        $filename = ($type === 'so' ? 'sales_orders_' : 'purchase_orders_') . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }

    private function enforceAuthenticated(): void
    {
        if (!AuthSession::isAuthenticated()) {
            AuthSession::setFlash('error', 'Silakan login terlebih dahulu untuk mengunduh laporan.');
            header('Location: /login');
            exit;
        }
    }

    /**
     * @param string[] $roles
     */
    private function enforceRoles(array $roles): void
    {
        if (AuthSession::hasRole($roles)) {
            return;
        }

        http_response_code(403);
        $title = '403 Forbidden — Akses Ditolak';
        $message = 'Anda tidak memiliki izin untuk mengunduh laporan ini.';
        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/errors/403.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
        exit;
    }
}
