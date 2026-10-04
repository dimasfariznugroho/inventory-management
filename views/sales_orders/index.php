<?php
use App\Service\AuthSession;
$canCreateSO = AuthSession::hasRole(['Admin', 'Sales']);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sales Orders & Pengeluaran Barang (SO-01)</h1>
        <p class="page-subtitle">Kelola pesanan penjualan pelanggan, alur persetujuan bertingkat, dan pengeluaran stok fisik (Goods Issue).</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="/reports/orders/export?type=so" class="btn btn-secondary" title="Ekspor daftar Sales Order ke format CSV (REPORT-01)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            <span>Ekspor CSV</span>
        </a>
        <?php if ($canCreateSO): ?>
            <a href="/sales-orders/create" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Buat SO Baru</span>
            </a>
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

<!-- Filter & Search Bar (FIND-01) -->
<div class="card filter-card" style="margin-bottom: 1.75rem; padding: 1.25rem;">
    <form action="/sales-orders" method="GET" class="filter-form">
        <div class="filter-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto; gap: 1rem; align-items: flex-end;">
            <div class="form-group filter-item">
                <label for="search" class="form-label text-sm">Cari Nomor / Pelanggan</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    class="form-control" 
                    placeholder="No. SO atau nama customer..." 
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                >
            </div>

            <div class="form-group filter-item">
                <label for="status" class="form-label text-sm">Status SO</label>
                <select id="status" name="status" class="form-control select-control">
                    <option value="">-- Semua Status --</option>
                    <option value="Draft" <?= ($_GET['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="PendingApproval" <?= ($_GET['status'] ?? '') === 'PendingApproval' ? 'selected' : '' ?>>Pending Approval</option>
                    <option value="Approved" <?= ($_GET['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Fulfilled" <?= ($_GET['status'] ?? '') === 'Fulfilled' ? 'selected' : '' ?>>Fulfilled (Selesai)</option>
                    <option value="Cancelled" <?= ($_GET['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="form-group filter-item">
                <label for="warehouse_id" class="form-label text-sm">Gudang Asal</label>
                <select id="warehouse_id" name="warehouse_id" class="form-control select-control">
                    <option value="">-- Semua Gudang --</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= $wh->getId() ?>" <?= (isset($_GET['warehouse_id']) && (int)$_GET['warehouse_id'] === $wh->getId()) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wh->getName()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filter-item">
                <label for="sort" class="form-label text-sm">Urutan Tanggal</label>
                <select id="sort" name="sort" class="form-control select-control">
                    <option value="desc" <?= (isset($_GET['sort']) && strtolower($_GET['sort']) === 'desc') ? 'selected' : '' ?>>Tanggal Terbaru &darr;</option>
                    <option value="asc" <?= (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') ? 'selected' : '' ?>>Tanggal Terlama &uarr;</option>
                </select>
            </div>

            <div class="filter-actions" style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <span>Filter</span>
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['warehouse_id']) || !empty($_GET['sort'])): ?>
                    <a href="/sales-orders" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Listing or Empty State (VIEW-01) -->
<?php if (empty($orders)): ?>
    <?php
    $emptyTitle = 'Tidak Ada Sales Order Ditemukan';
    $emptyMessage = 'Tidak ada dokumen Sales Order yang cocok dengan kriteria pencarian atau filter status yang dipilih.';
    $emptyResetUrl = '/sales-orders';
    $emptyActionUrl = $canCreateSO ? '/sales-orders/create' : null;
    $emptyActionText = $canCreateSO ? '+ Buat Sales Order Baru' : null;
    require_once dirname(__DIR__) . '/layout/empty_state.php';
    ?>
<?php else: ?>
    <div class="card table-card">
        <div class="table-responsive">
            <table class="data-table" aria-label="Daftar Sales Order">
                <thead>
                    <tr>
                        <th style="width: 150px;">No. SO</th>
                        <th>Pelanggan (Customer)</th>
                        <th>Gudang Asal</th>
                        <th>Tgl Order</th>
                        <th style="text-align: center;">Item / Qty</th>
                        <th style="text-align: right;">Total Nilai</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right; width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $so): ?>
                        <tr>
                            <td>
                                <a href="/sales-orders/<?= $so->getId() ?>" style="font-weight: 700; font-family: var(--font-mono); color: var(--text-main);">
                                    <?= htmlspecialchars($so->getSoNumber()) ?>
                                </a>
                                <span style="display: block; font-size: 0.75rem; color: var(--text-muted);">
                                    Oleh: <?= htmlspecialchars($so->getCreatedByName()) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($so->getCustomerName()) ?></strong>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($so->getWarehouseName()) ?></span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?= date('d M Y', strtotime($so->getOrderDate())) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span style="font-weight: 600;"><?= count($so->getItems()) ?> Item</span>
                                <span style="display: block; font-size: 0.75rem; color: var(--text-muted);">
                                    <?= $so->getTotalFulfilled() ?> / <?= $so->getTotalOrdered() ?> unit
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                Rp <?= number_format($so->getTotalAmount(), 0, ',', '.') ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($so->isDraft()): ?>
                                    <span class="badge badge-secondary">Draft</span>
                                <?php elseif ($so->isPendingApproval()): ?>
                                    <span class="badge badge-warning">Pending Approval</span>
                                <?php elseif ($so->isApproved()): ?>
                                    <span class="badge badge-info">Approved</span>
                                <?php elseif ($so->isFulfilled()): ?>
                                    <span class="badge badge-success">Fulfilled</span>
                                <?php elseif ($so->isCancelled()): ?>
                                    <span class="badge badge-danger">Cancelled</span>
                                <?php else: ?>
                                    <span class="badge"><?= htmlspecialchars($so->getStatus()) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="/sales-orders/<?= $so->getId() ?>" class="btn btn-secondary btn-sm">
                                    Detail &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reusable Pagination Component -->
    <?php require_once dirname(__DIR__) . '/layout/pagination.php'; ?>
<?php endif; ?>
