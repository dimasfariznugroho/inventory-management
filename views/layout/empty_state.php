<?php
/**
 * Reusable Informative Empty State Component (VIEW-01)
 *
 * Variables expected:
 * - string $emptyTitle
 * - string $emptyMessage
 * - string|null $emptyActionUrl
 * - string|null $emptyActionText
 * - string|null $emptyResetUrl
 */
?>
<div class="empty-state-card card" style="text-align: center; padding: 3.5rem 1.5rem; margin: 1.5rem 0; border: 2px dashed var(--border-color, #e5e7eb); background: #fafafa;">
    <div style="font-size: 2.75rem; margin-bottom: 0.75rem; line-height: 1;">🔍</div>
    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-color, #111827); margin-bottom: 0.5rem;">
        <?= htmlspecialchars($emptyTitle ?? 'Tidak Ada Data Ditemukan') ?>
    </h3>
    <p style="font-size: 0.925rem; color: var(--text-muted, #6b7280); max-width: 480px; margin: 0 auto 1.5rem;">
        <?= htmlspecialchars($emptyMessage ?? 'Tidak ada catatan yang sesuai dengan filter atau kriteria pencarian yang aktif saat ini.') ?>
    </p>

    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <?php if (!empty($emptyResetUrl)): ?>
            <a href="<?= htmlspecialchars($emptyResetUrl) ?>" class="btn btn-secondary btn-sm">
                <span>Reset Filter & Pencarian</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($emptyActionUrl) && !empty($emptyActionText)): ?>
            <a href="<?= htmlspecialchars($emptyActionUrl) ?>" class="btn btn-primary btn-sm">
                <span><?= htmlspecialchars($emptyActionText) ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>
