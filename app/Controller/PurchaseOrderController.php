<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\PartnerService;
use App\Service\ProductService;
use App\Service\PurchaseOrderService;
use App\Service\WarehouseService;

/**
 * Controller handling Purchase Orders and Goods Receipts (PO-01).
 * Permissions: Admin and WarehouseStaff have write/process access; Sales has read-only.
 */
class PurchaseOrderController
{
    public function __construct(
        private PurchaseOrderService $poService,
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

        $pagination = $this->poService->getPaginatedPurchaseOrders($status, $warehouseId, $search, $sortOrder, $page, $perPage);
        $orders = $pagination['items'];
        $total = $pagination['total'];
        $totalPages = $pagination['total_pages'];

        $warehouses = $this->warehouseService->getAllWarehouses();

        $title = 'Purchase Orders & Penerimaan Barang (PO-01) — InventoryHub';
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/purchase_orders/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function create(): void
    {
        $this->enforceOrderStaff();

        $suppliers = $this->partnerService->getAllSuppliers();
        $warehouses = $this->warehouseService->getAllWarehouses();
        $products = $this->productService->getCatalog();

        $title = 'Buat Purchase Order Baru — InventoryHub';
        $errors = [];
        $old = [
            'supplier_id'  => '',
            'warehouse_id' => '',
            'order_date'   => date('Y-m-d'),
            'items'        => [],
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/purchase_orders/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function store(): void
    {
        $this->enforceOrderStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $rawItems = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['product_id']) && isset($item['quantity'])) {
                    $rawItems[] = [
                        'product_id' => (int) $item['product_id'],
                        'quantity'   => (int) $item['quantity'],
                        'unit_price' => (float) ($item['unit_price'] ?? 0.0),
                    ];
                }
            }
        }

        $data = [
            'supplier_id'  => $_POST['supplier_id'] ?? 0,
            'warehouse_id' => $_POST['warehouse_id'] ?? 0,
            'order_date'   => $_POST['order_date'] ?? date('Y-m-d'),
            'items'        => $rawItems,
        ];

        $result = $this->poService->createPurchaseOrder($data, $userId);

        if (!$result['success']) {
            $suppliers = $this->partnerService->getAllSuppliers();
            $warehouses = $this->warehouseService->getAllWarehouses();
            $products = $this->productService->getCatalog();

            $title = 'Buat Purchase Order Baru — InventoryHub';
            $errors = $result['errors'] ?? ['general' => $result['message'] ?? 'Terjadi kesalahan validasi.'];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/purchase_orders/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', $result['message']);
        header("Location: /purchase-orders/{$result['po']->getId()}");
        exit;
    }

    public function show(int $id): void
    {
        $this->enforceAuthenticated();

        $po = $this->poService->getPurchaseOrderById($id);
        if ($po === null) {
            http_response_code(404);
            $title = '404 - Purchase Order Tidak Ditemukan';
            $message = "Purchase Order dengan ID #{$id} tidak ditemukan di sistem.";
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/errors/404.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        $stockLedgerHistory = $this->poService->getStockLedgerByPo($id);
        $title = "Detail PO #{$po->getPoNumber()} — InventoryHub";
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/purchase_orders/show.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function markAsOrdered(int $id): void
    {
        $this->enforceOrderStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $result = $this->poService->markAsOrdered($id, $userId);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /purchase-orders/{$id}");
        exit;
    }

    public function cancel(int $id): void
    {
        $this->enforceOrderStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $result = $this->poService->cancelPurchaseOrder($id, $userId);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /purchase-orders/{$id}");
        exit;
    }

    public function processReceipt(int $id): void
    {
        $this->enforceOrderStaff();

        $user = AuthSession::user();
        $userId = $user ? (int) $user['id'] : 1;

        $receivedQuantities = $_POST['received_quantities'] ?? [];
        $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;

        $result = $this->poService->processGoodsReceipt($id, $receivedQuantities, $notes, $userId);

        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header("Location: /purchase-orders/{$id}");
        exit;
    }

    public function stockLedger(): void
    {
        $this->enforceOrderStaff();

        $title = 'Buku Besar Mutasi Stok (Stock Ledger) — InventoryHub';
        $ledgerEntries = $this->poService->getAllStockLedger(150);
        $user = AuthSession::user();

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/stock_ledger/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    private function enforceAuthenticated(): void
    {
        if (!AuthSession::isAuthenticated()) {
            AuthSession::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses sistem.');
            header('Location: /login');
            exit;
        }
    }

    private function enforceOrderStaff(): void
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
                        Akses ditolak di level server. Modul pembuatan Purchase Order dan penerimaan barang (Goods Receipt) hanya dapat dioperasikan oleh <strong>Administrator</strong> atau <strong>Warehouse Staff</strong>.
                    </p>
                    <a href="/purchase-orders" class="btn btn-primary">&larr; Kembali ke Daftar Purchase Order</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }
}
