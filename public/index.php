<?php

declare(strict_types=1);

/**
 * Front Controller & Composition Root
 * Inventory & Order Management System - Phase 1
 */

// Error reporting configuration: Log errors, never display raw traces to client (ERR-01)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// 1. PSR-4 Autoloading Setup
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Fallback native PSR-4 autoloader for App\ and Config\ namespaces
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

// 2. Load Environment Variables & Start Session
Config\Env::load(dirname(__DIR__) . '/.env');
App\Service\AuthSession::start();

// 3. Parse HTTP Request URI and Method
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$parsedUrl = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($parsedUrl ?: '/', '/');
if ($path === '') {
    $path = '/';
}

// 4. Composition Root (Dependency Injection)
$dbConfig = require dirname(__DIR__) . '/config/database.php';

$database = new App\Repository\Database(
    host: $dbConfig['host'],
    port: $dbConfig['port'],
    database: $dbConfig['database'],
    username: $dbConfig['username'],
    password: $dbConfig['password'],
    charset: $dbConfig['charset']
);

// Repositories
$userRepository = new App\Repository\MySQLUserRepository($database);
$pingRepository = new App\Repository\MySQLPingRepository($database);
$categoryRepository = new App\Repository\MySQLCategoryRepository($database);
$warehouseRepository = new App\Repository\MySQLWarehouseRepository($database);
$productRepository = new App\Repository\MySQLProductRepository($database);
$supplierRepository = new App\Repository\MySQLSupplierRepository($database);
$customerRepository = new App\Repository\MySQLCustomerRepository($database);
$stockRepository = new App\Repository\MySQLStockRepository($database);
$stockLedgerRepository = new App\Repository\MySQLStockLedgerRepository($database);
$poRepository = new App\Repository\MySQLPurchaseOrderRepository($database);
$soRepository = new App\Repository\MySQLSalesOrderRepository($database);

// Services
$authService = new App\Service\AuthService($userRepository);
$userService = new App\Service\UserService($userRepository);
$pingService = new App\Service\PingService($pingRepository);
$categoryService = new App\Service\CategoryService($categoryRepository);
$warehouseService = new App\Service\WarehouseService($warehouseRepository);
$uploadDir = dirname(__DIR__) . '/public/uploads/products';
$productService = new App\Service\ProductService($productRepository, $categoryRepository, $uploadDir);
$partnerService = new App\Service\PartnerService($supplierRepository, $customerRepository);
$poService = new App\Service\PurchaseOrderService(
    $database,
    $poRepository,
    $stockRepository,
    $stockLedgerRepository,
    $supplierRepository,
    $warehouseRepository,
    $productRepository
);
$soService = new App\Service\SalesOrderService(
    $database,
    $soRepository,
    $stockRepository,
    $stockLedgerRepository,
    $customerRepository,
    $warehouseRepository,
    $productRepository
);
$dashboardService = new App\Service\DashboardService(
    $productRepository,
    $poRepository,
    $soRepository
);
$reportService = new App\Service\ReportService(
    $stockLedgerRepository,
    $poRepository,
    $soRepository
);

// Controllers
$authController = new App\Controller\AuthController($authService);
$dashboardController = new App\Controller\DashboardController($dashboardService);
$userController = new App\Controller\UserController($userService);
$pingController = new App\Controller\PingController($pingService);
$productController = new App\Controller\ProductController($productService, $categoryService);
$categoryController = new App\Controller\CategoryController($categoryService);
$warehouseController = new App\Controller\WarehouseController($warehouseService);
$partnerController = new App\Controller\PartnerController($partnerService);
$purchaseOrderController = new App\Controller\PurchaseOrderController(
    $poService,
    $partnerService,
    $warehouseService,
    $productService
);
$salesOrderController = new App\Controller\SalesOrderController(
    $soService,
    $partnerService,
    $warehouseService,
    $productService
);
$apiController = new App\Controller\ApiController($productService);
$reportController = new App\Controller\ReportController($reportService);



// Development auto-login hook for testing and headless screenshot capture (ERR-01 safe)
if (\Config\Env::get('APP_ENV', 'production') === 'development' && isset($_GET['dev_login'])) {
    $devRole = strtolower((string) $_GET['dev_login']);
    $emailMap = [
        'admin'     => 'admin@inventory.local',
        'sales'     => 'sales1@inventory.local',
        'warehouse' => 'warehouse1@inventory.local',
    ];
    $devEmail = $emailMap[$devRole] ?? 'admin@inventory.local';
    $devUser = $userRepository->findByEmail($devEmail);
    if ($devUser) {
        App\Service\AuthSession::login($devUser);
    }
}

// 5. Auth Guard Helper
function guardAuth(array $allowedRoles = []): void
{
    if (!App\Service\AuthSession::isAuthenticated()) {
        App\Service\AuthSession::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        header('Location: /login');
        exit;
    }

    if (!empty($allowedRoles) && !App\Service\AuthSession::hasRole($allowedRoles)) {
        http_response_code(403);
        $title = '403 Forbidden — Akses Ditolak';
        $message = 'Akses ditolak di level server. Anda tidak memiliki izin untuk mengakses sumber daya ini.';
        require_once dirname(__DIR__) . '/views/layout/header.php';
        require_once dirname(__DIR__) . '/views/errors/403.php';
        require_once dirname(__DIR__) . '/views/layout/footer.php';
        exit;
    }
}

// 6. Router Dispatcher (Framework-less)
$routeKey = "{$requestMethod} {$path}";

try {
switch ($routeKey) {
    case 'GET /simulate-error-500':
        if (\Config\Env::get('APP_ENV', 'production') !== 'development') {
            http_response_code(404);
            $title = '404 - Not Found';
            $message = 'Halaman yang Anda minta tidak ditemukan di router sistem.';
            require_once dirname(__DIR__) . '/views/layout/header.php';
            require_once dirname(__DIR__) . '/views/errors/404.php';
            require_once dirname(__DIR__) . '/views/layout/footer.php';
            break;
        }
        throw new \RuntimeException('Simulated server error for ERR-01 testing');

    case 'GET /':
        if (App\Service\AuthSession::isAuthenticated()) {
            $role = App\Service\AuthSession::role();
            if ($role === 'Admin') {
                header('Location: /admin/dashboard');
            } elseif ($role === 'Sales') {
                header('Location: /sales/dashboard');
            } else {
                header('Location: /warehouse/dashboard');
            }
            exit;
        }
        $authController->showLoginForm();
        break;

    case 'GET /login':
        $authController->showLoginForm();
        break;

    case 'POST /login':
        $authController->login();
        break;

    case 'GET /logout':
    case 'POST /logout':
        $authController->logout();
        break;

    case 'GET /admin/dashboard':
        guardAuth(['Admin']);
        $dashboardController->adminDashboard();
        break;

    case 'GET /sales/dashboard':
        guardAuth(['Sales']);
        $dashboardController->salesDashboard();
        break;

    case 'GET /warehouse/dashboard':
        guardAuth(['WarehouseStaff']);
        $dashboardController->warehouseDashboard();
        break;

    case 'GET /admin/users':
        guardAuth(['Admin']);
        $userController->index();
        break;

    case 'GET /admin/users/create':
        guardAuth(['Admin']);
        $userController->create();
        break;

    case 'POST /admin/users/create':
        guardAuth(['Admin']);
        $userController->store();
        break;

    // --- Phase 2: Master Data Routes ---
    // Products (PRD-01, WH-01)
    case 'GET /products':
        $productController->index();
        break;

    case 'GET /admin/products/create':
        $productController->create();
        break;

    case 'POST /admin/products/create':
        $productController->store();
        break;

    // Categories
    case 'GET /admin/categories':
        $categoryController->index();
        break;

    case 'GET /admin/categories/create':
        $categoryController->create();
        break;

    case 'POST /admin/categories/create':
        $categoryController->store();
        break;

    // Warehouses (WH-01)
    case 'GET /admin/warehouses':
        $warehouseController->index();
        break;

    case 'GET /admin/warehouses/create':
        $warehouseController->create();
        break;

    case 'POST /admin/warehouses/create':
        $warehouseController->store();
        break;

    // Suppliers
    case 'GET /admin/suppliers':
        $partnerController->listSuppliers();
        break;

    case 'GET /admin/suppliers/create':
        $partnerController->createSupplier();
        break;

    case 'POST /admin/suppliers/create':
        $partnerController->storeSupplier();
        break;

    // Customers
    case 'GET /admin/customers':
        $partnerController->listCustomers();
        break;

    case 'GET /admin/customers/create':
        $partnerController->createCustomer();
        break;

    case 'POST /admin/customers/create':
        $partnerController->storeCustomer();
        break;

    // --- Phase 3: Purchase Orders & Goods Receipt (PO-01) ---
    case 'GET /purchase-orders':
        $purchaseOrderController->index();
        break;

    case 'GET /purchase-orders/create':
        $purchaseOrderController->create();
        break;

    case 'POST /purchase-orders/create':
        $purchaseOrderController->store();
        break;

    case 'GET /stock-ledger':
        $purchaseOrderController->stockLedger();
        break;

    // --- Phase 4: Sales Orders & Goods Issue (SO-01, ARCH-02) ---
    case 'GET /sales-orders':
        $salesOrderController->index();
        break;

    case 'GET /sales-orders/create':
        $salesOrderController->create();
        break;

    case 'POST /sales-orders/create':
        $salesOrderController->store();
        break;

    // System Health Ping

    case 'GET /ping':
        $pingController->index();
        break;

    case 'GET /api/ping':
        header('Content-Type: application/json; charset=utf-8');
        $result = $pingService->checkHealth();
        http_response_code($result->isAlive() ? 200 : 503);
        echo json_encode([
            'status' => $result->isAlive() ? 'healthy' : 'degraded',
            'data'   => $result->toArray(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;

    // --- Phase 5: Reports (REPORT-01) ---
    case 'GET /reports/stock-ledger/export':
        $reportController->exportStockLedger();
        break;

    case 'GET /reports/orders/export':
        $reportController->exportOrders();
        break;

    default:
        // API-01: Authenticated product availability per warehouse
        if (preg_match('#^GET /api/products/([^/]+)/availability$#', $routeKey, $matches)) {
            $apiController->productAvailability($matches[1]);
            break;
        }

        // Dynamic route matches: User Management
        if (preg_match('#^GET /admin/users/(\d+)/edit$#', $routeKey, $matches)) {
            guardAuth(['Admin']);
            $userController->edit((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/users/(\d+)/edit$#', $routeKey, $matches)) {
            guardAuth(['Admin']);
            $userController->update((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/users/(\d+)/toggle-status$#', $routeKey, $matches)) {
            guardAuth(['Admin']);
            $userController->toggleStatus((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Products (PRD-01, WH-01)
        if (preg_match('#^GET /products/(\d+)$#', $routeKey, $matches)) {
            $productController->show((int) $matches[1]);
            break;
        }

        if (preg_match('#^GET /admin/products/(\d+)/edit$#', $routeKey, $matches)) {
            $productController->edit((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/products/(\d+)/edit$#', $routeKey, $matches)) {
            $productController->update((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/products/(\d+)/toggle$#', $routeKey, $matches)) {
            $productController->toggleStatus((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/products/(\d+)/delete$#', $routeKey, $matches)) {
            $productController->delete((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Categories
        if (preg_match('#^GET /admin/categories/(\d+)/edit$#', $routeKey, $matches)) {
            $categoryController->edit((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/categories/(\d+)/edit$#', $routeKey, $matches)) {
            $categoryController->update((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/categories/(\d+)/delete$#', $routeKey, $matches)) {
            $categoryController->delete((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Warehouses (WH-01)
        if (preg_match('#^GET /admin/warehouses/(\d+)/edit$#', $routeKey, $matches)) {
            $warehouseController->edit((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/warehouses/(\d+)/edit$#', $routeKey, $matches)) {
            $warehouseController->update((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/warehouses/(\d+)/toggle$#', $routeKey, $matches)) {
            $warehouseController->toggleStatus((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/warehouses/(\d+)/delete$#', $routeKey, $matches)) {
            $warehouseController->delete((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Suppliers
        if (preg_match('#^GET /admin/suppliers/(\d+)/edit$#', $routeKey, $matches)) {
            $partnerController->editSupplier((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/suppliers/(\d+)/edit$#', $routeKey, $matches)) {
            $partnerController->updateSupplier((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/suppliers/(\d+)/toggle$#', $routeKey, $matches)) {
            $partnerController->toggleSupplier((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Customers
        if (preg_match('#^GET /admin/customers/(\d+)/edit$#', $routeKey, $matches)) {
            $partnerController->editCustomer((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/customers/(\d+)/edit$#', $routeKey, $matches)) {
            $partnerController->updateCustomer((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /admin/customers/(\d+)/toggle$#', $routeKey, $matches)) {
            $partnerController->toggleCustomer((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Purchase Orders & Goods Receipt (PO-01)
        if (preg_match('#^GET /purchase-orders/(\d+)$#', $routeKey, $matches)) {
            $purchaseOrderController->show((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /purchase-orders/(\d+)/order$#', $routeKey, $matches)) {
            $purchaseOrderController->markAsOrdered((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /purchase-orders/(\d+)/cancel$#', $routeKey, $matches)) {
            $purchaseOrderController->cancel((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /purchase-orders/(\d+)/receipt$#', $routeKey, $matches)) {
            $purchaseOrderController->processReceipt((int) $matches[1]);
            break;
        }

        // Dynamic route matches: Sales Orders & Goods Issue (SO-01, ARCH-02)
        if (preg_match('#^GET /sales-orders/(\d+)$#', $routeKey, $matches)) {
            $salesOrderController->show((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /sales-orders/(\d+)/submit-approval$#', $routeKey, $matches)) {
            $salesOrderController->submitForApproval((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /sales-orders/(\d+)/approve$#', $routeKey, $matches)) {
            $salesOrderController->approve((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /sales-orders/(\d+)/reject$#', $routeKey, $matches)) {
            $salesOrderController->reject((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /sales-orders/(\d+)/cancel$#', $routeKey, $matches)) {
            $salesOrderController->cancel((int) $matches[1]);
            break;
        }

        if (preg_match('#^POST /sales-orders/(\d+)/issue$#', $routeKey, $matches)) {
            $salesOrderController->processIssue((int) $matches[1]);
            break;
        }


        // 404 Not Found
        http_response_code(404);
        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'message' => "Endpoint {$path} does not exist.",
            ]);
        } else {
            $title = '404 - Not Found';
            $message = 'Halaman yang Anda minta tidak ditemukan di router sistem.';
            require_once dirname(__DIR__) . '/views/layout/header.php';
            require_once dirname(__DIR__) . '/views/errors/404.php';
            require_once dirname(__DIR__) . '/views/layout/footer.php';
        }
        break;
}
} catch (\Throwable $e) {
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    if (str_starts_with($path, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Terjadi kesalahan internal pada server.',
        ]);
    } else {
        $title = '500 - Kesalahan Server';
        require_once dirname(__DIR__) . '/views/layout/header.php';
        require_once dirname(__DIR__) . '/views/errors/500.php';
        require_once dirname(__DIR__) . '/views/layout/footer.php';
    }
    exit;
}
