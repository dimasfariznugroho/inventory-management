<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\PartnerService;

/**
 * Controller handling Supplier & Customer CRUD for Administrators.
 */
class PartnerController
{
    public function __construct(
        private PartnerService $partnerService
    ) {
    }

    // ==========================================
    // Suppliers
    // ==========================================

    public function listSuppliers(): void
    {
        $this->enforceAdminOnly();

        $title = 'Pemasok (Suppliers) — Admin Panel';
        $suppliers = $this->partnerService->getAllSuppliers();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/suppliers/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function createSupplier(): void
    {
        $this->enforceAdminOnly();

        $title = 'Tambah Pemasok Baru — Admin Panel';
        $errors = [];
        $old = ['name' => '', 'contact' => '', 'address' => '', 'is_active' => '1'];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/suppliers/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function storeSupplier(): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'      => $_POST['name'] ?? '',
            'contact'   => $_POST['contact'] ?? '',
            'address'   => $_POST['address'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $result = $this->partnerService->createSupplier($data);

        if (!$result['success']) {
            $title = 'Tambah Pemasok Baru — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/suppliers/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Supplier '{$result['supplier']->getName()}' berhasil ditambahkan.");
        header('Location: /admin/suppliers');
        exit;
    }

    public function editSupplier(int $id): void
    {
        $this->enforceAdminOnly();

        $supplier = $this->partnerService->getSupplierById($id);
        if ($supplier === null) {
            AuthSession::setFlash('error', 'Supplier tidak ditemukan.');
            header('Location: /admin/suppliers');
            exit;
        }

        $title = "Edit Pemasok: {$supplier->getName()}";
        $errors = [];
        $old = [
            'id'      => $supplier->getId(),
            'name'    => $supplier->getName(),
            'contact' => $supplier->getContact(),
            'address' => $supplier->getAddress(),
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/suppliers/edit.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function updateSupplier(int $id): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'    => $_POST['name'] ?? '',
            'contact' => $_POST['contact'] ?? '',
            'address' => $_POST['address'] ?? '',
        ];

        $result = $this->partnerService->updateSupplier($id, $data);

        if (!$result['success']) {
            $title = 'Edit Pemasok — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = array_merge($data, ['id' => $id]);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/suppliers/edit.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Data supplier '{$result['supplier']->getName()}' berhasil diperbarui.");
        header('Location: /admin/suppliers');
        exit;
    }

    public function toggleSupplier(int $id): void
    {
        $this->enforceAdminOnly();

        $result = $this->partnerService->toggleSupplierStatus($id);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header('Location: /admin/suppliers');
        exit;
    }

    // ==========================================
    // Customers
    // ==========================================

    public function listCustomers(): void
    {
        $this->enforceAdminOnly();

        $title = 'Pelanggan (Customers) — Admin Panel';
        $customers = $this->partnerService->getAllCustomers();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/customers/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function createCustomer(): void
    {
        $this->enforceAdminOnly();

        $title = 'Tambah Pelanggan Baru — Admin Panel';
        $errors = [];
        $old = ['name' => '', 'contact' => '', 'address' => '', 'is_active' => '1'];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/customers/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function storeCustomer(): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'      => $_POST['name'] ?? '',
            'contact'   => $_POST['contact'] ?? '',
            'address'   => $_POST['address'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $result = $this->partnerService->createCustomer($data);

        if (!$result['success']) {
            $title = 'Tambah Pelanggan Baru — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/customers/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Pelanggan '{$result['customer']->getName()}' berhasil ditambahkan.");
        header('Location: /admin/customers');
        exit;
    }

    public function editCustomer(int $id): void
    {
        $this->enforceAdminOnly();

        $customer = $this->partnerService->getCustomerById($id);
        if ($customer === null) {
            AuthSession::setFlash('error', 'Customer tidak ditemukan.');
            header('Location: /admin/customers');
            exit;
        }

        $title = "Edit Pelanggan: {$customer->getName()}";
        $errors = [];
        $old = [
            'id'      => $customer->getId(),
            'name'    => $customer->getName(),
            'contact' => $customer->getContact(),
            'address' => $customer->getAddress(),
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/customers/edit.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function updateCustomer(int $id): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'    => $_POST['name'] ?? '',
            'contact' => $_POST['contact'] ?? '',
            'address' => $_POST['address'] ?? '',
        ];

        $result = $this->partnerService->updateCustomer($id, $data);

        if (!$result['success']) {
            $title = 'Edit Pelanggan — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = array_merge($data, ['id' => $id]);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/customers/edit.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Data pelanggan '{$result['customer']->getName()}' berhasil diperbarui.");
        header('Location: /admin/customers');
        exit;
    }

    public function toggleCustomer(int $id): void
    {
        $this->enforceAdminOnly();

        $result = $this->partnerService->toggleCustomerStatus($id);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header('Location: /admin/customers');
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
                    <p style="color: var(--text-muted); margin-bottom: 2rem;">Akses ditolak di level server. Modul ini hanya untuk Administrator.</p>
                    <a href="/products" class="btn btn-primary">&larr; Kembali ke Katalog</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }
}
