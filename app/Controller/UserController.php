<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\UserService;

/**
 * Controller handling Admin User Management (USR-01).
 * Server-side authorization check strictly enforced on every action.
 */
class UserController
{
    public function __construct(
        private UserService $userService
    ) {
    }

    public function index(): void
    {
        $this->enforceAdminOnly();

        $title = 'Manajemen Pengguna — Admin Panel';
        $users = $this->userService->getAllUsers();
        $currentUser = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/users/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function create(): void
    {
        $this->enforceAdminOnly();

        $title = 'Tambah Pengguna Baru — Admin Panel';
        $errors = [];
        $old = [
            'name'      => '',
            'email'     => '',
            'role'      => User::ROLE_SALES,
            'is_active' => '1',
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/users/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function store(): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'      => $_POST['name'] ?? '',
            'email'     => $_POST['email'] ?? '',
            'password'  => $_POST['password'] ?? '',
            'role'      => $_POST['role'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $result = $this->userService->createUser($data);

        if (!$result['success']) {
            $title = 'Tambah Pengguna Baru — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/users/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Pengguna '{$result['user']->getName()}' berhasil ditambahkan.");
        header('Location: /admin/users');
        exit;
    }

    public function edit(int $id): void
    {
        $this->enforceAdminOnly();

        $user = $this->userService->getUserById($id);
        if ($user === null) {
            AuthSession::setFlash('error', 'Pengguna tidak ditemukan.');
            header('Location: /admin/users');
            exit;
        }

        $title = "Edit Pengguna: {$user->getName()} — Admin Panel";
        $errors = [];
        $old = [
            'id'    => $user->getId(),
            'name'  => $user->getName(),
            'email' => $user->getEmail(),
            'role'  => $user->getRole(),
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/users/edit.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function update(int $id): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'     => $_POST['name'] ?? '',
            'email'    => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'role'     => $_POST['role'] ?? '',
        ];

        $result = $this->userService->updateUser($id, $data);

        if (!$result['success']) {
            $title = 'Edit Pengguna — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = array_merge($data, ['id' => $id]);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/users/edit.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Data pengguna '{$result['user']->getName()}' berhasil diperbarui.");
        header('Location: /admin/users');
        exit;
    }

    public function toggleStatus(int $id): void
    {
        $this->enforceAdminOnly();

        $currentAdminId = AuthSession::id() ?? 0;
        $result = $this->userService->toggleStatus($id, $currentAdminId);

        if (!$result['success']) {
            AuthSession::setFlash('error', $result['message']);
        } else {
            AuthSession::setFlash('success', $result['message']);
        }

        header('Location: /admin/users');
        exit;
    }

    private function enforceAdminOnly(): void
    {
        if (!AuthSession::isAuthenticated()) {
            AuthSession::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
            header('Location: /login');
            exit;
        }

        if (!AuthSession::hasRole(User::ROLE_ADMIN)) {
            http_response_code(403);
            $title = '403 Forbidden — Akses Ditolak';
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            echo '<div class="card" style="padding: 3.5rem; text-align: center; margin-top: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <h1 style="font-size: 2.2rem; color: var(--color-danger); margin-bottom: 0.75rem;">403 Forbidden</h1>
                    <p style="color: var(--text-muted); max-width: 550px; margin: 0 auto 2rem;">
                        Akses ditolak di level server. Halaman manajemen pengguna hanya dapat diakses oleh peran <strong>Administrator</strong>.
                    </p>
                    <a href="/login" class="btn btn-primary">&larr; Kembali ke Dashboard Anda</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }
}
