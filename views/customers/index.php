<div class="page-header">
    <div>
        <h1 class="page-title">Master Data Pelanggan (Customers)</h1>
        <p class="page-subtitle">Kelola direktori data pembeli / pelanggan untuk transaksi Sales Order (SO).</p>
    </div>
    <div>
        <a href="/admin/customers/create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Tambah Pelanggan</span>
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
        <table class="data-table" aria-label="Daftar Pelanggan">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Nama Pelanggan</th>
                    <th>Kontak / Telp</th>
                    <th>Alamat Pengiriman</th>
                    <th>Status</th>
                    <th>Didaftarkan</th>
                    <th style="text-align: right; width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                            Belum ada data pelanggan yang didaftarkan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>#<?= $customer->getId() ?></td>
                            <td>
                                <strong><?= htmlspecialchars($customer->getName()) ?></strong>
                            </td>
                            <td>
                                <span style="font-size: 0.9rem;"><?= htmlspecialchars($customer->getContact() ?? '-') ?></span>
                            </td>
                            <td>
                                <span style="color: var(--text-muted); font-size: 0.85rem;">
                                    <?= htmlspecialchars($customer->getAddress() ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $customer->isActive() ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $customer->isActive() ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">
                                <?= date('d M Y H:i', strtotime($customer->getCreatedAt())) ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="/admin/customers/<?= $customer->getId() ?>/edit" class="btn btn-secondary btn-sm" title="Edit Data Pelanggan">
                                        Edit
                                    </a>
                                    <form action="/admin/customers/<?= $customer->getId() ?>/toggle" method="POST" style="display: inline;" onsubmit="return confirm('Ubah status aktif pelanggan ini?');">
                                        <button type="submit" class="btn <?= $customer->isActive() ? 'btn-danger' : 'btn-primary' ?> btn-sm">
                                            <?= $customer->isActive() ? 'Nonaktifkan' : 'Aktifkan' ?>
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
