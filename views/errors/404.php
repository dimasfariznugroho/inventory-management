<div class="card" style="max-width: 600px; margin: 3rem auto; padding: 2.5rem; text-align: center;">
    <div style="width: 56px; height: 56px; margin: 0 auto 1.25rem; display: flex; align-items: center; justify-content: center; background: #f3f4f6; border-radius: 50%; color: #4b5563;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
    </div>
    <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: #111827;">404 - Halaman Tidak Ditemukan</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.75rem; line-height: 1.6;">
        <?= htmlspecialchars($message ?? 'Data atau alamat URL yang Anda tuju tidak ditemukan di dalam sistem.') ?>
    </p>
    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <a href="/" class="btn btn-primary">&larr; Kembali ke Dashboard</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Halaman Sebelumnya</a>
    </div>
</div>
