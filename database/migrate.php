<?php

declare(strict_types=1);

/**
 * Standalone Database Migration & Seed CLI runner.
 * Usage: php database/migrate.php
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/app/Repository/Database.php';

Config\Env::load(dirname(__DIR__) . '/.env');
$dbConfig = require dirname(__DIR__) . '/config/database.php';

// Parse CLI flags: --host=, --port=, --user=, --pass[=], --database=
$options = getopt('', ['host:', 'port:', 'user:', 'pass::', 'database:']);

$host = (string) ($options['host'] ?? $dbConfig['host']);
$port = (int) ($options['port'] ?? $dbConfig['port']);
$databaseName = (string) ($options['database'] ?? $dbConfig['database']);
$username = (string) ($options['user'] ?? $dbConfig['username']);

if (array_key_exists('pass', $options)) {
    $password = is_string($options['pass']) ? trim($options['pass'], "'\"") : '';
} else {
    $password = (string) $dbConfig['password'];
}

// Fallback to 127.0.0.1 if running on host where 'mysql' hostname is not resolvable
if ($host === 'mysql' && gethostbyname('mysql') === 'mysql') {
    $host = '127.0.0.1';
}

echo "=== Running Database Schema & Seed Migration ===\n";
echo "Connecting to MySQL ({$host}:{$port}), Database: {$databaseName}, User: {$username}...\n";

try {
    $database = new App\Repository\Database(
        host: $host,
        port: $port,
        database: $databaseName,
        username: $username,
        password: $password,
        charset: $dbConfig['charset']
    );

    $pdo = $database->getConnection();
    echo "Connected successfully!\n";

    $sqlFile = __DIR__ . '/schema-and-seed.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("SQL file not found: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Failed to read {$sqlFile}");
    }

    // Execute multi-query script
    $pdo->exec($sql);

    echo "Schema and seed data executed successfully!\n";

    // Verify users count
    $stmt = $pdo->query('SELECT COUNT(*) AS total_users FROM users');
    $res = $stmt->fetch();
    echo "Total seeded users in database: " . ($res['total_users'] ?? 0) . "\n";
    echo "=== Migration Complete ===\n";
} catch (Throwable $e) {
    echo "Migration failed with error: " . $e->getMessage() . "\n";
    exit(1);
}
