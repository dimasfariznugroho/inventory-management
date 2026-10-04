<?php
use App\Service\AuthSession;
$authUser = AuthSession::user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inventory & Order Management System - Clean Layered Architecture">
    <title><?= htmlspecialchars($title ?? 'Inventory & Order Management System') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <header class="app-header">
            <div class="header-inner container">
                <div class="brand">
                    <a href="/" class="brand-link">
                        <div class="brand-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                <line x1="12" y1="22.08" x2="12" y2="12"/>
                            </svg>
                        </div>
                        <div class="brand-text">
                            <span class="brand-title">InventoryHub</span>
                            <span class="brand-subtitle">Order Management System</span>
                        </div>
                    </a>
                </div>

                <nav class="header-nav">
                    <?php if ($authUser): ?>
                        <?php if ($authUser['role'] === 'Admin'): ?>
                            <a href="/admin/dashboard" class="nav-link">Dashboard</a>
                            <a href="/products" class="nav-link">Katalog</a>
                            <a href="/purchase-orders" class="nav-link">PO</a>
                            <a href="/sales-orders" class="nav-link">SO</a>
                            <a href="/stock-ledger" class="nav-link">Stock Ledger</a>
                            <a href="/admin/categories" class="nav-link">Kategori</a>
                            <a href="/admin/warehouses" class="nav-link">Gudang</a>
                            <a href="/admin/suppliers" class="nav-link">Pemasok</a>
                            <a href="/admin/customers" class="nav-link">Pelanggan</a>
                            <a href="/admin/users" class="nav-link">Pengguna</a>
                        <?php elseif ($authUser['role'] === 'Sales'): ?>
                            <a href="/sales/dashboard" class="nav-link">Dashboard</a>
                            <a href="/products" class="nav-link">Katalog Produk</a>
                            <a href="/sales-orders" class="nav-link">Sales Order</a>
                            <a href="/purchase-orders" class="nav-link">Purchase Order</a>
                        <?php elseif ($authUser['role'] === 'WarehouseStaff'): ?>
                            <a href="/warehouse/dashboard" class="nav-link">Dashboard</a>
                            <a href="/products" class="nav-link">Katalog Produk</a>
                            <a href="/purchase-orders" class="nav-link">Purchase Order</a>
                            <a href="/sales-orders" class="nav-link">Sales Order</a>
                            <a href="/stock-ledger" class="nav-link">Stock Ledger</a>
                        <?php endif; ?>

                        <a href="/ping" class="nav-link">Status DB</a>
                    <?php else: ?>
                        <a href="/ping" class="nav-link">Status DB</a>
                        <a href="/login" class="nav-link">Login</a>
                    <?php endif; ?>
                </nav>

                <div class="header-meta">
                    <?php if ($authUser): ?>
                        <div class="user-pill">
                            <div class="user-avatar"><?= strtoupper(substr($authUser['name'], 0, 1)) ?></div>
                            <div class="user-details">
                                <span class="user-name"><?= htmlspecialchars($authUser['name']) ?></span>
                                <span class="badge badge-role badge-<?= strtolower($authUser['role']) ?>"><?= htmlspecialchars($authUser['role']) ?></span>
                            </div>
                        </div>
                        <form action="/logout" method="POST" class="logout-form">
                            <button type="submit" class="btn btn-logout" title="Keluar dari sistem">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge badge-primary">Guest Session</span>
                        <a href="/login" class="btn btn-primary btn-sm">Masuk</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <main class="app-main container">
