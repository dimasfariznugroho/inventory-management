<?php
/**
 * Shared Product Form Fields
 * Variables expected:
 *  - $old (array)
 *  - $errors (array)
 *  - $categories (Category[])
 */
?>
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
    <div class="form-group">
        <label for="sku" class="form-label">SKU Produk <span class="text-danger">*</span></label>
        <input 
            type="text" 
            id="sku" 
            name="sku" 
            class="form-control <?= isset($errors['sku']) ? 'is-invalid' : '' ?>" 
            placeholder="contoh: PRD-LAP-001"
            value="<?= htmlspecialchars($old['sku'] ?? '') ?>"
            required
        >
        <?php if (isset($errors['sku'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['sku']) ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="name" class="form-label">Nama Produk <span class="text-danger">*</span></label>
        <input 
            type="text" 
            id="name" 
            name="name" 
            class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
            placeholder="contoh: Laptop ThinkPad X1 Carbon"
            value="<?= htmlspecialchars($old['name'] ?? '') ?>"
            required
        >
        <?php if (isset($errors['name'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['name']) ?></span>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-top: 1rem;">
    <div class="form-group">
        <label for="category_id" class="form-label">Kategori Produk <span class="text-danger">*</span></label>
        <select id="category_id" name="category_id" class="form-control <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
            <option value="">-- Pilih Kategori --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat->getId() ?>" <?= (isset($old['category_id']) && (int)$old['category_id'] === $cat->getId()) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat->getName()) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['category_id']) ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="unit" class="form-label">Satuan Unit <span class="text-danger">*</span></label>
        <input 
            type="text" 
            id="unit" 
            name="unit" 
            class="form-control <?= isset($errors['unit']) ? 'is-invalid' : '' ?>" 
            placeholder="pcs, box, kg, meter..."
            value="<?= htmlspecialchars($old['unit'] ?? 'pcs') ?>"
            required
        >
        <?php if (isset($errors['unit'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['unit']) ?></span>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem;">
    <div class="form-group">
        <label for="purchase_price" class="form-label">Harga Beli (Modal) Rp <span class="text-danger">*</span></label>
        <input 
            type="number" 
            id="purchase_price" 
            name="purchase_price" 
            step="0.01" 
            min="0" 
            class="form-control <?= isset($errors['purchase_price']) ? 'is-invalid' : '' ?>" 
            value="<?= htmlspecialchars((string)($old['purchase_price'] ?? '0')) ?>"
            required
        >
        <?php if (isset($errors['purchase_price'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['purchase_price']) ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="selling_price" class="form-label">Harga Jual Rp <span class="text-danger">*</span></label>
        <input 
            type="number" 
            id="selling_price" 
            name="selling_price" 
            step="0.01" 
            min="0" 
            class="form-control <?= isset($errors['selling_price']) ? 'is-invalid' : '' ?>" 
            value="<?= htmlspecialchars((string)($old['selling_price'] ?? '0')) ?>"
            required
        >
        <?php if (isset($errors['selling_price'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['selling_price']) ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="reorder_point" class="form-label">Reorder Point <span class="text-danger">*</span></label>
        <input 
            type="number" 
            id="reorder_point" 
            name="reorder_point" 
            min="0" 
            class="form-control <?= isset($errors['reorder_point']) ? 'is-invalid' : '' ?>" 
            value="<?= htmlspecialchars((string)($old['reorder_point'] ?? '10')) ?>"
            required
        >
        <?php if (isset($errors['reorder_point'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['reorder_point']) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="form-group" style="margin-top: 1.25rem;">
    <?php if (!empty($old['image_path'])): ?>
        <label class="form-label">Foto / Gambar Produk Saat Ini</label>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem;">
            <img src="<?= htmlspecialchars($old['image_path']) ?>" alt="Thumbnail" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars(basename($old['image_path'])) ?></span>
        </div>
        <label for="image" class="form-label">Ganti Foto Baru (Opsional)</label>
    <?php else: ?>
        <label for="image" class="form-label">Foto / Gambar Produk (Opsional)</label>
    <?php endif; ?>
    <input 
        type="file" 
        id="image" 
        name="image" 
        class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>" 
        accept="image/jpeg,image/png,image/webp"
    >
    <span class="form-text" style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
        Format diperbolehkan: JPEG, PNG, WEBP. Maksimal ukuran file: 2MB.
    </span>
    <?php if (isset($errors['image'])): ?>
        <span class="field-error"><?= htmlspecialchars($errors['image']) ?></span>
    <?php endif; ?>
</div>
