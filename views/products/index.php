<?php
use App\Service\AuthSession;
$isAdmin = AuthSession::hasRole('Admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Katalog Produk & Inventaris (PRD-01)</h1>
        <p class="page-subtitle">Daftar produk, status ketersediaan stok global, dan master data katalog.</p>
    </div>
    <?php if ($isAdmin): ?>
        <div>
            <a href="/admin/products/create" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Tambah Produk Baru</span>
            </a>
        </div>
    <?php endif; ?>
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

<!-- Filter & Search Bar -->
<div class="card filter-card" style="margin-bottom: 1.75rem; padding: 1.25rem;">
    <form action="/products" method="GET" class="filter-form">
        <div class="filter-grid">
            <div class="form-group filter-item">
                <label for="search" class="form-label text-sm">Cari Nama / SKU</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    class="form-control" 
                    placeholder="Ketik SKU atau nama produk..." 
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                >
            </div>

            <div class="form-group filter-item">
                <label for="category_id" class="form-label text-sm">Kategori</label>
                <select id="category_id" name="category_id" class="form-control select-control">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat->getId() ?>" <?= (isset($_GET['category_id']) && (int)$_GET['category_id'] === $cat->getId()) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat->getName()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filter-item">
                <label for="stock_status" class="form-label text-sm">Status Stok</label>
                <select id="stock_status" name="stock_status" class="form-control select-control">
                    <option value="">Semua Status Stok</option>
                    <option value="low" <?= ($_GET['stock_status'] ?? '') === 'low' ? 'selected' : '' ?>>Menipis (Low Stock &le; Reorder Point)</option>
                    <option value="normal" <?= ($_GET['stock_status'] ?? '') === 'normal' ? 'selected' : '' ?>>Aman / Normal</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <span>Filter</span>
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['category_id']) || !empty($_GET['stock_status'])): ?>
                    <a href="/products" class="btn btn-secondary" style="align-self: flex-end;">Reset</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Products Listing / Empty State -->
<?php if (empty($products)): ?>
    <?php
    $emptyTitle = 'Tidak Ada Produk Ditemukan';
    $emptyMessage = 'Tidak ada produk yang cocok dengan kata kunci pencarian atau filter kategori/status stok yang dipilih.';
    $emptyResetUrl = '/products';
    $emptyActionUrl = $isAdmin ? '/admin/products/create' : null;
    $emptyActionText = $isAdmin ? '+ Tambah Produk Baru' : null;
    require_once dirname(__DIR__) . '/layout/empty_state.php';
    ?>
<?php else: ?>
    <div class="card table-card">
        <div class="table-responsive">
            <table class="data-table" aria-label="Katalog Produk">
                <thead>
                    <tr>
                        <th style="width: 70px;">Gambar</th>
                        <th>SKU / Nama Produk</th>
                        <th>Kategori</th>
                        <th style="text-align: right;">Harga Jual</th>
                        <th style="text-align: center;">Total Stok</th>
                        <th style="text-align: center;">Reorder Point</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right; width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="product-thumb">
                                    <?php if ($p->getImagePath()): ?>
                                        <img src="<?= htmlspecialchars($p->getImagePath()) ?>" alt="<?= htmlspecialchars($p->getName()) ?>" class="thumb-img">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <polyline points="21 15 16 10 5 21"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="badge badge-secondary badge-xs text-mono" style="margin-bottom: 0.2rem;"><?= htmlspecialchars($p->getSku()) ?></span>
                                    <a href="/products/<?= $p->getId() ?>" class="product-title-link">
                                        <?= htmlspecialchars($p->getName()) ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm"><?= htmlspecialchars($p->getCategoryName() ?? '-') ?></span>
                            </td>
                            <td style="text-align: right;" class="text-mono font-semibold">
                                Rp <?= number_format($p->getSellingPrice(), 0, ',', '.') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge <?= $p->isLowStock() ? 'badge-danger-glow' : 'badge-success' ?>" style="font-size: 0.85rem; font-family: var(--font-mono);">
                                    <?= $p->getTotalStock() ?> <?= htmlspecialchars($p->getUnit()) ?>
                                </span>
                            </td>
                            <td style="text-align: center;" class="text-mono text-dim text-sm">
                                <?= $p->getReorderPoint() ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($p->isActive()): ?>
                                    <span class="status-pill status-active">Aktif</span>
                                <?php else: ?>
                                    <span class="status-pill status-inactive">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="/products/<?= $p->getId() ?>" class="btn-action btn-action-edit" title="Lihat Rincian Stok Multi-Gudang">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>Detail</span>
                                    </a>

                                    <?php if ($isAdmin): ?>
                                        <a href="/admin/products/<?= $p->getId() ?>/edit" class="btn-action btn-action-edit" title="Edit Produk">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>

                                        <form action="/admin/products/<?= $p->getId() ?>/toggle-status" method="POST" class="inline-form" onsubmit="return confirm('Ubah status aktif produk ini?')">
                                            <button type="submit" class="btn-action <?= $p->isActive() ? 'btn-action-deactivate' : 'btn-action-activate' ?>" title="<?= $p->isActive() ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <?= $p->isActive() ? 'Nonaktif' : 'Aktif' ?>
                                            </button>
                                        </form>

                                        <form action="/admin/products/<?= $p->getId() ?>/delete" method="POST" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus produk ini? Produk yang sudah dipakai di order hanya bisa dinonaktifkan.')">
                                            <button type="submit" class="btn-action btn-action-deactivate" title="Hapus Produk">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reusable Pagination Bar -->
    <?php require_once dirname(__DIR__) . '/layout/pagination.php'; ?>
<?php endif; ?>

