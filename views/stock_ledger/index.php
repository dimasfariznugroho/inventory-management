<div class="page-header">
    <div>
        <h1 class="page-title">Buku Besar Mutasi Stok (Stock Ledger)</h1>
        <p class="page-subtitle">Jurnal audit trail perubahan kuantitas fisik barang antar gudang (Receipt, Issue, Adjustment).</p>
    </div>
    <div>
        <a href="/purchase-orders" class="btn btn-secondary">&larr; Kembali ke Purchase Orders</a>
    </div>
</div>

<div class="card table-card">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 155px;">Waktu Mutasi</th>
                    <th style="width: 110px; text-align: center;">Tipe</th>
                    <th>Produk</th>
                    <th>Fasilitas Gudang</th>
                    <th style="text-align: right; width: 130px;">Jumlah Mutasi</th>
                    <th>Referensi Dokumen</th>
                    <th>Catatan Mutasi</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledgerEntries)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                            Belum ada catatan mutasi stok di dalam Stock Ledger.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ledgerEntries as $entry): ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                <?= date('d M Y H:i:s', strtotime($entry->getCreatedAt())) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($entry->getTransactionType() === 'Receipt'): ?>
                                    <span class="badge badge-success">Receipt (+)</span>
                                <?php elseif ($entry->getTransactionType() === 'Issue'): ?>
                                    <span class="badge badge-danger">Issue (-)</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Adjustment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($entry->getProductName() ?? '-') ?></strong>
                                <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted); display: block;">
                                    <?= htmlspecialchars($entry->getProductSku() ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($entry->getWarehouseName() ?? '-') ?></span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: <?= $entry->getTransactionType() === 'Receipt' ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                <?= $entry->getTransactionType() === 'Receipt' ? '+' : '-' ?><?= number_format($entry->getQuantity(), 0, ',', '.') ?> <?= htmlspecialchars($entry->getProductUnit() ?? 'pcs') ?>
                            </td>
                            <td>
                                <?php if ($entry->getReferenceType() === 'PurchaseOrder'): ?>
                                    <a href="/purchase-orders/<?= $entry->getReferenceId() ?>" style="font-weight: 600;">
                                        PO #<?= $entry->getReferenceId() ?>
                                    </a>
                                <?php elseif ($entry->getReferenceType() === 'SalesOrder'): ?>
                                    <a href="/sales-orders/<?= $entry->getReferenceId() ?>" style="font-weight: 600;">
                                        SO #<?= $entry->getReferenceId() ?>
                                    </a>
                                <?php else: ?>
                                    <span><?= htmlspecialchars($entry->getReferenceType()) ?> #<?= $entry->getReferenceId() ?></span>
                                <?php endif; ?>

                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= htmlspecialchars($entry->getNotes() ?? '-') ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= htmlspecialchars($entry->getCreatedByName() ?? '-') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
