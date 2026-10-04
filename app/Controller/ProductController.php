<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthSession;
use App\Service\CategoryService;
use App\Service\ProductService;

/**
 * Controller managing product catalog, multi-warehouse stock display, and Admin CRUD (PRD-01, WH-01).
 */
class ProductController
{
    public function __construct(
        private ProductService $productService,
        private CategoryService $categoryService
    ) {
    }

    /**
     * Product catalog accessible by Admin, Sales, and WarehouseStaff.
     */
    public function index(): void
    {
        $this->enforceAuthenticated();

        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : null;
        $categoryId = isset($_GET['category_id']) && (int) $_GET['category_id'] > 0 ? (int) $_GET['category_id'] : null;
        $stockStatus = isset($_GET['stock_status']) ? trim((string) $_GET['stock_status']) : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $pagination = $this->productService->getPaginatedCatalog($search, $categoryId, $stockStatus, $page, $perPage);
        $products = $pagination['items'];
        $total = $pagination['total'];
        $totalPages = $pagination['total_pages'];

        $categories = $this->categoryService->getAllCategories();

        $title = 'Katalog Produk & Inventaris — InventoryHub';
        $user = AuthSession::user();
        $success = AuthSession::getFlash('success');
        $error = AuthSession::getFlash('error');

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/products/index.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    /**
     * Product detail showing TOTAL stock and multi-warehouse breakdown (WH-01).
     * Accessible by Admin, Sales, and WarehouseStaff.
     */
    public function show(int $id): void
    {
        $this->enforceAuthenticated();

        $product = $this->productService->getProductWithWarehouseBreakdown($id);
        if ($product === null) {
            http_response_code(404);
            $title = '404 - Produk Tidak Ditemukan';
            $message = "Produk dengan ID #{$id} tidak ditemukan di sistem.";
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/errors/404.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        $title = "Detail Produk: {$product->getName()} ({$product->getSku()})";
        $user = AuthSession::user();

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/products/show.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function create(): void
    {
        $this->enforceAdminOnly();

        $categories = $this->categoryService->getAllCategories();
        $title = 'Tambah Produk Baru — Admin Panel';
        $errors = [];
        $old = [
            'sku'            => '',
            'name'           => '',
            'category_id'    => '',
            'unit'           => 'pcs',
            'purchase_price' => '0',
            'selling_price'  => '0',
            'reorder_point'  => '10',
            'is_active'      => '1',
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/products/create.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function store(): void
    {
        $this->enforceAdminOnly();

        $data = [
            'sku'            => $_POST['sku'] ?? '',
            'name'           => $_POST['name'] ?? '',
            'category_id'    => $_POST['category_id'] ?? 0,
            'unit'           => $_POST['unit'] ?? 'pcs',
            'purchase_price' => $_POST['purchase_price'] ?? 0,
            'selling_price'  => $_POST['selling_price'] ?? 0,
            'reorder_point'  => $_POST['reorder_point'] ?? 0,
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        $file = $_FILES['image'] ?? null;
        $result = $this->productService->createProduct($data, $file);

        if (!$result['success']) {
            $categories = $this->categoryService->getAllCategories();
            $title = 'Tambah Produk Baru — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = $data;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/products/create.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Produk '{$result['product']->getName()}' ({$result['product']->getSku()}) berhasil ditambahkan.");
        header('Location: /products');
        exit;
    }

    public function edit(int $id): void
    {
        $this->enforceAdminOnly();

        $product = $this->productService->getProductById($id);
        if ($product === null) {
            AuthSession::setFlash('error', 'Produk tidak ditemukan.');
            header('Location: /products');
            exit;
        }

        $categories = $this->categoryService->getAllCategories();
        $title = "Edit Produk: {$product->getName()}";
        $errors = [];
        $old = [
            'id'             => $product->getId(),
            'sku'            => $product->getSku(),
            'name'           => $product->getName(),
            'category_id'    => $product->getCategoryId(),
            'unit'           => $product->getUnit(),
            'purchase_price' => $product->getPurchasePrice(),
            'selling_price'  => $product->getSellingPrice(),
            'reorder_point'  => $product->getReorderPoint(),
            'image_path'     => $product->getImagePath(),
        ];

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/products/edit.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function update(int $id): void
    {
        $this->enforceAdminOnly();

        $data = [
            'sku'            => $_POST['sku'] ?? '',
            'name'           => $_POST['name'] ?? '',
            'category_id'    => $_POST['category_id'] ?? 0,
            'unit'           => $_POST['unit'] ?? 'pcs',
            'purchase_price' => $_POST['purchase_price'] ?? 0,
            'selling_price'  => $_POST['selling_price'] ?? 0,
            'reorder_point'  => $_POST['reorder_point'] ?? 0,
        ];

        $file = $_FILES['image'] ?? null;
        $result = $this->productService->updateProduct($id, $data, $file);

        if (!$result['success']) {
            $categories = $this->categoryService->getAllCategories();
            $title = 'Edit Produk — Admin Panel';
            $errors = $result['errors'] ?? [];
            $old = array_merge($data, ['id' => $id]);

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/products/edit.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        AuthSession::setFlash('success', "Data produk '{$result['product']->getName()}' berhasil diperbarui.");
        header("Location: /products/{$id}");
        exit;
    }

    public function toggleStatus(int $id): void
    {
        $this->enforceAdminOnly();

        $result = $this->productService->toggleStatus($id);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header('Location: /products');
        exit;
    }

    public function delete(int $id): void
    {
        $this->enforceAdminOnly();

        $result = $this->productService->deleteProduct($id);
        if ($result['success']) {
            AuthSession::setFlash('success', $result['message']);
        } else {
            AuthSession::setFlash('error', $result['message']);
        }

        header('Location: /products');
        exit;
    }

    private function enforceAuthenticated(): void
    {
        if (!AuthSession::isAuthenticated()) {
            AuthSession::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses katalog produk.');
            header('Location: /login');
            exit;
        }
    }

    private function enforceAdminOnly(): void
    {
        $this->enforceAuthenticated();

        if (!AuthSession::hasRole(User::ROLE_ADMIN)) {
            http_response_code(403);
            $title = '403 Forbidden — Akses Ditolak';
            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            echo '<div class="card" style="padding: 3.5rem; text-align: center; margin-top: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <h1 style="font-size: 2.2rem; color: var(--color-danger); margin-bottom: 0.75rem;">403 Forbidden</h1>
                    <p style="color: var(--text-muted); max-width: 550px; margin: 0 auto 2rem;">
                        Akses ditolak di level server. Perubahan data master produk hanya dapat dilakukan oleh <strong>Administrator</strong>. Peran Anda hanya memiliki akses baca (read-only).
                    </p>
                    <a href="/products" class="btn btn-primary">&larr; Kembali ke Katalog Produk</a>
                  </div>';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            exit;
        }
    }
}
