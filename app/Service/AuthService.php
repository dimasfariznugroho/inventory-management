<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

/**
 * Business service handling authentication verification rules.
 */
class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Attempt login verification with strict business rules.
     *
     * @return array{success: bool, message?: string, user?: User}
     */
    public function attemptLogin(string $email, string $password): array
    {
        $email = trim($email);

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Email dan password wajib diisi.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Format email tidak valid.',
            ];
        }

        $user = $this->userRepository->findByEmail($email);

        // Generic error message for non-existent email or invalid password (AUTH-01)
        if ($user === null || !password_verify($password, $user->getPassword())) {
            return [
                'success' => false,
                'message' => 'Email atau password yang Anda masukkan salah.',
            ];
        }

        // Deactivated user check (AUTH-01 requirement)
        if (!$user->isActive()) {
            return [
                'success' => false,
                'message' => 'Akun Anda berstatus nonaktif. Silakan hubungi Administrator.',
            ];
        }

        return [
            'success' => true,
            'user'    => $user,
        ];
    }
}
