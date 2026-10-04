<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

/**
 * Business service managing user CRUD operations and business invariants.
 */
class UserService
{
    private const ALLOWED_ROLES = [
        User::ROLE_ADMIN,
        User::ROLE_SALES,
        User::ROLE_WAREHOUSE_STAFF,
    ];

    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * @return User[]
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findAll();
    }

    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Create a new user with server-side validation.
     *
     * @return array{success: bool, errors?: array<string, string>, user?: User}
     */
    public function createUser(array $data): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = trim((string) ($data['role'] ?? ''));
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        if ($name === '') {
            $errors['name'] = 'Nama lengkap wajib diisi.';
        }

        if ($email === '') {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        } elseif ($this->userRepository->emailExists($email)) {
            $errors['email'] = 'Email sudah digunakan oleh user lain.';
        }

        if ($password === '') {
            $errors['password'] = 'Password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password minimal 6 karakter.';
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $errors['role'] = 'Role yang dipilih tidak valid.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $user = new User(
            id: null,
            name: $name,
            email: $email,
            password: $hashedPassword,
            role: $role,
            isActive: $isActive
        );

        $savedUser = $this->userRepository->save($user);

        return [
            'success' => true,
            'user'    => $savedUser,
        ];
    }

    /**
     * Update an existing user with email uniqueness check.
     *
     * @return array{success: bool, errors?: array<string, string>, user?: User}
     */
    public function updateUser(int $id, array $data): array
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return [
                'success' => false,
                'errors'  => ['general' => 'User tidak ditemukan.'],
            ];
        }

        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = trim((string) ($data['role'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Nama lengkap wajib diisi.';
        }

        if ($email === '') {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        } elseif ($this->userRepository->emailExists($email, $id)) {
            $errors['email'] = 'Email sudah digunakan oleh user lain.';
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $errors['role'] = 'Role yang dipilih tidak valid.';
        }

        // Optional password update: if provided, must be at least 6 characters
        if ($password !== '' && strlen($password) < 6) {
            $errors['password'] = 'Password baru minimal 6 karakter.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        $user->setName($name);
        $user->setEmail($email);
        $user->setRole($role);

        if ($password !== '') {
            $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        }

        $savedUser = $this->userRepository->save($user);

        return [
            'success' => true,
            'user'    => $savedUser,
        ];
    }

    /**
     * Toggle active/deactive status with self-deactivation protection.
     *
     * @return array{success: bool, message: string}
     */
    public function toggleStatus(int $targetUserId, int $currentAdminId): array
    {
        if ($targetUserId === $currentAdminId) {
            return [
                'success' => false,
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ];
        }

        $user = $this->userRepository->findById($targetUserId);
        if ($user === null) {
            return [
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ];
        }

        $newStatus = !$user->isActive();
        $user->setIsActive($newStatus);
        $this->userRepository->save($user);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return [
            'success' => true,
            'message' => "User {$user->getName()} berhasil {$statusText}.",
        ];
    }
}
