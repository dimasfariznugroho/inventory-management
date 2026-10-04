<div class="page-header">
    <div>
        <h1 class="page-title">Master Data Gudang (WH-01 Multi-Lokasi)</h1>
        <p class="page-subtitle">Kelola daftar gudang fisik dan fasilitas penyimpanan stok inventaris.</p>
    </div>
    <div>
        <a href="/admin/warehouses/create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Tambah Gudang Baru</span>
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

<div class="card table-card">
    <div class="table-container">
        <table class="data-table" aria-label="Daftar Gudang">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Nama Gudang</th>
                    <th>Lokasi Fisik</th>
                    <th>Status</th>
                    <th>Didaftarkan</th>
                    <th style="text-align: right; width: 220px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($warehouses)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                            Belum ada fasilitas gudang yang didaftarkan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($warehouses as $wh): ?>
                        <tr>
                            <td>#<?= $wh->getId() ?></td>
                            <td>
                                <strong><?= htmlspecialchars($wh->getName()) ?></strong>
                            </td>
                            <td>
                                <span style="color: var(--text-muted); font-size: 0.9rem;">
                                    <?= htmlspecialchars($wh->getLocation() ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $wh->isActive() ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $wh->isActive() ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">
                                <?= date('d M Y H:i', strtotime($wh->getCreatedAt())) ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="/admin/warehouses/<?= $wh->getId() ?>/edit" class="btn btn-secondary btn-sm" title="Edit Data Gudang">
                                        Edit
                                    </a>
                                    <form action="/admin/warehouses/<?= $wh->getId() ?>/toggle" method="POST" style="display: inline;" onsubmit="return confirm('Ubah status aktif gudang ini?');">
                                        <button type="submit" class="btn <?= $wh->isActive() ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                                            <?= $wh->isActive() ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <form action="/admin/warehouses/<?= $wh->getId() ?>/delete" method="POST" style="display: inline;" onsubmit="return confirm('Hapus gudang ini secara permanen? Catatan: Gudang yang memiliki catatan stok tidak dapat dihapus.');">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus Gudang">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
