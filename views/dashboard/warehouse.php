<div class="dashboard-header">
    <div>
        <div class="status-indicator-wrapper">
            <span class="pulse-dot dot-alive"></span>
            <span class="status-text">Peran: Warehouse Staff Aktif</span>
        </div>
        <h1 class="dashboard-title">Dashboard Staf Gudang</h1>
        <p class="dashboard-subtitle">
            Selamat datang, <strong><?= htmlspecialchars($user['name'] ?? 'Staff') ?></strong>. Antrean operasional penerimaan barang (Goods Receipt), pengeluaran barang (Goods Issue), dan inventaris menipis (DASH-01).
        </p>
    </div>
    <div class="dashboard-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="/reports/stock-ledger/export" class="btn btn-secondary btn-sm" title="Ekspor Stock Ledger CSV">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            <span>Ekspor Ledger CSV</span>
        </a>
        <a href="/purchase-orders/create" class="btn btn-primary btn-sm">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Buat PO Baru</span>
        </a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<!-- Dynamic Operational Metric Cards for Warehouse (DASH-01) -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
    <!-- Card 1: Antrean Goods Receipt -->
    <div class="card stat-card">
        <div class="stat-icon icon-time" style="background: rgba(59, 130, 246, 0.1); color: #2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Antrean Goods Receipt</span>
            <span class="stat-value text-mono" style="font-size: 1.35rem; font-weight: 800; color: #2563eb;">
                <?= count($metrics['pending_receipt_queue'] ?? []) ?> PO
            </span>
            <span class="stat-hint">PO status Ordered / Partial perlu penerimaan stok</span>
        </div>
    </div>

    <!-- Card 2: Antrean Goods Issue -->
    <div class="card stat-card">
        <div class="stat-icon icon-version" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Antrean Goods Issue</span>
            <span class="stat-value text-mono" style="font-size: 1.35rem; font-weight: 800; color: #059669;">
                <?= count($metrics['pending_issue_queue'] ?? []) ?> SO
            </span>
            <span class="stat-hint">SO status Approved siap dikeluarkan fisiknya</span>
        </div>
    </div>

    <!-- Card 3: Produk Low Stock -->
    <div class="card stat-card">
        <div class="stat-icon icon-database" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Peringatan Low Stock</span>
            <span class="stat-value text-danger" style="font-size: 1.35rem; font-weight: 800;">
                <?= (int) ($metrics['low_stock_count'] ?? 0) ?> Produk
            </span>
            <span class="stat-hint">Stok global &le; Batas Reorder Point</span>
        </div>
    </div>
</div>

<!-- Antrean 1: Goods Receipt Queue (DASH-01) -->
<div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Antrean Penerimaan Barang (Goods Receipt Queue)</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                Purchase Order yang telah di-order ke supplier dan menunggu kedatangan fisik di gudang.
            </p>
        </div>
        <a href="/purchase-orders?status=Ordered" class="text-sm" style="text-decoration: underline;">Buka Semua PO &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table" aria-label="Tabel Antrean Penerimaan Barang PO">
            <thead>
                <tr>
                    <th>No. PO</th>
                    <th>Pemasok</th>
                    <th>Gudang Tujuan</th>
                    <th>Tgl Order</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($metrics['pending_receipt_queue'])): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                            Tidak ada antrean penerimaan barang yang tertunda saat ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($metrics['pending_receipt_queue'] as $po): ?>
                        <tr>
                            <td class="text-mono font-bold"><?= htmlspecialchars($po->getPoNumber()) ?></td>
                            <td><?= htmlspecialchars($po->getSupplierName()) ?></td>
                            <td><?= htmlspecialchars($po->getWarehouseName()) ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('d M Y', strtotime($po->getOrderDate())) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-status-<?= strtolower($po->getStatus()) ?>">
                                    <?= htmlspecialchars($po->getStatus()) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="/purchase-orders/<?= $po->getId() ?>" class="btn btn-primary btn-sm">
                                    Proses Receipt &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Antrean 2: Goods Issue Queue (DASH-01) -->
<div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Antrean Pengeluaran Barang (Goods Issue Queue)</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                Sales Order yang sudah disetujui (Approved) dan siap dikeluarkan dari gudang pengiriman.
            </p>
        </div>
        <a href="/sales-orders?status=Approved" class="text-sm" style="text-decoration: underline;">Buka Semua SO &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table" aria-label="Tabel Antrean Pengeluaran Barang SO">
            <thead>
                <tr>
                    <th>No. SO</th>
                    <th>Pelanggan</th>
                    <th>Gudang Asal</th>
                    <th>Tgl Order</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($metrics['pending_issue_queue'])): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                            Tidak ada antrean pengeluaran barang yang siap diproses saat ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($metrics['pending_issue_queue'] as $so): ?>
                        <tr>
                            <td class="text-mono font-bold"><?= htmlspecialchars($so->getSoNumber()) ?></td>
                            <td><?= htmlspecialchars($so->getCustomerName()) ?></td>
                            <td><?= htmlspecialchars($so->getWarehouseName()) ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('d M Y', strtotime($so->getOrderDate())) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($so->getStatus()) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="/sales-orders/<?= $so->getId() ?>" class="btn btn-primary btn-sm">
                                    Proses Issue &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Antrean 3: Produk Low Stock Alert Table (DASH-01) -->
<?php if (!empty($metrics['low_stock_products'])): ?>
    <div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Peringatan Stok Menipis (Low Stock Alert)</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                    Daftar produk dengan kuantitas fisik &le; titik pemesanan ulang (reorder point).
                </p>
            </div>
            <a href="/products?stock_status=low" class="btn btn-secondary btn-sm">Katalog Low Stock &rarr;</a>
        </div>

        <div class="table-responsive">
            <table class="data-table" aria-label="Tabel Peringatan Stok Menipis Gudang">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th style="text-align: center;">Total Stok Fisik</th>
                        <th style="text-align: center;">Reorder Point</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metrics['low_stock_products'] as $lp): ?>
                        <tr>
                            <td class="text-mono font-bold"><?= htmlspecialchars($lp->getSku()) ?></td>
                            <td><?= htmlspecialchars($lp->getName()) ?></td>
                            <td><span class="text-sm"><?= htmlspecialchars($lp->getCategoryName() ?? '-') ?></span></td>
                            <td style="text-align: center;">
                                <span class="badge badge-danger-glow text-mono">
                                    <?= $lp->getTotalStock() ?> <?= htmlspecialchars($lp->getUnit()) ?>
                                </span>
                            </td>
                            <td style="text-align: center;" class="text-mono"><?= $lp->getReorderPoint() ?></td>
                            <td style="text-align: right;">
                                <a href="/products/<?= $lp->getId() ?>" class="btn btn-secondary btn-sm">Lihat Per Gudang</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
