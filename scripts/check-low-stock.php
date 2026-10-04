<?php

declare(strict_types=1);

/**
 * Standalone CLI Script: Check Low Stock Products (JOB-01)
 *
 * Can be executed manually via CLI:
 *   php scripts/check-low-stock.php
 * or inside Docker container:
 *   docker compose exec app php scripts/check-low-stock.php
 */

// 1. PSR-4 Autoloading
$autoloadPaths = [
    dirname(__DIR__) . '/vendor/autoload.php',
];
foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        break;
    }
}

// Fallback native PSR-4 autoloader
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\'    => dirname(__DIR__) . '/app/',
        'Config\\' => dirname(__DIR__) . '/config/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 2. Load Environment Variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    Config\Env::load($envFile);
}

// 3. Database Connection Configuration & Smart Resolution
$options = getopt('', ['host:', 'port:', 'user:', 'pass::', 'database:']);

$host = (string) ($options['host'] ?? (getenv('DB_HOST') ?: Config\Env::get('DB_HOST', '127.0.0.1')));
$port = (int) ($options['port'] ?? (getenv('DB_PORT') ?: Config\Env::get('DB_PORT', 3306)));
$database = (string) ($options['database'] ?? (getenv('DB_NAME') ?: Config\Env::get('DB_NAME', 'inventory_db')));
$username = (string) ($options['user'] ?? (getenv('DB_USER') ?: Config\Env::get('DB_USER', 'inventory_user')));

if (array_key_exists('pass', $options)) {
    $password = is_string($options['pass']) ? trim($options['pass'], "'\"") : '';
} else {
    $password = (string) (getenv('DB_PASS') ?: Config\Env::get('DB_PASS', 'secret'));
}
$charset = (string) (getenv('DB_CHARSET') ?: 'utf8mb4');

// Fallback to 127.0.0.1 if running on host where 'mysql' hostname is not resolvable
if ($host === 'mysql' && gethostbyname('mysql') === 'mysql') {
    $host = '127.0.0.1';
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "[ERROR] Gagal terhubung ke database MySQL ({$host}:{$port}/{$database}): " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// 4. Query Total Registered Active Products
$totalStmt = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1');
$totalActiveProducts = (int) $totalStmt->fetchColumn();

// 5. Query Products Below Reorder Point
$sql = "
    SELECT p.id,
           p.sku,
           p.name,
           COALESCE(c.name, '-') AS category_name,
           p.reorder_point,
           COALESCE(SUM(ps.quantity), 0) AS total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    WHERE p.is_active = 1
    GROUP BY p.id, p.sku, p.name, c.name, p.reorder_point
    HAVING total_stock <= p.reorder_point
    ORDER BY (p.reorder_point - total_stock) DESC, p.id ASC
";

$stmt = $pdo->query($sql);
$lowStockProducts = $stmt->fetchAll();

$lowStockCount = count($lowStockProducts);
$outOfStockCount = 0;
foreach ($lowStockProducts as $prod) {
    if ((int) $prod['total_stock'] === 0) {
        $outOfStockCount++;
    }
}

// 6. Format and Output Terminal Report
$now = date('Y-m-d H:i:s');
echo "==========================================================================================" . PHP_EOL;
echo " INVENTORY MANAGEMENT SYSTEM — LAPORAN PERIKSA STOK RENDAH (JOB-01)" . PHP_EOL;
echo " Waktu Pemeriksaan : {$now}" . PHP_EOL;
echo " Target Database    : {$database}@{$host}:{$port}" . PHP_EOL;
echo "==========================================================================================" . PHP_EOL;

if ($lowStockCount === 0) {
    echo " [OK] Semua produk saat ini memiliki stok di atas batas reorder point." . PHP_EOL;
    echo " Tidak ada tindakan reorder mendesak yang diperlukan." . PHP_EOL;
} else {
    echo " [!] PERINGATAN: Ditemukan {$lowStockCount} produk yang stok fisiknya <= batas reorder point!" . PHP_EOL . PHP_EOL;

    // Table Header
    printf(
        "+----+-------------+------------------------------------+---------------------+---------+-------+---------+----------+%s",
        PHP_EOL
    );
    printf(
        "| %-2s | %-11s | %-34s | %-19s | %7s | %5s | %7s | %-8s |%s",
        "ID",
        "SKU",
        "Nama Produk",
        "Kategori",
        "Reorder",
        "Stok",
        "Defisit",
        "Status",
        PHP_EOL
    );
    printf(
        "+----+-------------+------------------------------------+---------------------+---------+-------+---------+----------+%s",
        PHP_EOL
    );

    foreach ($lowStockProducts as $row) {
        $id = (int) $row['id'];
        $sku = (string) $row['sku'];
        $name = mb_strimwidth((string) $row['name'], 0, 34, '...');
        $cat = mb_strimwidth((string) $row['category_name'], 0, 19, '...');
        $reorder = (int) $row['reorder_point'];
        $stock = (int) $row['total_stock'];
        $deficit = $reorder - $stock;
        $status = ($stock === 0) ? 'HABIS' : 'MENIPIS';

        printf(
            "| %2d | %-11s | %-34s | %-19s | %7d | %5d | %7d | %-8s |%s",
            $id,
            $sku,
            $name,
            $cat,
            $reorder,
            $stock,
            $deficit,
            $status,
            PHP_EOL
        );
    }

    printf(
        "+----+-------------+------------------------------------+---------------------+---------+-------+---------+----------+%s",
        PHP_EOL
    );
}

echo PHP_EOL;
echo "RINGKASAN OPERASIONAL:" . PHP_EOL;
echo " - Total Produk Aktif Terdaftar  : {$totalActiveProducts} produk" . PHP_EOL;
echo " - Produk Perlu Reorder / Kritis : {$lowStockCount} produk" . PHP_EOL;
echo " - Produk Habis Total (Stok 0)   : {$outOfStockCount} produk" . PHP_EOL;

if ($lowStockCount > 0) {
    echo " - Rekomendasi                   : Harap segera terbitkan Purchase Order (PO) ke pemasok" . PHP_EOL;
    echo "                                   terkait untuk memulihkan stok ke level aman." . PHP_EOL;
}
echo "==========================================================================================" . PHP_EOL;

exit(0);
