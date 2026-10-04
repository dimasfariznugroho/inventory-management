<?php
use App\Service\AuthSession;
$canManagePO = AuthSession::hasRole(['Admin', 'WarehouseStaff']);
?>

<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <a href="/purchase-orders" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.65rem;">
                &larr; Kembali ke Daftar PO
            </a>
            <span class="badge badge-status-<?= strtolower($po->getStatus()) ?>" style="font-size: 0.85rem; padding: 0.3rem 0.75rem;">
                Status: <?= htmlspecialchars($po->getStatus()) ?>
            </span>
        </div>
        <h1 class="page-title">Purchase Order: <?= htmlspecialchars($po->getPoNumber()) ?></h1>
        <p class="page-subtitle">
            Pemasok: <strong><?= htmlspecialchars($po->getSupplierName() ?? '-') ?></strong> &bull; 
            Gudang Tujuan: <strong><?= htmlspecialchars($po->getWarehouseName() ?? '-') ?></strong> &bull; 
            Tgl Order: <?= date('d M Y', strtotime($po->getOrderDate())) ?> &bull; 
            Dibuat oleh: <?= htmlspecialchars($po->getCreatedByName() ?? 'Petugas') ?>
        </p>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <?php if ($canManagePO): ?>
            <?php if ($po->isDraft()): ?>
                <form action="/purchase-orders/<?= $po->getId() ?>/order" method="POST" style="display: inline;" onsubmit="return confirm('Konfirmasi dan kirim pesanan PO ini ke pemasok? Status akan berubah menjadi Ordered.');">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>Kirim Pesanan (Mark as Ordered)</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($po->canCancel()): ?>
                <form action="/purchase-orders/<?= $po->getId() ?>/cancel" method="POST" style="display: inline;" onsubmit="return confirm('Batalkan Purchase Order ini? Tindakan ini tidak dapat diurungkan.');">
                    <button type="submit" class="btn btn-danger">
                        Batalkan PO
                    </button>
                </form>
            <?php endif; ?>
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

<!-- Summary Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem;">
    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Nilai PO</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;">
            Rp <?= number_format($po->getTotalAmount(), 0, ',', '.') ?>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            <?= count($po->getItems()) ?> jenis item produk
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Qty Dipesan</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;">
            <?= number_format($po->getTotalOrdered(), 0, ',', '.') ?>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Target kuantitas pengadaan
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Qty Diterima</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; color: var(--color-success);">
            <?= number_format($po->getTotalReceived(), 0, ',', '.') ?>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Sudah masuk ke stok fisik
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Sisa Belum Diterima</span>
        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; color: <?= $po->getTotalRemaining() > 0 ? 'var(--color-warning)' : 'var(--text-muted)' ?>;">
            <?= number_format($po->getTotalRemaining(), 0, ',', '.') ?>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Menunggu kedatangan barang
        </div>
    </div>
</div>

<!-- Ordered Items Table -->
<div class="card table-card" style="margin-bottom: 2rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0;">Rincian Item Purchase Order</h2>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Progres Penerimaan: <strong><?= $po->getTotalReceived() ?> / <?= $po->getTotalOrdered() ?></strong> unit
        </span>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th style="text-align: right;">Dipesan</th>
                    <th style="text-align: right;">Diterima</th>
                    <th style="text-align: right;">Sisa Belum Datang</th>
                    <th style="text-align: right;">Harga Satuan</th>
                    <th style="text-align: right;">Subtotal</th>
                    <th style="text-align: center;">Status Item</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($po->getItems() as $item): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($item->getProductName() ?? '-') ?></div>
                            <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                <?= htmlspecialchars($item->getProductSku() ?? '-') ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            <?= number_format($item->getQuantityOrdered(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                        </td>
                        <td style="text-align: right; font-weight: 600; color: var(--color-success);">
                            <?= number_format($item->getQuantityReceived(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: <?= $item->getRemainingQuantity() > 0 ? 'var(--color-warning)' : 'var(--text-muted)' ?>;">
                            <?= number_format($item->getRemainingQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                        </td>
                        <td style="text-align: right;">
                            Rp <?= number_format($item->getUnitPrice(), 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            Rp <?= number_format($item->getSubtotal(), 0, ',', '.') ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($item->isFullyReceived()): ?>
                                <span class="badge badge-success">Lengkap (Received)</span>
                            <?php elseif ($item->getQuantityReceived() > 0): ?>
                                <span class="badge badge-warning">Sebagian (Partial)</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Belum Datang</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: 700;">
                    <td>TOTAL</td>
                    <td style="text-align: right;"><?= number_format($po->getTotalOrdered(), 0, ',', '.') ?></td>
                    <td style="text-align: right; color: var(--color-success);"><?= number_format($po->getTotalReceived(), 0, ',', '.') ?></td>
                    <td style="text-align: right; color: var(--color-warning);"><?= number_format($po->getTotalRemaining(), 0, ',', '.') ?></td>
                    <td></td>
                    <td style="text-align: right;">Rp <?= number_format($po->getTotalAmount(), 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Goods Receipt Processing Section (PO-01) -->
<?php if ($po->canReceive() && $canManagePO): ?>
    <div class="card" style="margin-bottom: 2rem; padding: 1.75rem; border: 1px solid var(--border-color);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Proses Penerimaan Barang (Goods Receipt)</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0;">
                    Catat kedatangan fisik barang ke fasilitas <strong><?= htmlspecialchars($po->getWarehouseName() ?? 'Gudang') ?></strong>. Penerimaan parsial (sebagian) didukung.
                </p>
            </div>
            <span class="badge badge-info">Transaksi Atomik Database</span>
        </div>

        <form action="/purchase-orders/<?= $po->getId() ?>/receipt" method="POST">
            <div class="table-container" style="margin-bottom: 1.25rem;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Produk / SKU</th>
                            <th style="text-align: right; width: 140px;">Sisa Pesanan</th>
                            <th style="text-align: right; width: 220px;">Qty Diterima Saat Ini</th>
                            <th style="width: 140px; text-align: center;">Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($po->getItems() as $item): ?>
                            <?php if ($item->getRemainingQuantity() > 0): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($item->getProductName() ?? '-') ?></div>
                                        <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                            <?= htmlspecialchars($item->getProductSku() ?? '-') ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?= number_format($item->getRemainingQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                            <input 
                                                type="number" 
                                                name="received_quantities[<?= $item->getId() ?>]" 
                                                id="qty_input_<?= $item->getId() ?>"
                                                class="form-control" 
                                                min="0" 
                                                max="<?= $item->getRemainingQuantity() ?>" 
                                                value="0" 
                                                style="width: 130px; text-align: right; font-weight: 600;"
                                                required
                                            >
                                            <span style="font-size: 0.85rem; color: var(--text-muted); width: 35px; text-align: left;">
                                                <?= htmlspecialchars($item->getProductUnit() ?? 'pcs') ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <button 
                                            type="button" 
                                            class="btn btn-secondary btn-sm" 
                                            onclick="document.getElementById('qty_input_<?= $item->getId() ?>').value = '<?= $item->getRemainingQuantity() ?>';"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.75rem;"
                                        >
                                            Terima Semua
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr style="opacity: 0.6;">
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($item->getProductName() ?? '-') ?></div>
                                        <span style="font-family: var(--font-mono); font-size: 0.8rem;"><?= htmlspecialchars($item->getProductSku() ?? '-') ?></span>
                                    </td>
                                    <td style="text-align: right;">0</td>
                                    <td style="text-align: right; color: var(--color-success); font-weight: 600;">
                                        Sudah Lengkap
                                    </td>
                                    <td style="text-align: center;">-</td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="notes" class="form-label">Catatan Penerimaan / No. Surat Jalan Vendor (Opsional)</label>
                <input 
                    type="text" 
                    id="notes" 
                    name="notes" 
                    class="form-control" 
                    placeholder="contoh: Diterima dalam kondisi baik via Truk Ekspedisi SJ-8899"
                >
            </div>

            <div style="background: rgba(15, 23, 42, 0.04); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                <strong>Aturan Integritas Logistik:</strong> Saat tombol ditekan, sistem mengeksekusi penambahan stok pada <code>product_stock</code> dan pencatatan riwayat pada <code>stock_ledger</code> secara <strong>atomik dalam satu transaksi database</strong>. Jika ada kegagalan, seluruh perubahan akan otomatis di-rollback.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Proses penerimaan barang ini sekarang?');">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Simpan & Catat Penerimaan Barang</span>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Stock Ledger History for this PO -->
<div class="card table-card" style="margin-bottom: 2rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0;">Riwayat Mutasi Buku Besar (Stock Ledger) PO Ini</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0;">
                Catatan mutasi riil penambahan stok yang dihasilkan dari transaksi Goods Receipt PO ini.
            </p>
        </div>
        <a href="/stock-ledger" class="btn btn-secondary btn-sm">Lihat Semua Ledger &rarr;</a>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 150px;">Waktu Mutasi</th>
                    <th>Tipe Transaksi</th>
                    <th>Produk</th>
                    <th>Gudang Tujuan</th>
                    <th style="text-align: right;">Jumlah (+Qty)</th>
                    <th>Catatan Mutasi</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stockLedgerHistory)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            Belum ada mutasi penerimaan barang yang dicatat untuk Purchase Order ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stockLedgerHistory as $log): ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= date('d M Y H:i:s', strtotime($log->getCreatedAt())) ?>
                            </td>
                            <td>
                                <span class="badge badge-success"><?= htmlspecialchars($log->getTransactionType()) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log->getProductName() ?? '-') ?></strong>
                                <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted); display: block;">
                                    <?= htmlspecialchars($log->getProductSku() ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($log->getWarehouseName() ?? '-') ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-success);">
                                +<?= number_format($log->getQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($log->getProductUnit() ?? 'pcs') ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= htmlspecialchars($log->getNotes() ?? '-') ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= htmlspecialchars($log->getCreatedByName() ?? '-') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
