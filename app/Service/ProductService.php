<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;

/**
 * Business service managing products, image uploads, order-safety rules, and stock inspection (PRD-01, WH-01).
 */
class ProductService
{
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_IMAGE_SIZE = 2097152; // 2MB

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * @return Product[]
     */
    public function getCatalog(?string $search = null, ?int $categoryId = null, ?string $stockStatus = null): array
    {
        return $this->productRepository->findAllWithStock($search, $categoryId, $stockStatus, 1, 1000);
    }

    /**
     * @return array{items: Product[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function getPaginatedCatalog(
        ?string $search = null,
        ?int $categoryId = null,
        ?string $stockStatus = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $total = $this->productRepository->countAllWithStock($search, $categoryId, $stockStatus);
        $items = $this->productRepository->findAllWithStock($search, $categoryId, $stockStatus, $page, $perPage);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, $totalPages),
        ];
    }

    public function getProductAvailabilityBySku(string $sku): ?array
    {
        $product = $this->productRepository->findBySkuWithWarehouseBreakdown($sku);
        if ($product === null) {
            return null;
        }

        $warehouses = [];
        foreach ($product->getWarehouseStocks() as $stock) {
            $warehouses[] = [
                'warehouse_id'   => $stock->getWarehouseId(),
                'warehouse_name' => $stock->getWarehouseName(),
                'quantity'       => $stock->getQuantity(),
            ];
        }

        $totalStock = $product->getTotalStock();
        $reorderPoint = $product->getReorderPoint();

        return [
            'sku'           => $product->getSku(),
            'name'          => $product->getName(),
            'category'      => $product->getCategoryName(),
            'unit'          => $product->getUnit(),
            'total_stock'   => $totalStock,
            'reorder_point' => $reorderPoint,
            'stock_status'  => $totalStock <= $reorderPoint ? 'low_stock' : 'normal',
            'warehouses'    => $warehouses,
        ];
    }

    public function getProductWithWarehouseBreakdown(int $id): ?Product
    {
        return $this->productRepository->findByIdWithWarehouseBreakdown($id);
    }

    public function getProductById(int $id): ?Product
    {
        return $this->productRepository->findById($id);
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, product?: Product}
     */
    public function createProduct(array $data, ?array $file = null): array
    {
        $errors = [];

        $sku = trim((string) ($data['sku'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $categoryId = (int) ($data['category_id'] ?? 0);
        $unit = trim((string) ($data['unit'] ?? 'pcs'));
        $purchasePrice = (float) ($data['purchase_price'] ?? 0.0);
        $sellingPrice = (float) ($data['selling_price'] ?? 0.0);
        $reorderPoint = (int) ($data['reorder_point'] ?? 0);
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        if ($sku === '') {
            $errors['sku'] = 'SKU produk wajib diisi.';
        } elseif ($this->productRepository->skuExists($sku)) {
            $errors['sku'] = 'SKU sudah digunakan oleh produk lain.';
        }

        if ($name === '') {
            $errors['name'] = 'Nama produk wajib diisi.';
        }

        if ($categoryId <= 0 || $this->categoryRepository->findById($categoryId) === null) {
            $errors['category_id'] = 'Kategori yang dipilih tidak valid.';
        }

        if ($unit === '') {
            $errors['unit'] = 'Satuan unit produk wajib diisi.';
        }

        if ($purchasePrice < 0) {
            $errors['purchase_price'] = 'Harga beli tidak boleh negatif.';
        }

        if ($sellingPrice < 0) {
            $errors['selling_price'] = 'Harga jual tidak boleh negatif.';
        }

        if ($reorderPoint < 0) {
            $errors['reorder_point'] = 'Reorder point tidak boleh negatif.';
        }

        $imagePath = null;
        if ($file !== null && isset($file['tmp_name']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->handleImageUpload($file);
            if (!$uploadResult['success']) {
                $errors['image'] = $uploadResult['error'];
            } else {
                $imagePath = $uploadResult['path'];
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $product = new Product(
            id: null,
            sku: $sku,
            name: $name,
            categoryId: $categoryId,
            unit: $unit,
            purchasePrice: $purchasePrice,
            sellingPrice: $sellingPrice,
            reorderPoint: $reorderPoint,
            imagePath: $imagePath,
            isActive: $isActive
        );

        $saved = $this->productRepository->save($product);

        return ['success' => true, 'product' => $saved];
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, product?: Product}
     */
    public function updateProduct(int $id, array $data, ?array $file = null): array
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            return ['success' => false, 'errors' => ['general' => 'Produk tidak ditemukan.']];
        }

        $errors = [];

        $sku = trim((string) ($data['sku'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $categoryId = (int) ($data['category_id'] ?? 0);
        $unit = trim((string) ($data['unit'] ?? 'pcs'));
        $purchasePrice = (float) ($data['purchase_price'] ?? 0.0);
        $sellingPrice = (float) ($data['selling_price'] ?? 0.0);
        $reorderPoint = (int) ($data['reorder_point'] ?? 0);

        if ($sku === '') {
            $errors['sku'] = 'SKU produk wajib diisi.';
        } elseif ($this->productRepository->skuExists($sku, $id)) {
            $errors['sku'] = 'SKU sudah digunakan oleh produk lain.';
        }

        if ($name === '') {
            $errors['name'] = 'Nama produk wajib diisi.';
        }

        if ($categoryId <= 0 || $this->categoryRepository->findById($categoryId) === null) {
            $errors['category_id'] = 'Kategori yang dipilih tidak valid.';
        }

        if ($unit === '') {
            $errors['unit'] = 'Satuan unit produk wajib diisi.';
        }

        if ($purchasePrice < 0) {
            $errors['purchase_price'] = 'Harga beli tidak boleh negatif.';
        }

        if ($sellingPrice < 0) {
            $errors['selling_price'] = 'Harga jual tidak boleh negatif.';
        }

        if ($reorderPoint < 0) {
            $errors['reorder_point'] = 'Reorder point tidak boleh negatif.';
        }

        $imagePath = $product->getImagePath();
        if ($file !== null && isset($file['tmp_name']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->handleImageUpload($file);
            if (!$uploadResult['success']) {
                $errors['image'] = $uploadResult['error'];
            } else {
                // Delete old image if existed
                $this->deletePhysicalFile($product->getImagePath());
                $imagePath = $uploadResult['path'];
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $product->setSku($sku);
        $product->setName($name);
        $product->setCategoryId($categoryId);
        $product->setUnit($unit);
        $product->setPurchasePrice($purchasePrice);
        $product->setSellingPrice($sellingPrice);
        $product->setReorderPoint($reorderPoint);
        $product->setImagePath($imagePath);

        $saved = $this->productRepository->save($product);

        return ['success' => true, 'product' => $saved];
    }

    /**
     * Toggle active/deactive status.
     *
     * @return array{success: bool, message: string}
     */
    public function toggleStatus(int $id): array
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan.'];
        }

        $newStatus = !$product->isActive();
        $product->setIsActive($newStatus);
        $this->productRepository->save($product);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return [
            'success' => true,
            'message' => "Produk '{$product->getName()}' ({$product->getSku()}) berhasil {$statusText}.",
        ];
    }

    /**
     * Delete product with soft status protection:
     * If used in PO or SO, permanent delete is strictly prohibited (PRD-01).
     *
     * @return array{success: bool, message: string}
     */
    public function deleteProduct(int $id): array
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan.'];
        }

        if ($this->productRepository->isUsedInOrders($id)) {
            return [
                'success' => false,
                'message' => 'Produk sudah tercatat dalam riwayat Purchase Order atau Sales Order. Produk tidak boleh dihapus permanen, silakan nonaktifkan statusnya.',
            ];
        }

        $this->deletePhysicalFile($product->getImagePath());
        $this->productRepository->delete($id);

        return [
            'success' => true,
            'message' => "Produk '{$product->getName()}' ({$product->getSku()}) berhasil dihapus permanen.",
        ];
    }

    /**
     * Secure image upload validation and random file naming.
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    private function handleImageUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Terjadi kesalahan saat mengunggah file gambar (Error code: ' . $file['error'] . ').'];
        }

        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            return ['success' => false, 'error' => 'Ukuran file gambar maksimal 2MB.'];
        }

        if (!file_exists($file['tmp_name'])) {
            return ['success' => false, 'error' => 'File sementara tidak ditemukan di server.'];
        }

        // Validate real MIME type via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_IMAGE_TYPES[$mimeType])) {
            return ['success' => false, 'error' => 'Tipe file tidak diizinkan. Hanya format JPG, PNG, dan WebP yang diperbolehkan.'];
        }

        $extension = self::ALLOWED_IMAGE_TYPES[$mimeType];

        // Generate randomized filename to avoid collision and predictability
        $randomName = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/products';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $destination = $uploadDir . '/' . $randomName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            // Fallback for non-HTTP uploads in CLI testing
            if (!copy($file['tmp_name'], $destination)) {
                return ['success' => false, 'error' => 'Gagal memindahkan file ke direktori tujuan.'];
            }
        }

        return [
            'success' => true,
            'path'    => '/uploads/products/' . $randomName,
        ];
    }

    private function deletePhysicalFile(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }

        $fullPath = dirname(__DIR__, 2) . '/public' . $relativePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
