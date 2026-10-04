<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Manages native PHP session lifecycle, security cookies, and authentication state.
 */
class AuthSession
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        }
    }

    public static function login(User $user): void
    {
        self::start();
        // Prevent session fixation attack (AUTH-01 requirement)
        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id'        => $user->getId(),
            'name'      => $user->getName(),
            'email'     => $user->getEmail(),
            'role'      => $user->getRole(),
            'is_active' => $user->isActive(),
        ];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['auth_user'] ?? null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user ? (string) $user['role'] : null;
    }

    public static function isAuthenticated(): bool
    {
        return self::user() !== null;
    }

    public static function hasRole(string|array $roles): bool
    {
        $currentRole = self::role();
        if (!$currentRole) {
            return false;
        }

        if (is_string($roles)) {
            return $currentRole === $roles;
        }

        return in_array($currentRole, $roles, true);
    }

    public static function setFlash(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }

    public static function getFlash(string $type): ?string
    {
        self::start();
        if (isset($_SESSION['flash'][$type])) {
            $msg = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $msg;
        }
        return null;
    }
}
