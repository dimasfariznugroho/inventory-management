<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthService;
use App\Service\AuthSession;

/**
 * Controller handling authentication flows (Login, Logout, Session management).
 */
class AuthController
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function showLoginForm(): void
    {
        if (AuthSession::isAuthenticated()) {
            $this->redirectByRole(AuthSession::role() ?? '');
            return;
        }

        $title = 'Login — Inventory & Order Management System';
        $error = AuthSession::getFlash('error');
        $success = AuthSession::getFlash('success');
        $oldEmail = '';

        require_once dirname(__DIR__, 2) . '/views/layout/header.php';
        require_once dirname(__DIR__, 2) . '/views/auth/login.php';
        require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
    }

    public function login(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $result = $this->authService->attemptLogin($email, $password);

        if (!$result['success']) {
            $title = 'Login — Inventory & Order Management System';
            $error = $result['message'] ?? 'Login gagal.';
            $success = null;
            $oldEmail = $email;

            require_once dirname(__DIR__, 2) . '/views/layout/header.php';
            require_once dirname(__DIR__, 2) . '/views/auth/login.php';
            require_once dirname(__DIR__, 2) . '/views/layout/footer.php';
            return;
        }

        /** @var User $user */
        $user = $result['user'];
        AuthSession::login($user);
        AuthSession::setFlash('success', "Selamat datang kembali, {$user->getName()}!");

        $this->redirectByRole($user->getRole());
    }

    public function logout(): void
    {
        AuthSession::logout();
        AuthSession::setFlash('success', 'Anda telah berhasil logout.');
        header('Location: /login');
        exit;
    }

    private function redirectByRole(string $role): void
    {
        switch ($role) {
            case User::ROLE_ADMIN:
                header('Location: /admin/dashboard');
                exit;
            case User::ROLE_SALES:
                header('Location: /sales/dashboard');
                exit;
            case User::ROLE_WAREHOUSE_STAFF:
                header('Location: /warehouse/dashboard');
                exit;
            default:
                header('Location: /login');
                exit;
        }
    }
}
