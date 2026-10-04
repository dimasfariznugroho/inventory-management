<div class="card" style="max-width: 600px; margin: 3rem auto; padding: 2.5rem; text-align: center;">
    <div style="width: 56px; height: 56px; margin: 0 auto 1.25rem; display: flex; align-items: center; justify-content: center; background: #fee2e2; border-radius: 50%; color: #dc2626;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    </div>
    <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: #111827;">403 - Akses Ditolak</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.75rem; line-height: 1.6;">
        <?= htmlspecialchars($message ?? 'Anda tidak memiliki kewenangan atau izin untuk mengakses halaman ini.') ?>
    </p>
    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <a href="/" class="btn btn-primary">&larr; Kembali ke Dashboard</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Halaman Sebelumnya</a>
    </div>
</div>
