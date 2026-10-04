<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\WarehouseService;

/**
 * Controller handling Warehouse CRUD for Administrators (WH-01).
 */
class WarehouseController
{
    public function __construct(
        private WarehouseService $warehouseService
    ) {
    }

    public function index(): void
    {
        $this->enforceAdminOnly();

        $title = 'Gudang & Lokasi Penyimpanan (WH-01) — Admin Panel';
        $warehouses = $this->warehouseService->getAllWarehouses();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/warehouses/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function create(): void
    {
        $this->enforceAdminOnly();

        $title = 'Tambah Gudang Baru — Admin Panel';
        $errors = [];
        $old = ['name' => '', 'location' => '', 'is_active' => '1'];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/warehouses/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function store(): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'      => $_POST['name'] ?? '',
            'location'  => $_POST['location'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $result = $this->warehouseService->createWarehouse($data);

        if (!$result['success']) {
            $title = 'Tambah Gudang Baru — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/warehouses/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Gudang '{$result['warehouse']->getName()}' berhasil ditambahkan.");
        header('Location: /admin/warehouses');
        exit;
    }

    public function edit(int $id): void
    {
        $this->enforceAdminOnly();

        $warehouse = $this->warehouseService->getWarehouseById($id);
        if ($warehouse === null) {
            AuthSession::setFlash('error', 'Gudang tidak ditemukan.');
            header('Location: /admin/warehouses');
            exit;
        }

        $title = "Edit Gudang: {$warehouse->getName()}";
        $errors = [];
        $old = [
            'id'       => $warehouse->getId(),
            'name'     => $warehouse->getName(),
            'location' => $warehouse->getLocation(),
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/warehouses/edit.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function update(int $id): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'     => $_POST['name'] ?? '',
            'location' => $_POST['location'] ?? '',
        ];

        $result = $this->warehouseService->updateWarehouse($id, $data);

        if (!$result['success']) {
            $title = 'Edit Gudang — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = array_merge($data, ['id' => $id]);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/warehouses/edit.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Gudang '{$result['warehouse']->getName()}' berhasil diperbarui.");
        header('Location: /admin/warehouses');
        exit;
    }

    public function toggleStatus(int $id): void
    {
        $this->enforceAdminOnly();

        $result = $this->warehouseService->toggleStatus($id);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header('Location: /admin/warehouses');
        exit;
    }

    private function enforceAdminOnly(): void
    {
        if (!AuthSession::isAuthenticated()) {
            AuthSession::setFlash('error', 'Silakan login terlebih dahulu.');
            header('Location: /login');
            exit;
        }

        if (!AuthSession::hasRole(User::ROLE_ADMIN)) {
            http_response_code(403);
            $title = '403 Forbidden';
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            echo '<div class="card" style="padding: 3rem; text-align: center; margin-top: 2rem;">
                    <h1 style="color: var(--color-danger); margin-bottom: 1rem;">403 Forbidden</h1>
                    <p style="color: var(--text-muted); margin-bottom: 2rem;">Akses ditolak di level server. Modul pengelolaan gudang hanya untuk Administrator.</p>
                    <a href="/products" class="btn btn-primary">&larr; Kembali ke Katalog</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }
}
