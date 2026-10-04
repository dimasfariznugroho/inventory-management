<?php

declare(strict_types=1);

use Config\Env;

return [
    'host'     => (string) Env::get('DB_HOST', 'mysql'),
    'port'     => (int) Env::get('DB_PORT', 3306),
    'database' => (string) Env::get('DB_NAME', 'inventory_db'),
    'username' => (string) Env::get('DB_USER', 'inventory_user'),
    'password' => (string) Env::get('DB_PASS', 'secret'),
    'charset'  => 'utf8mb4',
];
