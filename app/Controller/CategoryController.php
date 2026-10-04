<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\CategoryService;

/**
 * Controller handling Category CRUD for Administrators.
 */
class CategoryController
{
    public function __construct(
        private CategoryService $categoryService
    ) {
    }

    public function index(): void
    {
        $this->enforceAdminOnly();

        $title = 'Kategori Produk — Admin Panel';
        $categories = $this->categoryService->getAllCategories();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/categories/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function create(): void
    {
        $this->enforceAdminOnly();

        $title = 'Tambah Kategori — Admin Panel';
        $errors = [];
        $old = ['name' => '', 'description' => ''];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/categories/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function store(): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'        => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
        ];

        $result = $this->categoryService->createCategory($data);

        if (!$result['success']) {
            $title = 'Tambah Kategori — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/categories/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Kategori '{$result['category']->getName()}' berhasil ditambahkan.");
        header('Location: /admin/categories');
        exit;
    }

    public function edit(int $id): void
    {
        $this->enforceAdminOnly();

        $category = $this->categoryService->getCategoryById($id);
        if ($category === null) {
            AuthSession::setFlash('error', 'Kategori tidak ditemukan.');
            header('Location: /admin/categories');
            exit;
        }

        $title = "Edit Kategori: {$category->getName()}";
        $errors = [];
        $old = [
            'id'          => $category->getId(),
            'name'        => $category->getName(),
            'description' => $category->getDescription(),
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/categories/edit.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function update(int $id): void
    {
        $this->enforceAdminOnly();

        $data = [
            'name'        => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
        ];

        $result = $this->categoryService->updateCategory($id, $data);

        if (!$result['success']) {
            $title = 'Edit Kategori — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = array_merge($data, ['id' => $id]);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/categories/edit.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Kategori '{$result['category']->getName()}' berhasil diperbarui.");
        header('Location: /admin/categories');
        exit;
    }

    public function delete(int $id): void
    {
        $this->enforceAdminOnly();

        $result = $this->categoryService->deleteCategory($id);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header('Location: /admin/categories');
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
