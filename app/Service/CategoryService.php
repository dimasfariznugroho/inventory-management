<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepositoryInterface;

/**
 * Business service managing product categories and validation rules.
 */
class CategoryService
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * @return Category[]
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }

    public function getCategoryById(int $id): ?Category
    {
        return $this->categoryRepository->findById($id);
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, category?: Category}
     */
    public function createCategory(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama kategori wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $category = new Category(
            id: null,
            name: $name,
            description: $description !== '' ? $description : null
        );

        $saved = $this->categoryRepository->save($category);

        return ['success' => true, 'category' => $saved];
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, category?: Category}
     */
    public function updateCategory(int $id, array $data): array
    {
        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            return ['success' => false, 'errors' => ['general' => 'Kategori tidak ditemukan.']];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama kategori wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $category->setName($name);
        $category->setDescription($description !== '' ? $description : null);

        $saved = $this->categoryRepository->save($category);

        return ['success' => true, 'category' => $saved];
    }

    /**
     * Delete category with Foreign Key protection check.
     * Prevents raw MySQL constraint errors when category is still in use by products.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteCategory(int $id): array
    {
        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            return ['success' => false, 'message' => 'Kategori tidak ditemukan.'];
        }

        // Integrity check: prevent deleting category if products exist
        if ($this->categoryRepository->isUsedByProducts($id)) {
            return [
                'success' => false,
                'message' => 'Kategori masih digunakan oleh produk lain. Ubah atau hapus produk terkait terlebih dahulu.',
            ];
        }

        $this->categoryRepository->delete($id);

        return [
            'success' => true,
            'message' => "Kategori '{$category->getName()}' berhasil dihapus.",
        ];
    }
}
