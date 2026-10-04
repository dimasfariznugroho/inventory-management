<?php
use App\Service\AuthSession;
$isAdmin = AuthSession::hasRole('Admin');
?>

<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <a href="/products" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.65rem;">
                &larr; Kembali ke Katalog
            </a>
            <span class="badge <?= $product->isActive() ? 'badge-success' : 'badge-danger' ?>">
                <?= $product->isActive() ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <span class="badge badge-info"><?= htmlspecialchars($product->getCategoryName() ?? 'Tanpa Kategori') ?></span>
        </div>
        <h1 class="page-title"><?= htmlspecialchars($product->getName()) ?></h1>
        <p class="page-subtitle">SKU: <strong><?= htmlspecialchars($product->getSku()) ?></strong> &bull; Didaftarkan: <?= date('d M Y H:i', strtotime($product->getCreatedAt())) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <?php if ($isAdmin): ?>
            <a href="/admin/products/<?= $product->getId() ?>/edit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <span>Edit Produk</span>
            </a>
            <form action="/admin/products/<?= $product->getId() ?>/toggle" method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status produk ini?');">
                <button type="submit" class="btn <?= $product->isActive() ? 'btn-danger' : 'btn-secondary' ?>">
                    <?= $product->isActive() ? 'Nonaktifkan' : 'Aktifkan' ?>
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

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.75rem; margin-bottom: 2rem;">
    <!-- Left Column: Product Image & Overview -->
    <div class="card" style="padding: 1.5rem; text-align: center;">
        <div style="width: 100%; height: 260px; background: var(--bg-tertiary, #1e293b); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 1.25rem; border: 1px solid var(--border-color, #334155);">
            <?php if ($product->getImagePath()): ?>
                <img src="<?= htmlspecialchars($product->getImagePath()) ?>" alt="<?= htmlspecialchars($product->getName()) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <div style="color: var(--text-muted, #94a3b8); font-size: 0.9rem; text-align: center;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; display: block; margin-left: auto; margin-right: auto;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <span>Tidak ada foto produk</span>
                </div>
            <?php endif; ?>
        </div>

        <div style="border-top: 1px solid var(--border-color, #334155); padding-top: 1rem; text-align: left;">
            <div style="margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Kategori</span>
                <div style="font-weight: 500; font-size: 0.95rem;"><?= htmlspecialchars($product->getCategoryName() ?? '-') ?></div>
            </div>
            <div style="margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Satuan Unit</span>
                <div style="font-weight: 500; font-size: 0.95rem;"><?= htmlspecialchars($product->getUnit()) ?></div>
            </div>
            <div style="margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Harga Beli (Modal)</span>
                <div style="font-weight: 600; font-size: 1rem; color: var(--text-color);">Rp <?= number_format($product->getPurchasePrice(), 0, ',', '.') ?></div>
            </div>
            <div>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Harga Jual</span>
                <div style="font-weight: 700; font-size: 1.15rem; color: var(--color-primary, #3b82f6);">Rp <?= number_format($product->getSellingPrice(), 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Right Column: Stock Summary & Warehouse Breakdown -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Metrics Row -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            <div class="card" style="padding: 1.25rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Stok Fisik</span>
                <div style="font-size: 2rem; font-weight: 700; color: var(--text-color); margin-top: 0.25rem;">
                    <?= number_format($product->getTotalStock(), 0, ',', '.') ?>
                    <span style="font-size: 0.9rem; font-weight: 400; color: var(--text-muted);"><?= htmlspecialchars($product->getUnit()) ?></span>
                </div>
                <div style="margin-top: 0.5rem;">
                    <?php if ($product->getTotalStock() <= 0): ?>
                        <span class="badge badge-danger">Habis (Out of Stock)</span>
                    <?php elseif ($product->getTotalStock() <= $product->getReorderPoint()): ?>
                        <span class="badge badge-warning">Menipis (Reorder Alert)</span>
                    <?php else: ?>
                        <span class="badge badge-success">Tersedia (Aman)</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="padding: 1.25rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Reorder Point</span>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-warning, #f59e0b); margin-top: 0.25rem;">
                    <?= number_format($product->getReorderPoint(), 0, ',', '.') ?>
                    <span style="font-size: 0.9rem; font-weight: 400; color: var(--text-muted);"><?= htmlspecialchars($product->getUnit()) ?></span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                    Ambang batas peringatan stok minimum
                </div>
            </div>

            <div class="card" style="padding: 1.25rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Estimasi Nilai Stok</span>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success, #10b981); margin-top: 0.25rem;">
                    Rp <?= number_format($product->getTotalStock() * $product->getPurchasePrice(), 0, ',', '.') ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                    Berdasarkan harga modal beli
                </div>
            </div>
        </div>

        <!-- Multi-Warehouse Stock Breakdown (WH-01) -->
        <div class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #334155); padding-bottom: 0.75rem;">
                <div>
                    <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0;">Rincian Stok per Gudang (WH-01 Multi-Lokasi)</h2>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0;">
                        Alokasi stok terpisah pada masing-masing fasilitas penyimpanan logistik.
                    </p>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Gudang</th>
                            <th>Lokasi</th>
                            <th style="text-align: right;">Stok Fisik</th>
                            <th style="text-align: right;">Reserved</th>
                            <th style="text-align: right;">Tersedia (Avail)</th>
                            <th style="text-align: center;">Status Gudang</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($product->getWarehouseStocks())): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    Belum ada data alokasi stok gudang untuk produk ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($product->getWarehouseStocks() as $ws): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($ws->getWarehouseName() ?? "Gudang #{$ws->getWarehouseId()}") ?></div>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-muted); font-size: 0.9rem;"><?= htmlspecialchars($ws->getWarehouseLocation() ?? '-') ?></span>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?= number_format($ws->getQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($product->getUnit()) ?>
                                    </td>
                                    <td style="text-align: right; color: var(--text-muted);">
                                        <?= number_format($ws->getReservedQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($product->getUnit()) ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: <?= $ws->getAvailableQuantity() > 0 ? 'var(--color-success, #10b981)' : 'var(--color-danger, #ef4444)' ?>;">
                                        <?= number_format($ws->getAvailableQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($product->getUnit()) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($ws->getQuantity() > 0): ?>
                                            <span class="badge badge-success">Stok Siap</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Kosong</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: 700; background: var(--bg-tertiary, #1e293b);">
                            <td colspan="2">TOTAL KESELURUHAN</td>
                            <td style="text-align: right;"><?= number_format($product->getTotalStock(), 0, ',', '.') ?> <?= htmlspecialchars($product->getUnit()) ?></td>
                            <td style="text-align: right; color: var(--text-muted);">-</td>
                            <td style="text-align: right; color: var(--color-primary);"><?= number_format($product->getTotalStock(), 0, ',', '.') ?> <?= htmlspecialchars($product->getUnit()) ?></td>
                            <td style="text-align: center;">-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="margin-top: 1.25rem; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary); flex-shrink: 0;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <span>
                    <strong>Aturan Sistem Logistik:</strong> Stok per gudang tidak dapat diedit secara manual sembarangan. Penambahan dan pengurangan stok dicatat otomatis melalui dokumen penerimaan barang (Goods Receipt / PO) dan pengeluaran barang (Goods Issue / SO) via Stock Ledger.
                </span>
            </div>
        </div>
    </div>
</div>
