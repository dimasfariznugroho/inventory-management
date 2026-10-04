<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SalesOrder;
use App\Entity\User;
use App\Service\AuthSession;
use App\Service\PartnerService;
use App\Service\ProductService;
use App\Service\SalesOrderService;
use App\Service\WarehouseService;

/**
 * Controller handling Sales Orders, Approval Workflow, and Goods Issue (SO-01, ARCH-02).
 * Strictly enforces Segregation of Duties and Role-Based Permissions.
 */
class SalesOrderController
{
    public function __construct(
        private SalesOrderService $soService,
        private PartnerService $partnerService,
        private WarehouseService $warehouseService,
        private ProductService $productService
    ) {
    }

    public function index(): void
    {
        $this->enforceAuthenticated();

        $status = isset($_GET['status']) && trim((string) $_GET['status']) !== '' ? trim((string) $_GET['status']) : null;
        $warehouseId = isset($_GET['warehouse_id']) && (int) $_GET['warehouse_id'] > 0 ? (int) $_GET['warehouse_id'] : null;
        $search = isset($_GET['search']) && trim((string) $_GET['search']) !== '' ? trim((string) $_GET['search']) : null;
        $sortOrder = (isset($_GET['sort']) && strtolower((string) $_GET['sort']) === 'asc') ? 'ASC' : 'DESC';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $pagination = $this->soService->getPaginatedSalesOrders($status, $warehouseId, $search, null, $sortOrder, $page, $perPage);
        $orders = $pagination['items'];
        $total = $pagination['total'];
        $totalPages = $pagination['total_pages'];

        $warehouses = $this->warehouseService->getAllWarehouses();

        $title = 'Sales Orders & Pengeluaran Barang (SO-01) — InventoryHub';
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/sales_orders/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function create(): void
    {
        $this->enforceSalesStaff();

        $customers = $this->partnerService->getAllCustomers();
        $warehouses = $this->warehouseService->getAllWarehouses();
        $products = $this->productService->getCatalog();

        $title = 'Buat Sales Order Baru — InventoryHub';
        $errors = [];
        $old = [
            'customer_id'  => '',
            'warehouse_id' => '',
            'order_date'   => date('Y-m-d'),
            'items'        => [],
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/sales_orders/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function store(): void
    {
        $this->enforceSalesStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $rawItems = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['product_id']) && isset($item['quantity'])) {
                    $rawItems[] = [
                        'product_id' => (int) $item['product_id'],
                        'quantity'   => (int) $item['quantity'],
                        'unit_price' => isset($item['unit_price']) && $item['unit_price'] !== '' ? (float) $item['unit_price'] : 0.0,
                    ];
                }
            }
        }

        $data = [
            'customer_id'  => $_POST['customer_id'] ?? 0,
            'warehouse_id' => $_POST['warehouse_id'] ?? 0,
            'order_date'   => $_POST['order_date'] ?? date('Y-m-d'),
            'items'        => $rawItems,
        ];

        $result = $this->soService->createSalesOrder($data, $userId);

        if (!$result['success']) {
            $customers = $this->partnerService->getAllCustomers();
            $warehouses = $this->warehouseService->getAllWarehouses();
            $products = $this->productService->getCatalog();

            $title = 'Buat Sales Order Baru — InventoryHub';
            $errors = $result['errors'] ?? [];
            $old = $data;

            AuthSession::setFlash('error', $result['message']);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/sales_orders/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', $result['message']);
        header("Location: /sales-orders/{$result['so']->getId()}");
        exit;
    }

    public function show(int $id): void
    {
        $this->enforceAuthenticated();

        $order = $this->soService->getSalesOrderById($id);
        if ($order === null) {
            http_response_code(404);
            $title = '404 - Sales Order Tidak Ditemukan';
            $message = "Sales Order dengan ID #{$id} tidak ditemukan di sistem.";
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/errors/404.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        // Fetch stock ledger mutation entries for this SO
        $ledgerRepo = new \App\Repository\MySQLStockLedgerRepository(new \App\Repository\Database(
            host: $GLOBALS['dbConfig']['host'],
            port: $GLOBALS['dbConfig']['port'],
            database: $GLOBALS['dbConfig']['database'],
            username: $GLOBALS['dbConfig']['username'],
            password: $GLOBALS['dbConfig']['password'],
            charset: $GLOBALS['dbConfig']['charset']
        ));
        $ledgerEntries = $ledgerRepo->findByReference('SalesOrder', $id);

        $title = "Detail Sales Order: {$order->getSoNumber()} — InventoryHub";
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/sales_orders/show.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function submitForApproval(int $id): void
    {
        $this->enforceSalesStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $result = $this->soService->submitForApproval($id, $userId);

        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /sales-orders/{$id}");
        exit;
    }

    /**
     * Admin-only approval action (Segregation of Duties).
     */
    public function approve(int $id): void
    {
        $this->enforceAdminOnly('Hanya Administrator yang memiliki wewenang untuk menyetujui Sales Order.');

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;
        $userRole = $user ? (string) $user['role'] : '';

        $result = $this->soService->approveSalesOrder($id, $userId, $userRole);

        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /sales-orders/{$id}");
        exit;
    }

    /**
     * Admin-only rejection action (Segregation of Duties).
     */
    public function reject(int $id): void
    {
        $this->enforceAdminOnly('Hanya Administrator yang memiliki wewenang untuk menolak Sales Order.');

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;
        $userRole = $user ? (string) $user['role'] : '';

        $result = $this->soService->rejectSalesOrder($id, $userId, $userRole);

        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /sales-orders/{$id}");
        exit;
    }

    public function cancel(int $id): void
    {
        $this->enforceSalesStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $result = $this->soService->cancelSalesOrder($id, $userId);

        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /sales-orders/{$id}");
        exit;
    }

    /**
     * Process Goods Issue (Warehouse Staff or Admin).
     */
    public function processIssue(int $id): void
    {
        $this->enforceWarehouseStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $issuedQuantities = $_POST['issued_quantities'] ?? [];
        $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;

        $result = $this->soService->processGoodsIssue($id, $issuedQuantities, $notes, $userId);

        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /sales-orders/{$id}");
        exit;
    }

    private function enforceAuthenticated(): void
    {
        if (!AuthSession::isAuthenticated()) {
            AuthSession::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses sistem.');
            header('Location: /login');
            exit;
        }
    }

    /**
     * Enforce Sales or Admin access (creation, submission, cancellation).
     */
    private function enforceSalesStaff(): void
    {
        $this->enforceAuthenticated();

        if (!AuthSession::hasRole([User::ROLE_ADMIN, User::ROLE_SALES])) {
            http_response_code(403);
            $title = '403 Forbidden — Akses Ditolak';
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            echo '<div class="card" style="padding: 3.5rem; text-align: center; margin-top: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <h1 style="font-size: 2.2rem; margin-bottom: 0.75rem;">403 Forbidden</h1>
                    <p style="color: var(--text-muted); max-width: 550px; margin: 0 auto 2rem;">
                        Akses ditolak di level server. Modul pembuatan dan pengajuan Sales Order hanya dapat dioperasikan oleh <strong>Administrator</strong> atau staf <strong>Sales</strong>.
                    </p>
                    <a href="/sales-orders" class="btn btn-primary">&larr; Kembali ke Daftar Sales Order</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }

    /**
     * Enforce Warehouse Staff or Admin access for physical Goods Issue.
     */
    private function enforceWarehouseStaff(): void
    {
        $this->enforceAuthenticated();

        if (!AuthSession::hasRole([User::ROLE_ADMIN, User::ROLE_WAREHOUSE_STAFF])) {
            http_response_code(403);
            $title = '403 Forbidden — Akses Ditolak';
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            echo '<div class="card" style="padding: 3.5rem; text-align: center; margin-top: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <h1 style="font-size: 2.2rem; margin-bottom: 0.75rem;">403 Forbidden</h1>
                    <p style="color: var(--text-muted); max-width: 550px; margin: 0 auto 2rem;">
                        Akses ditolak di level server. Operasi pengeluaran fisik barang (Goods Issue) hanya dapat diproses oleh <strong>Administrator</strong> atau <strong>Warehouse Staff</strong>.
                    </p>
                    <a href="/sales-orders" class="btn btn-primary">&larr; Kembali ke Daftar Sales Order</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }

    /**
     * Enforce Admin-only access (Segregation of Duties for SO Approval/Rejection).
     */
    private function enforceAdminOnly(string $reason = 'Operasi ini memerlukan hak akses Administrator.'): void
    {
        $this->enforceAuthenticated();

        if (!AuthSession::hasRole(User::ROLE_ADMIN)) {
            http_response_code(403);
            $title = '403 Forbidden — Akses Ditolak';
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            echo '<div class="card" style="padding: 3.5rem; text-align: center; margin-top: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <h1 style="font-size: 2.2rem; margin-bottom: 0.75rem;">403 Forbidden (Segregation of Duties)</h1>
                    <p style="color: var(--text-muted); max-width: 550px; margin: 0 auto 2rem;">
                        ' . htmlspecialchars($reason) . '
                    </p>
                    <a href="/sales-orders" class="btn btn-primary">&larr; Kembali ke Daftar Sales Order</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }
}
