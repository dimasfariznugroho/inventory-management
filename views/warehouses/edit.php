<div class="page-header">
    <div>
        <h1 class="page-title">Edit Gudang: <?= htmlspecialchars($old['name'] ?? '') ?></h1>
        <p class="page-subtitle">Perbarui data gudang dan lokasi fasilitas (WH-01).</p>
    </div>
    <div>
        <a href="/admin/warehouses" class="btn btn-secondary">&larr; Kembali ke Daftar Gudang</a>
    </div>
</div>

<div class="form-layout-wrapper">
    <div class="card form-card" style="max-width: 650px; margin: 0 auto; padding: 2rem;">
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

        <form action="/admin/warehouses/<?= $old['id'] ?>/edit" method="POST" class="standard-form">
            <div class="form-group">
                <label for="name" class="form-label">Nama Gudang <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                    required
                >
                <?php if (isset($errors['name'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top: 1.25rem;">
                <label for="location" class="form-label">Lokasi / Alamat Fasilitas Gudang</label>
                <textarea 
                    id="location" 
                    name="location" 
                    class="form-control" 
                    rows="3" 
                ><?= htmlspecialchars($old['location'] ?? '') ?></textarea>
            </div>

            <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <a href="/admin/warehouses" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>
</div>
