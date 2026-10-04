<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Warehouse;
use App\Repository\WarehouseRepositoryInterface;

/**
 * Business service managing warehouses (WH-01).
 */
class WarehouseService
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository
    ) {
    }

    /**
     * @return Warehouse[]
     */
    public function getAllWarehouses(): array
    {
        return $this->warehouseRepository->findAll();
    }

    public function getWarehouseById(int $id): ?Warehouse
    {
        return $this->warehouseRepository->findById($id);
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, warehouse?: Warehouse}
     */
    public function createWarehouse(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama gudang wajib diisi.';
        }
        if ($location === '') {
            $errors['location'] = 'Lokasi / alamat gudang wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $warehouse = new Warehouse(
            id: null,
            name: $name,
            location: $location,
            isActive: $isActive
        );

        $saved = $this->warehouseRepository->save($warehouse);

        return ['success' => true, 'warehouse' => $saved];
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, warehouse?: Warehouse}
     */
    public function updateWarehouse(int $id, array $data): array
    {
        $warehouse = $this->warehouseRepository->findById($id);
        if ($warehouse === null) {
            return ['success' => false, 'errors' => ['general' => 'Gudang tidak ditemukan.']];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama gudang wajib diisi.';
        }
        if ($location === '') {
            $errors['location'] = 'Lokasi / alamat gudang wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $warehouse->setName($name);
        $warehouse->setLocation($location);

        $saved = $this->warehouseRepository->save($warehouse);

        return ['success' => true, 'warehouse' => $saved];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function toggleStatus(int $id): array
    {
        $warehouse = $this->warehouseRepository->findById($id);
        if ($warehouse === null) {
            return ['success' => false, 'message' => 'Gudang tidak ditemukan.'];
        }

        $newStatus = !$warehouse->isActive();
        $warehouse->setIsActive($newStatus);
        $this->warehouseRepository->save($warehouse);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return [
            'success' => true,
            'message' => "Gudang '{$warehouse->getName()}' berhasil {$statusText}.",
        ];
    }
}
