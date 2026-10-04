<div class="dashboard-header">
    <div>
        <div class="status-indicator-wrapper">
            <span class="pulse-dot dot-alive"></span>
            <span class="status-text">Peran: Administrator Aktif</span>
        </div>
        <h1 class="dashboard-title">Dashboard Administrator</h1>
        <p class="dashboard-subtitle">
            Selamat datang, <strong><?= htmlspecialchars($user['name'] ?? 'Admin') ?></strong>. Ringkasan agregasi real-time inventaris dan order di seluruh gudang (DASH-01).
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
        <a href="/admin/users" class="btn btn-primary btn-sm">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
            </svg>
            <span>Kelola Pengguna</span>
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

<!-- Dynamic Aggregation Metric Cards (DASH-01) -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
    <!-- Card 1: Nilai Inventori -->
    <div class="card stat-card">
        <div class="stat-icon icon-database">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Nilai Inventori Global</span>
            <span class="stat-value text-mono" style="font-size: 1.35rem; font-weight: 800;">
                Rp <?= number_format($metrics['total_inventory_valuation'] ?? 0, 0, ',', '.') ?>
            </span>
            <span class="stat-hint">Total modal stok dari <?= (int) ($metrics['total_products_count'] ?? 0) ?> produk katalog</span>
        </div>
    </div>

    <!-- Card 2: Produk Di Bawah Reorder Point -->
    <div class="card stat-card">
        <div class="stat-icon icon-version" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Produk Low Stock</span>
            <span class="stat-value text-danger" style="font-size: 1.35rem; font-weight: 800;">
                <?= (int) ($metrics['low_stock_count'] ?? 0) ?> Produk
            </span>
            <span class="stat-hint">
                <a href="/products?stock_status=low" style="color: inherit; text-decoration: underline;">
                    Stok &le; Reorder Point &rarr;
                </a>
            </span>
        </div>
    </div>

    <!-- Card 3: Purchase Orders Pending -->
    <div class="card stat-card">
        <div class="stat-icon icon-time">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending PO (Penerimaan)</span>
            <span class="stat-value text-mono" style="font-size: 1.35rem; font-weight: 800;">
                <?= (int) (($metrics['pending_orders_summary']['po_ordered'] ?? 0) + ($metrics['pending_orders_summary']['po_partially_received'] ?? 0)) ?> PO
            </span>
            <span class="stat-hint">
                Ordered: <strong><?= (int) ($metrics['pending_orders_summary']['po_ordered'] ?? 0) ?></strong> | 
                Partial: <strong><?= (int) ($metrics['pending_orders_summary']['po_partially_received'] ?? 0) ?></strong>
            </span>
        </div>
    </div>

    <!-- Card 4: Sales Orders Pending Approval -->
    <div class="card stat-card">
        <div class="stat-icon icon-version">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">SO Menunggu Approval</span>
            <span class="stat-value" style="font-size: 1.35rem; font-weight: 800;">
                <?= (int) ($metrics['pending_orders_summary']['so_pending_approval'] ?? 0) ?> SO
            </span>
            <span class="stat-hint">
                <a href="/sales-orders?status=PendingApproval" style="color: inherit; text-decoration: underline;">
                    Perlu persetujuan Admin &rarr;
                </a>
            </span>
        </div>
    </div>
</div>

<!-- Order Status Aggregation Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
    <!-- Purchase Orders Breakdown -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Status Purchase Orders</h2>
            <a href="/purchase-orders" class="text-sm" style="text-decoration: underline;">Lihat Semua &rarr;</a>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <?php
            $poStatuses = [
                'Draft'             => ['label' => 'Draft', 'badge' => 'badge-secondary'],
                'Ordered'           => ['label' => 'Ordered (Menunggu Pengiriman)', 'badge' => 'badge-info'],
                'PartiallyReceived' => ['label' => 'Partially Received', 'badge' => 'badge-warning'],
                'Received'          => ['label' => 'Received (Selesai)', 'badge' => 'badge-success'],
                'Cancelled'         => ['label' => 'Cancelled', 'badge' => 'badge-danger'],
            ];
            foreach ($poStatuses as $st => $meta):
                $count = (int) ($metrics['po_counts'][$st] ?? 0);
            ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: #f9fafb; border-radius: 6px;">
                    <a href="/purchase-orders?status=<?= urlencode($st) ?>" style="font-size: 0.875rem; font-weight: 500; color: var(--text-color);">
                        <?= htmlspecialchars($meta['label']) ?>
                    </a>
                    <span class="badge <?= $meta['badge'] ?> text-mono font-bold"><?= $count ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sales Orders Breakdown -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Status Sales Orders</h2>
            <a href="/sales-orders" class="text-sm" style="text-decoration: underline;">Lihat Semua &rarr;</a>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <?php
            $soStatuses = [
                'Draft'           => ['label' => 'Draft', 'badge' => 'badge-secondary'],
                'PendingApproval' => ['label' => 'Pending Approval', 'badge' => 'badge-warning'],
                'Approved'        => ['label' => 'Approved (Siap Kirim)', 'badge' => 'badge-info'],
                'Fulfilled'       => ['label' => 'Fulfilled (Selesai)', 'badge' => 'badge-success'],
                'Cancelled'       => ['label' => 'Cancelled', 'badge' => 'badge-danger'],
            ];
            foreach ($soStatuses as $st => $meta):
                $count = (int) ($metrics['so_counts'][$st] ?? 0);
            ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: #f9fafb; border-radius: 6px;">
                    <a href="/sales-orders?status=<?= urlencode($st) ?>" style="font-size: 0.875rem; font-weight: 500; color: var(--text-color);">
                        <?= htmlspecialchars($meta['label']) ?>
                    </a>
                    <span class="badge <?= $meta['badge'] ?> text-mono font-bold"><?= $count ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Low Stock Products Warning Table -->
<?php if (!empty($metrics['low_stock_products'])): ?>
    <div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Peringatan Stok Menipis (&le; Reorder Point)</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                    Produk berikut memerlukan pengadaan ulang (Purchase Order) segera.
                </p>
            </div>
            <a href="/products?stock_status=low" class="btn btn-secondary btn-sm">Lihat Semua Low Stock &rarr;</a>
        </div>

        <div class="table-container table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th style="text-align: center;">Stok Saat Ini</th>
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
                                <a href="/products/<?= $lp->getId() ?>" class="btn btn-secondary btn-sm">Rincian Gudang</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
