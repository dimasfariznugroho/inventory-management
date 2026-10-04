<div class="auth-wrapper">
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1 class="auth-title">Masuk ke Sistem</h1>
            <p class="auth-subtitle">Inventory & Order Management System</p>
        </div>

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

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <form action="/login" method="POST" class="auth-form">
            <div class="form-group">
                <label for="email" class="form-label">Alamat Email</label>
                <div class="input-wrapper">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="contoh: admin@inventory.local"
                        value="<?= htmlspecialchars($oldEmail ?? '') ?>"
                        required 
                        autocomplete="email"
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Kata Sandi</label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Masukkan kata sandi"
                        required 
                        autocomplete="current-password"
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <span>Masuk Sekarang</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </form>

        <div class="demo-accounts-card">
            <div class="demo-header">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <span>Akun Uji Coba Tersedia (Password: <code>password123</code>):</span>
            </div>
            <div class="demo-list">
                <button type="button" class="demo-item" onclick="fillDemo('admin@inventory.local')">
                    <span class="badge badge-role badge-admin">Admin</span>
                    <code>admin@inventory.local</code>
                </button>
                <button type="button" class="demo-item" onclick="fillDemo('sales1@inventory.local')">
                    <span class="badge badge-role badge-sales">Sales</span>
                    <code>sales1@inventory.local</code>
                </button>
                <button type="button" class="demo-item" onclick="fillDemo('warehouse1@inventory.local')">
                    <span class="badge badge-role badge-warehousestaff">Warehouse</span>
                    <code>warehouse1@inventory.local</code>
                </button>
                <button type="button" class="demo-item demo-item-inactive" onclick="fillDemo('inactive@inventory.local')">
                    <span class="badge badge-secondary">Nonaktif</span>
                    <code>inactive@inventory.local</code>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = 'password123';
}
</script>
