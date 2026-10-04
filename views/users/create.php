<div class="page-header">
    <div>
        <h1 class="page-title">Tambah Pengguna Baru</h1>
        <p class="page-subtitle">Daftarkan akun staf baru ke dalam sistem Inventory & Order Management.</p>
    </div>
    <div>
        <a href="/admin/users" class="btn btn-secondary">&larr; Kembali ke Daftar</a>
    </div>
</div>

<div class="form-layout-wrapper">
    <div class="card form-card">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
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

        <form action="/admin/users/create" method="POST" class="standard-form">
            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                    placeholder="contoh: Ahmad Firdaus"
                    value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                    required
                >
                <?php if (isset($errors['name'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                    placeholder="contoh: ahmad@inventory.local"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                    required
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
                <span class="field-hint">Harus unik dan berformat email valid.</span>
            </div>

            <div class="form-row">
                <div class="form-group col-half">
                    <label for="password" class="form-label">Kata Sandi Awal <span class="text-danger">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                        placeholder="Minimal 6 karakter"
                        required
                    >
                    <?php if (isset($errors['password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group col-half">
                    <label for="role" class="form-label">Peran (Role) <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="form-control select-control <?= isset($errors['role']) ? 'is-invalid' : '' ?>" required>
                        <option value="Sales" <?= ($old['role'] ?? '') === 'Sales' ? 'selected' : '' ?>>Sales (Sales Representative)</option>
                        <option value="WarehouseStaff" <?= ($old['role'] ?? '') === 'WarehouseStaff' ? 'selected' : '' ?>>Warehouse Staff (Staf Gudang)</option>
                        <option value="Admin" <?= ($old['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin (Administrator)</option>
                    </select>
                    <?php if (isset($errors['role'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['role']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group checkbox-group">
                <label class="custom-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($old['is_active']) || $old['is_active']) ? 'checked' : '' ?>>
                    <span class="checkbox-box"></span>
                    <span class="checkbox-label">Aktifkan akun ini segera (user dapat login)</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <span>Simpan Pengguna</span>
                </button>
                <a href="/admin/users" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
