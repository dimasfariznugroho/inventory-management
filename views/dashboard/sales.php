<div class="dashboard-header">
    <div>
        <div class="status-indicator-wrapper">
            <span class="pulse-dot dot-alive"></span>
            <span class="status-text">Peran: Sales Officer</span>
        </div>
        <h1 class="dashboard-title">Dashboard Sales</h1>
        <p class="dashboard-subtitle">
            Selamat datang, <strong><?= htmlspecialchars($user['name'] ?? 'Sales') ?></strong>. Ringkasan performa penjualan dan status order milik Anda (DASH-01).
        </p>
    </div>
    <div class="dashboard-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="/reports/orders/export?type=so" class="btn btn-secondary btn-sm" title="Ekspor Sales Order CSV">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            <span>Ekspor SO CSV</span>
        </a>
        <a href="/sales-orders/create" class="btn btn-primary btn-sm">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Buat SO Baru</span>
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

<!-- Dynamic Aggregation Metric Cards for Sales (DASH-01) -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <!-- Card 1: Total Nilai Penjualan Saya -->
    <div class="card stat-card">
        <div class="stat-icon icon-database">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Total Penjualan Saya</span>
            <span class="stat-value text-mono" style="font-size: 1.3rem; font-weight: 800;">
                Rp <?= number_format($metrics['my_total_sales_amount'] ?? 0, 0, ',', '.') ?>
            </span>
            <span class="stat-hint">Akumulasi nilai pesanan aktif Anda</span>
        </div>
    </div>

    <!-- Card 2: Menunggu Persetujuan Admin -->
    <div class="card stat-card">
        <div class="stat-icon icon-time" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Menunggu Persetujuan</span>
            <span class="stat-value text-mono" style="font-size: 1.35rem; font-weight: 800; color: #d97706;">
                <?= (int) ($metrics['my_so_counts']['PendingApproval'] ?? 0) ?> SO
            </span>
            <span class="stat-hint">Diajukan ke Admin untuk di-approve</span>
        </div>
    </div>

    <!-- Card 3: Disetujui (Approved) -->
    <div class="card stat-card">
        <div class="stat-icon icon-version" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Disetujui (Siap Kirim)</span>
            <span class="stat-value text-mono" style="font-size: 1.35rem; font-weight: 800; color: #2563eb;">
                <?= (int) ($metrics['my_so_counts']['Approved'] ?? 0) ?> SO
            </span>
            <span class="stat-hint">Dalam antrean pengeluaran barang gudang</span>
        </div>
    </div>

    <!-- Card 4: Selesai (Fulfilled) -->
    <div class="card stat-card">
        <div class="stat-icon icon-version" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Selesai (Fulfilled)</span>
            <span class="stat-value text-success text-mono" style="font-size: 1.35rem; font-weight: 800;">
                <?= (int) ($metrics['my_so_counts']['Fulfilled'] ?? 0) ?> SO
            </span>
            <span class="stat-hint">Stok fisik telah berhasil dikeluarkan</span>
        </div>
    </div>
</div>

<!-- Order Summary Per Status (DASH-01) -->
<div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Ringkasan Order Milik Saya per Status</h2>
        <a href="/sales-orders" class="text-sm" style="text-decoration: underline;">Buka Semua Sales Order &rarr;</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
        <?php
        $statusLabels = [
            'Draft'           => ['label' => 'Draft', 'badge' => 'badge-secondary'],
            'PendingApproval' => ['label' => 'Pending Approval', 'badge' => 'badge-warning'],
            'Approved'        => ['label' => 'Approved', 'badge' => 'badge-info'],
            'Fulfilled'       => ['label' => 'Fulfilled', 'badge' => 'badge-success'],
            'Cancelled'       => ['label' => 'Cancelled', 'badge' => 'badge-danger'],
        ];

        foreach ($statusLabels as $statusKey => $meta):
            $count = (int) ($metrics['my_so_counts'][$statusKey] ?? 0);
        ?>
            <div style="padding: 1rem; background: #f9fafb; border: 1px solid var(--border-color, #e5e7eb); border-radius: 8px; text-align: center;">
                <span class="badge <?= $meta['badge'] ?>" style="margin-bottom: 0.5rem;"><?= htmlspecialchars($meta['label']) ?></span>
                <div class="text-mono" style="font-size: 1.75rem; font-weight: 800; color: var(--text-color);">
                    <?= $count ?>
                </div>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Dokumen Order</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Orders Table -->
<?php if (!empty($metrics['my_recent_orders'])): ?>
    <div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Pesanan Terbaru Dibuat oleh Anda</h2>
            <a href="/sales-orders" class="btn btn-secondary btn-sm">Lihat Semua Order &rarr;</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. SO</th>
                        <th>Pelanggan</th>
                        <th>Gudang Asal</th>
                        <th>Tgl Order</th>
                        <th style="text-align: right;">Total Nilai</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metrics['my_recent_orders'] as $rso): ?>
                        <tr>
                            <td class="text-mono font-bold"><?= htmlspecialchars($rso->getSoNumber()) ?></td>
                            <td><?= htmlspecialchars($rso->getCustomerName()) ?></td>
                            <td><?= htmlspecialchars($rso->getWarehouseName()) ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('d M Y', strtotime($rso->getOrderDate())) ?></td>
                            <td style="text-align: right;" class="text-mono font-bold">
                                Rp <?= number_format($rso->getTotalAmount(), 0, ',', '.') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-status-<?= strtolower($rso->getStatus()) ?>">
                                    <?= htmlspecialchars($rso->getStatus()) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="/sales-orders/<?= $rso->getId() ?>" class="btn btn-secondary btn-sm">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
