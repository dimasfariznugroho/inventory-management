<div class="page-header">
    <div>
        <h1 class="page-title">Buat Purchase Order Baru</h1>
        <p class="page-subtitle">Pemesanan stok barang ke pemasok menuju fasilitas gudang tujuan (PO-01).</p>
    </div>
    <div>
        <a href="/purchase-orders" class="btn btn-secondary">&larr; Kembali ke Daftar PO</a>
    </div>
</div>

<div class="form-layout-wrapper">
    <div class="card form-card" style="max-width: 960px; margin: 0 auto; padding: 2rem;">
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

        <form action="/purchase-orders/create" method="POST" class="standard-form" id="poForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="supplier_id" class="form-label">Pemasok (Supplier) <span class="text-danger">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="form-control" required>
                        <option value="">-- Pilih Pemasok --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier->getId() ?>" <?= (isset($old['supplier_id']) && (int)$old['supplier_id'] === $supplier->getId()) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($supplier->getName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="warehouse_id" class="form-label">Gudang Tujuan <span class="text-danger">*</span></label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control" required>
                        <option value="">-- Pilih Gudang Tujuan --</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= $wh->getId() ?>" <?= (isset($old['warehouse_id']) && (int)$old['warehouse_id'] === $wh->getId()) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh->getName()) ?> (<?= htmlspecialchars($wh->getLocation() ?? '-') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="order_date" class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                    <input 
                        type="date" 
                        id="order_date" 
                        name="order_date" 
                        class="form-control" 
                        value="<?= htmlspecialchars($old['order_date'] ?? date('Y-m-d')) ?>"
                        required
                    >
                </div>
            </div>

            <!-- Items Table -->
            <div style="margin-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Item Produk yang Dipesan</h2>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnAddItem">
                        + Tambah Baris Produk
                    </button>
                </div>

                <div class="table-container" style="margin-bottom: 1rem;">
                    <table class="data-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Produk</th>
                                <th style="width: 18%; text-align: right;">Jumlah (Qty)</th>
                                <th style="width: 22%; text-align: right;">Harga Beli Satuan (Rp)</th>
                                <th style="width: 20%; text-align: right;">Subtotal (Rp)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Dynamic rows appended via JS -->
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: 700;">
                                <td colspan="3" style="text-align: right;">TOTAL ESTIMASI NILAI PO:</td>
                                <td style="text-align: right;" id="grandTotalDisplay">Rp 0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <a href="/purchase-orders" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Simpan PO (Draft)</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const products = <?= json_encode(array_map(fn($p) => [
        'id'             => $p->getId(),
        'sku'            => $p->getSku(),
        'name'           => $p->getName(),
        'unit'           => $p->getUnit(),
        'purchase_price' => $p->getPurchasePrice(),
    ], $products)) ?>;

    const tbody = document.getElementById('itemsTableBody');
    const btnAdd = document.getElementById('btnAddItem');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    let rowIndex = 0;

    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function recalculateTotals() {
        let grandTotal = 0;
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            const qtyInput = row.querySelector('.item-qty');
            const priceInput = row.querySelector('.item-price');
            const subtotalCell = row.querySelector('.item-subtotal');

            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const subtotal = qty * price;
            grandTotal += subtotal;

            subtotalCell.textContent = 'Rp ' + formatNumber(subtotal);
        });

        grandTotalDisplay.textContent = 'Rp ' + formatNumber(grandTotal);
    }

    function addRow(selectedProductId = null, qty = 1, unitPrice = null) {
        const tr = document.createElement('tr');
        const idx = rowIndex++;

        let productOptions = '<option value="">-- Pilih Produk --</option>';
        products.forEach(p => {
            const isSelected = selectedProductId && selectedProductId == p.id ? 'selected' : '';
            productOptions += `<option value="${p.id}" data-price="${p.purchase_price}" data-unit="${p.unit}" ${isSelected}>
                [${p.sku}] ${p.name} (${p.unit})
            </option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${idx}][product_id]" class="form-control item-product" required>
                    ${productOptions}
                </select>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]" class="form-control item-qty" min="1" value="${qty}" style="text-align: right;" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${idx}][unit_price]" class="form-control item-price" value="${unitPrice !== null ? unitPrice : 0}" style="text-align: right;" required>
            </td>
            <td style="text-align: right; font-weight: 600;" class="item-subtotal">
                Rp 0
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn btn-secondary btn-sm btn-remove-row" style="padding: 0.25rem 0.5rem;" title="Hapus baris">&times;</button>
            </td>
        `;

        tbody.appendChild(tr);

        const select = tr.querySelector('.item-product');
        const priceInput = tr.querySelector('.item-price');
        const qtyInput = tr.querySelector('.item-qty');
        const btnRemove = tr.querySelector('.btn-remove-row');

        select.addEventListener('change', function() {
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.price) {
                priceInput.value = selectedOpt.dataset.price;
            }
            recalculateTotals();
        });

        priceInput.addEventListener('input', recalculateTotals);
        qtyInput.addEventListener('input', recalculateTotals);

        btnRemove.addEventListener('click', function() {
            if (tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
                recalculateTotals();
            } else {
                alert('Minimal harus ada 1 item produk dalam Purchase Order.');
            }
        });

        recalculateTotals();
    }

    btnAdd.addEventListener('click', () => addRow());

    // Initial rows (sticky from $old['items'] if validation failed, else 1 default row)
    <?php if (!empty($old['items'])): ?>
        <?php foreach ($old['items'] as $oldItem): ?>
            addRow(<?= json_encode($oldItem['product_id'] ?? null) ?>, <?= json_encode((int)($oldItem['quantity'] ?? 1)) ?>, <?= json_encode((float)($oldItem['unit_price'] ?? 0)) ?>);
        <?php endforeach; ?>
    <?php else: ?>
        addRow();
    <?php endif; ?>
});
</script>
