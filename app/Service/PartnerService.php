<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Supplier;
use App\Repository\CustomerRepositoryInterface;
use App\Repository\SupplierRepositoryInterface;

/**
 * Business service managing business partners (Suppliers and Customers).
 */
class PartnerService
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository,
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    // ==========================================
    // Suppliers
    // ==========================================

    /**
     * @return Supplier[]
     */
    public function getAllSuppliers(): array
    {
        return $this->supplierRepository->findAll();
    }

    public function getSupplierById(int $id): ?Supplier
    {
        return $this->supplierRepository->findById($id);
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, supplier?: Supplier}
     */
    public function createSupplier(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $contact = trim((string) ($data['contact'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama supplier wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $supplier = new Supplier(
            id: null,
            name: $name,
            contact: $contact !== '' ? $contact : null,
            address: $address !== '' ? $address : null,
            isActive: $isActive
        );

        $saved = $this->supplierRepository->save($supplier);

        return ['success' => true, 'supplier' => $saved];
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, supplier?: Supplier}
     */
    public function updateSupplier(int $id, array $data): array
    {
        $supplier = $this->supplierRepository->findById($id);
        if ($supplier === null) {
            return ['success' => false, 'errors' => ['general' => 'Supplier tidak ditemukan.']];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $contact = trim((string) ($data['contact'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama supplier wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $supplier->setName($name);
        $supplier->setContact($contact !== '' ? $contact : null);
        $supplier->setAddress($address !== '' ? $address : null);

        $saved = $this->supplierRepository->save($supplier);

        return ['success' => true, 'supplier' => $saved];
    }

    public function toggleSupplierStatus(int $id): array
    {
        $supplier = $this->supplierRepository->findById($id);
        if ($supplier === null) {
            return ['success' => false, 'message' => 'Supplier tidak ditemukan.'];
        }

        $newStatus = !$supplier->isActive();
        $supplier->setIsActive($newStatus);
        $this->supplierRepository->save($supplier);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return [
            'success' => true,
            'message' => "Supplier '{$supplier->getName()}' berhasil {$statusText}.",
        ];
    }

    // ==========================================
    // Customers
    // ==========================================

    /**
     * @return Customer[]
     */
    public function getAllCustomers(): array
    {
        return $this->customerRepository->findAll();
    }

    public function getCustomerById(int $id): ?Customer
    {
        return $this->customerRepository->findById($id);
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, customer?: Customer}
     */
    public function createCustomer(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $contact = trim((string) ($data['contact'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama customer wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $customer = new Customer(
            id: null,
            name: $name,
            contact: $contact !== '' ? $contact : null,
            address: $address !== '' ? $address : null,
            isActive: $isActive
        );

        $saved = $this->customerRepository->save($customer);

        return ['success' => true, 'customer' => $saved];
    }

    /**
     * @return array{success: bool, errors?: array<string, string>, customer?: Customer}
     */
    public function updateCustomer(int $id, array $data): array
    {
        $customer = $this->customerRepository->findById($id);
        if ($customer === null) {
            return ['success' => false, 'errors' => ['general' => 'Customer tidak ditemukan.']];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $contact = trim((string) ($data['contact'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama customer wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $customer->setName($name);
        $customer->setContact($contact !== '' ? $contact : null);
        $customer->setAddress($address !== '' ? $address : null);

        $saved = $this->customerRepository->save($customer);

        return ['success' => true, 'customer' => $saved];
    }

    public function toggleCustomerStatus(int $id): array
    {
        $customer = $this->customerRepository->findById($id);
        if ($customer === null) {
            return ['success' => false, 'message' => 'Customer tidak ditemukan.'];
        }

        $newStatus = !$customer->isActive();
        $customer->setIsActive($newStatus);
        $this->customerRepository->save($customer);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return [
            'success' => true,
            'message' => "Customer '{$customer->getName()}' berhasil {$statusText}.",
        ];
    }
}
