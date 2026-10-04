<?php
use App\Service\AuthSession;
$isAdmin = AuthSession::hasRole('Admin');
$isSales = AuthSession::hasRole('Sales');
$isWarehouse = AuthSession::hasRole('WarehouseStaff');
$canManageSO = AuthSession::hasRole(['Admin', 'Sales']);
$canProcessIssue = AuthSession::hasRole(['Admin', 'WarehouseStaff']);
?>

<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <a href="/sales-orders" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.65rem;">
                &larr; Kembali ke Daftar SO
            </a>
            <span class="badge badge-status-<?= strtolower($order->getStatus()) ?>" style="font-size: 0.85rem; padding: 0.3rem 0.75rem;">
                Status: <?= htmlspecialchars($order->getStatus()) ?>
            </span>
        </div>
        <h1 class="page-title">Sales Order: <?= htmlspecialchars($order->getSoNumber()) ?></h1>
        <p class="page-subtitle">
            Pelanggan: <strong><?= htmlspecialchars($order->getCustomerName() ?? '-') ?></strong> &bull; 
            Gudang Asal: <strong><?= htmlspecialchars($order->getWarehouseName() ?? '-') ?></strong> &bull; 
            Tgl Order: <?= date('d M Y', strtotime($order->getOrderDate())) ?> &bull; 
            Dibuat oleh: <?= htmlspecialchars($order->getCreatedByName() ?? 'Sales') ?>
            <?php if ($order->getApprovedByName()): ?>
                &bull; Disetujui oleh: <strong><?= htmlspecialchars($order->getApprovedByName()) ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <?php if ($order->isDraft() && $canManageSO): ?>
            <form action="/sales-orders/<?= $order->getId() ?>/submit-approval" method="POST" style="display: inline;" onsubmit="return confirm('Ajukan Sales Order ini untuk mendapatkan persetujuan Administrator?');">
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Ajukan Persetujuan (Submit for Approval)</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($order->isPendingApproval() && $isAdmin): ?>
            <form action="/sales-orders/<?= $order->getId() ?>/approve" method="POST" style="display: inline;" onsubmit="return confirm('Setujui Sales Order ini? Order akan siap diproses pengeluaran barangnya oleh tim gudang.');">
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Setujui Pesanan (Approve)</span>
                </button>
            </form>

            <form action="/sales-orders/<?= $order->getId() ?>/reject" method="POST" style="display: inline;" onsubmit="return confirm('Tolak dan batalkan Sales Order ini? Tindakan ini tidak dapat diurungkan.');">
                <button type="submit" class="btn btn-danger">
                    Tolak Pesanan (Reject)
                </button>
            </form>
        <?php endif; ?>

        <?php if ($order->canCancel() && $canManageSO): ?>
            <form action="/sales-orders/<?= $order->getId() ?>/cancel" method="POST" style="display: inline;" onsubmit="return confirm('Batalkan Sales Order ini? Tindakan ini tidak dapat diurungkan.');">
                <button type="submit" class="btn btn-danger">
                    Batalkan SO
                </button>
            </form>
        <?php endif; ?>
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

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($order->isPendingApproval() && !$isAdmin): ?>
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.75rem; border-left: 4px solid var(--border-dark); background: var(--bg-surface-elevated);">
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span style="font-size: 1.25rem;">⏳</span>
            <div>
                <strong>Menunggu Persetujuan Administrator (Segregation of Duties)</strong>
                <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                    Sales Order ini telah diajukan dan sedang menunggu verifikasi oleh Administrator. 
                    Berdasarkan prinsip Segregation of Duties, staf Sales tidak diperkenankan menyetujui pesanan.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Summary Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem;">
    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Nilai SO</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; font-family: var(--font-mono);">
            Rp <?= number_format($order->getTotalAmount(), 0, ',', '.') ?>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            <?= count($order->getItems()) ?> jenis item produk
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Kuantitas Dipesan</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;">
            <?= number_format($order->getTotalOrdered(), 0, ',', '.') ?> <span style="font-size: 0.9rem; font-weight: 400; color: var(--text-muted);">unit</span>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Komitmen pesanan pelanggan
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Kuantitas Dikeluarkan</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;">
            <?= number_format($order->getTotalFulfilled(), 0, ',', '.') ?> <span style="font-size: 0.9rem; font-weight: 400; color: var(--text-muted);">unit</span>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Telah diproses Goods Issue
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Sisa Pengeluaran</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;">
            <?= number_format(max(0, $order->getTotalOrdered() - $order->getTotalFulfilled()), 0, ',', '.') ?> <span style="font-size: 0.9rem; font-weight: 400; color: var(--text-muted);">unit</span>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Belum keluar dari gudang
        </div>
    </div>
</div>

<!-- Items Table -->
<div class="card table-card" style="margin-bottom: 2rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Rincian Item Produk</h2>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Gudang Asal: <strong><?= htmlspecialchars($order->getWarehouseName() ?? '-') ?></strong>
        </span>
    </div>
    <div class="table-container">
        <table class="data-table" aria-label="Detail Item Sales Order">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th style="text-align: right;">Jumlah Dipesan</th>
                    <th style="text-align: right;">Sudah Dikeluarkan</th>
                    <th style="text-align: right;">Sisa Belum Keluar</th>
                    <th style="text-align: right;">Harga Satuan</th>
                    <th style="text-align: right;">Subtotal</th>
                    <th style="text-align: center;">Status Item</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->getItems() as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item->getProductName() ?? '-') ?></strong>
                            <span style="display: block; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                <?= htmlspecialchars($item->getProductSku() ?? '-') ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            <?= number_format($item->getQuantityOrdered(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            <?= number_format($item->getQuantityFulfilled(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                        </td>
                        <td style="text-align: right; font-weight: 700;">
                            <?= number_format($item->getRemainingQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                        </td>
                        <td style="text-align: right; font-family: var(--font-mono);">
                            Rp <?= number_format($item->getUnitPrice(), 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; font-family: var(--font-mono);">
                            Rp <?= number_format($item->getSubtotal(), 0, ',', '.') ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($item->isFullyFulfilled()): ?>
                                <span class="badge badge-success">Tuntas</span>
                            <?php elseif ($item->getQuantityFulfilled() > 0): ?>
                                <span class="badge badge-warning">Parsial</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Menunggu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Goods Issue Processing Form (ARCH-02) -->
<?php if ($order->canIssue() && $canProcessIssue): ?>
    <div class="card" style="padding: 1.75rem; margin-bottom: 2rem; border-top: 3px solid var(--border-dark);">
        <div style="margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.2rem; font-weight: 700; margin: 0;">Proses Pengeluaran Barang (Goods Issue)</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.35rem 0 0;">
                Mencatat pengeluaran fisik barang dari gudang asal <strong><?= htmlspecialchars($order->getWarehouseName()) ?></strong>.
                Dilindungi oleh <strong>Optimistic Locking (ARCH-02)</strong> dan pencatatan audit trail ke <strong>Stock Ledger (Issue)</strong> secara atomik.
            </p>
        </div>

        <form action="/sales-orders/<?= $order->getId() ?>/issue" method="POST" id="issueForm">
            <div class="table-container" style="margin-bottom: 1.25rem;">
                <table class="data-table" aria-label="Form Pengeluaran Barang Sales Order">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="text-align: right;">Sisa Pesanan</th>
                            <th style="width: 220px; text-align: right;">Kuantitas yang Dikeluarkan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->getItems() as $item): ?>
                            <?php if ($item->getRemainingQuantity() > 0): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item->getProductName() ?? '-') ?></strong>
                                        <span style="display: block; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                            <?= htmlspecialchars($item->getProductSku() ?? '-') ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?= $item->getRemainingQuantity() ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                            <input 
                                                type="number" 
                                                name="issued_quantities[<?= $item->getId() ?>]" 
                                                class="form-control" 
                                                style="width: 110px; text-align: right;" 
                                                min="0" 
                                                max="<?= $item->getRemainingQuantity() ?>" 
                                                value="<?= $item->getRemainingQuantity() ?>"
                                                required
                                            >
                                            <span style="font-size: 0.85rem; color: var(--text-muted); min-width: 30px;">
                                                <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="notes" class="form-label">Catatan Pengeluaran / Referensi Pengiriman</label>
                <input 
                    type="text" 
                    id="notes" 
                    name="notes" 
                    class="form-control" 
                    placeholder="contoh: Pengiriman Kurir Logistik / Surat Jalan SJ-001"
                >
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Apakah Anda yakin ingin memproses pengeluaran barang ini? Stok fisik akan langsung dipotong dari gudang.');">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Proses Goods Issue & Potong Stok &rarr;</span>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Stock Ledger History for this SO -->
<?php if (!empty($ledgerEntries)): ?>
    <div class="card table-card">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
            <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Riwayat Mutasi Stock Ledger untuk SO Ini</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0;">
                Catatan mutasi fisik barang keluar yang telah diverifikasi secara permanen di buku besar inventaris.
            </p>
        </div>
        <div class="table-container">
            <table class="data-table" aria-label="Riwayat Mutasi Pengeluaran Barang">
                <thead>
                    <tr>
                        <th>Waktu Mutasi</th>
                        <th>Produk</th>
                        <th>Fasilitas Gudang</th>
                        <th style="text-align: right;">Jumlah Keluar</th>
                        <th>Catatan</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ledgerEntries as $entry): ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= date('d M Y H:i:s', strtotime($entry->getCreatedAt())) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($entry->getProductName() ?? '-') ?></strong>
                                <span style="display: block; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($entry->getProductSku() ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($entry->getWarehouseName() ?? '-') ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: var(--font-mono); color: var(--text-main);">
                                -<?= number_format($entry->getQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($entry->getProductUnit() ?? 'pcs') ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= htmlspecialchars($entry->getNotes() ?? '-') ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= htmlspecialchars($entry->getCreatedByName() ?? '-') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
