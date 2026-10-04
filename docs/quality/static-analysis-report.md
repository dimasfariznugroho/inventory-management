# Static Analysis Report: PHPStan Level 5+

- **Tool**: PHPStan (PHP Static Analysis Tool)
- **Version**: 1.12.34
- **Analysis Level**: Level 5
- **Target Directory**: `app/` (Controllers, Entities, Repositories, Services)
- **Date Executed**: 2026-09-25
- **Configuration**: `phpstan.neon`

## Executive Summary

| Category | Total Files Inspected | Errors Detected | Status |
| :--- | :---: | :---: | :---: |
| **Entities** | 13 | 0 | PASSED |
| **Repositories & Interfaces** | 23 | 0 | PASSED |
| **Domain Services** | 12 | 0 | PASSED |
| **HTTP & API Controllers** | 12 | 0 | PASSED |
| **Overall Codebase** | **60** | **0** | **PASSED (100% CLEAN)** |

## PHPStan Execution Command

```bash
docker compose exec web vendor/bin/phpstan analyse --debug --memory-limit=512M
```

## Detailed Output

```text
Note: Using configuration file /var/www/html/phpstan.neon.
/var/www/html/app/Controller/ApiController.php
/var/www/html/app/Controller/AuthController.php
/var/www/html/app/Controller/CategoryController.php
/var/www/html/app/Controller/DashboardController.php
/var/www/html/app/Controller/PartnerController.php
/var/www/html/app/Controller/PingController.php
/var/www/html/app/Controller/ProductController.php
/var/www/html/app/Controller/PurchaseOrderController.php
/var/www/html/app/Controller/ReportController.php
/var/www/html/app/Controller/SalesOrderController.php
/var/www/html/app/Controller/UserController.php
/var/www/html/app/Controller/WarehouseController.php
/var/www/html/app/Entity/Category.php
/var/www/html/app/Entity/Customer.php
/var/www/html/app/Entity/PingResult.php
/var/www/html/app/Entity/Product.php
/var/www/html/app/Entity/ProductStock.php
/var/www/html/app/Entity/PurchaseOrder.php
/var/www/html/app/Entity/PurchaseOrderItem.php
/var/www/html/app/Entity/SalesOrder.php
/var/www/html/app/Entity/SalesOrderItem.php
/var/www/html/app/Entity/StockLedger.php
/var/www/html/app/Entity/Supplier.php
/var/www/html/app/Entity/User.php
/var/www/html/app/Entity/Warehouse.php
/var/www/html/app/Repository/CategoryRepositoryInterface.php
/var/www/html/app/Repository/CustomerRepositoryInterface.php
/var/www/html/app/Repository/Database.php
/var/www/html/app/Repository/MySQLCategoryRepository.php
/var/www/html/app/Repository/MySQLCustomerRepository.php
/var/www/html/app/Repository/MySQLPingRepository.php
/var/www/html/app/Repository/MySQLProductRepository.php
/var/www/html/app/Repository/MySQLPurchaseOrderRepository.php
/var/www/html/app/Repository/MySQLSalesOrderRepository.php
/var/www/html/app/Repository/MySQLStockLedgerRepository.php
/var/www/html/app/Repository/MySQLStockRepository.php
/var/www/html/app/Repository/MySQLSupplierRepository.php
/var/www/html/app/Repository/MySQLUserRepository.php
/var/www/html/app/Repository/MySQLWarehouseRepository.php
/var/www/html/app/Repository/PingRepositoryInterface.php
/var/www/html/app/Repository/ProductRepositoryInterface.php
/var/www/html/app/Repository/PurchaseOrderRepositoryInterface.php
/var/www/html/app/Repository/SalesOrderRepositoryInterface.php
/var/www/html/app/Repository/StockLedgerRepositoryInterface.php
/var/www/html/app/Repository/StockRepositoryInterface.php
/var/www/html/app/Repository/SupplierRepositoryInterface.php
/var/www/html/app/Repository/UserRepositoryInterface.php
/var/www/html/app/Repository/WarehouseRepositoryInterface.php
/var/www/html/app/Service/AuthService.php
/var/www/html/app/Service/AuthSession.php
/var/www/html/app/Service/CategoryService.php
/var/www/html/app/Service/DashboardService.php
/var/www/html/app/Service/PartnerService.php
/var/www/html/app/Service/PingService.php
/var/www/html/app/Service/ProductService.php
/var/www/html/app/Service/PurchaseOrderService.php
/var/www/html/app/Service/ReportService.php
/var/www/html/app/Service/SalesOrderService.php
/var/www/html/app/Service/UserService.php
/var/www/html/app/Service/WarehouseService.php

 [OK] No errors
```

## Static Quality Assessment

1. **Type Safety**: Strict typing (`declare(strict_types=1);`) is consistently utilized across all 60 application classes.
2. **Nullable and Union Types**: Return types and argument types are clearly specified, ensuring that unhandled `null` scenarios or mismatched types are caught at compilation/analysis time.
3. **Array Shape Documentation**: Complex data transfer structures are strongly documented with PHPDoc array shapes (`array{...}`) facilitating static validation without requiring heavy ORM overhead.
