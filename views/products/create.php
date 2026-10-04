<div class="page-header">
    <div>
        <h1 class="page-title">Tambah Produk Baru</h1>
        <p class="page-subtitle">Daftarkan item produk baru ke dalam katalog dan master inventaris (PRD-01).</p>
    </div>
    <div>
        <a href="/products" class="btn btn-secondary">&larr; Kembali ke Katalog</a>
    </div>
</div>

<div class="form-layout-wrapper">
    <div class="card form-card" style="max-width: 800px; margin: 0 auto; padding: 2rem;">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <strong>Mohon perbaiki kesalahan berikut:</strong>
                    <ul style="margin-top: 0.35rem; padding-left: 1.25rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="/admin/products/create" method="POST" enctype="multipart/form-data" class="standard-form">
            <?php require __DIR__ . '/_form_fields.php'; ?>

            <div class="form-group" style="margin-top: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($old['is_active']) || $old['is_active']) ? 'checked' : '' ?>>
                    <span style="font-weight: 500;">Aktifkan produk di katalog</span>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <a href="/products" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Simpan Produk</span>
                </button>
            </div>
        </form>
    </div>
</div>
