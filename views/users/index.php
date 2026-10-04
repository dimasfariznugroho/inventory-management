<div class="page-header">
    <div>
        <h1 class="page-title">Manajemen Pengguna (USR-01)</h1>
        <p class="page-subtitle">Kelola akun Sales, Warehouse Staff, dan Administrator sistem.</p>
    </div>
    <div>
        <a href="/admin/users/create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>Tambah Pengguna Baru</span>
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
    <div class="table-responsive">
        <table class="data-table" aria-label="Daftar Pengguna">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Nama Pengguna</th>
                    <th>Email</th>
                    <th>Peran (Role)</th>
                    <th>Status Akun</th>
                    <th>Waktu Dibuat</th>
                    <th style="text-align: right; width: 220px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="table-empty">Belum ada data pengguna yang terdaftar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-mono"><?= $u->getId() ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm"><?= strtoupper(substr($u->getName(), 0, 1)) ?></div>
                                    <span class="font-semibold"><?= htmlspecialchars($u->getName()) ?></span>
                                    <?php if ($u->getId() === ($currentUser['id'] ?? 0)): ?>
                                        <span class="badge badge-primary badge-xs">Anda</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-mono"><?= htmlspecialchars($u->getEmail()) ?></td>
                            <td>
                                <span class="badge badge-role badge-<?= strtolower($u->getRole()) ?>">
                                    <?= htmlspecialchars($u->getRole()) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u->isActive()): ?>
                                    <span class="status-pill status-active">
                                        <span class="pill-dot"></span>
                                        <span>Aktif</span>
                                    </span>
                                <?php else: ?>
                                    <span class="status-pill status-inactive">
                                        <span class="pill-dot"></span>
                                        <span>Nonaktif</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted text-sm">
                                <?= htmlspecialchars($u->getCreatedAt() ?? '-') ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="/admin/users/<?= $u->getId() ?>/edit" class="btn-action btn-action-edit" title="Edit Pengguna">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        <span>Edit</span>
                                    </a>

                                    <?php if ($u->getId() !== ($currentUser['id'] ?? 0)): ?>
                                        <form action="/admin/users/<?= $u->getId() ?>/toggle-status" method="POST" class="inline-form" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif pengguna ini?')">
                                            <button type="submit" class="btn-action <?= $u->isActive() ? 'btn-action-deactivate' : 'btn-action-activate' ?>">
                                                <?php if ($u->isActive()): ?>
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="10"/>
                                                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                                    </svg>
                                                    <span>Nonaktifkan</span>
                                                <?php else: ?>
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                                    </svg>
                                                    <span>Aktifkan</span>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-dim text-xs" title="Tidak dapat menonaktifkan akun sendiri">(Akun Anda)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
