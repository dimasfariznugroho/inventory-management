<?php

declare(strict_types=1);

namespace Config;

/**
 * Lightweight environment variable loader for native PHP without external packages.
 */
class Env
{
    private static bool $loaded = false;

    public static function load(string $filePath): void
    {
        if (self::$loaded || !file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $val = trim($parts[1]);

            // Strip surrounding quotes
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            // Populate $_ENV, $_SERVER, and putenv
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $val;
            }
            putenv("{$key}={$val}");
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default;
        }
        return $val;
    }
}
