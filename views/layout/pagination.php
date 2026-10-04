<?php
/**
 * Reusable Pagination Component (FIND-01)
 * Preserves all active $_GET filters and search queries across page changes.
 *
 * Variables expected:
 * - int $page (current page)
 * - int $totalPages (total number of pages)
 * - int $total (total records found)
 * - int $perPage (items per page, default 10)
 */

if (!isset($totalPages) || $totalPages <= 1) {
    if (isset($total) && $total > 0) {
        echo '<div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.875rem; text-align: right;">Total ' . (int)$total . ' data</div>';
    }
    return;
}

$page = max(1, (int) ($page ?? 1));
$perPage = (int) ($perPage ?? 10);
$startRecord = (($page - 1) * $perPage) + 1;
$endRecord = min($total, $page * $perPage);

// Build helper for query string preservation
$buildPageUrl = function (int $targetPage): string {
    $params = $_GET;
    $params['page'] = $targetPage;
    return '?' . http_build_query($params);
};
?>

<div class="pagination-wrapper" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 1.5rem; padding: 1rem 0; border-top: 1px solid var(--border-color, #e5e7eb);">
    <div class="pagination-info" style="font-size: 0.875rem; color: var(--text-muted, #6b7280);">
        Menampilkan <strong><?= $startRecord ?></strong> – <strong><?= $endRecord ?></strong> dari <strong><?= (int)$total ?></strong> data
    </div>

    <nav class="pagination-nav" aria-label="Navigasi Halaman" style="display: flex; gap: 0.35rem; align-items: center;">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($buildPageUrl($page - 1)) ?>" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.75rem;">
                &larr; Sebelumnya
            </a>
        <?php else: ?>
            <span class="btn btn-secondary btn-sm disabled" style="opacity: 0.5; cursor: not-allowed; padding: 0.4rem 0.75rem;">
                &larr; Sebelumnya
            </span>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="btn btn-primary btn-sm" style="padding: 0.4rem 0.75rem; font-weight: 700;">
                    <?= $i ?>
                </span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($buildPageUrl($i)) ?>" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.75rem;">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildPageUrl($page + 1)) ?>" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.75rem;">
                Berikutnya &rarr;
            </a>
        <?php else: ?>
            <span class="btn btn-secondary btn-sm disabled" style="opacity: 0.5; cursor: not-allowed; padding: 0.4rem 0.75rem;">
                Berikutnya &rarr;
            </span>
        <?php endif; ?>
    </nav>
</div>
