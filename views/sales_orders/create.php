<div class="page-header">
    <div>
        <h1 class="page-title">Buat Sales Order Baru</h1>
        <p class="page-subtitle">Pemesanan penjualan barang ke pelanggan dari fasilitas gudang asal (SO-01).</p>
    </div>
    <div>
        <a href="/sales-orders" class="btn btn-secondary">&larr; Kembali ke Daftar SO</a>
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

        <form action="/sales-orders/create" method="POST" class="standard-form" id="soForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="customer_id" class="form-label">Pelanggan (Customer) <span class="text-danger">*</span></label>
                    <select id="customer_id" name="customer_id" class="form-control" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer->getId() ?>" <?= (isset($old['customer_id']) && (int)$old['customer_id'] === $customer->getId()) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($customer->getName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="warehouse_id" class="form-label">Gudang Asal Pengeluaran <span class="text-danger">*</span></label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control" required>
                        <option value="">-- Pilih Gudang Asal --</option>
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
                    <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Item Produk yang Dipesan Pelanggan</h2>
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
                                <th style="width: 22%; text-align: right;">Harga Jual Satuan (Rp)</th>
                                <th style="width: 20%; text-align: right;">Subtotal (Rp)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Dynamic rows appended via JS -->
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: 700;">
                                <td colspan="3" style="text-align: right;">TOTAL ESTIMASI NILAI SO:</td>
                                <td style="text-align: right; font-family: var(--font-mono);" id="grandTotalDisplay">Rp 0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <a href="/sales-orders" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    Simpan Sales Order (Draft) &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const productsCatalog = <?= json_encode(array_map(fn($p) => [
    'id'            => $p->getId(),
    'name'          => $p->getName(),
    'sku'           => $p->getSku(),
    'unit'          => $p->getUnit(),
    'selling_price' => $p->getSellingPrice(),
    'total_stock'   => $p->getTotalStock()
], $products)) ?>;

let rowCount = 0;

function createProductRow(initialData = null) {
    const tbody = document.getElementById('itemsTableBody');
    const idx = rowCount++;

    const tr = document.createElement('tr');
    tr.id = `item-row-${idx}`;

    let optionsHtml = '<option value="">-- Pilih Produk --</option>';
    productsCatalog.forEach(p => {
        const isSel = initialData && initialData.product_id == p.id ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" data-price="${p.selling_price}" data-unit="${p.unit}" ${isSel}>${p.sku} - ${p.name} (Stok: ${p.total_stock} ${p.unit})</option>`;
    });

    const initQty = initialData ? initialData.quantity : 1;
    const initPrice = initialData ? initialData.unit_price : 0;

    tr.innerHTML = `
        <td>
            <select name="items[${idx}][product_id]" class="form-control select-product" required onchange="handleProductChange(${idx})">
                ${optionsHtml}
            </select>
        </td>
        <td>
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <input type="number" name="items[${idx}][quantity]" class="form-control input-qty text-right" min="1" value="${initQty}" required oninput="calculateRow(${idx})">
                <span class="unit-label" id="unit-label-${idx}" style="font-size: 0.8rem; color: var(--text-muted); min-width: 28px;">unit</span>
            </div>
        </td>
        <td>
            <input type="number" name="items[${idx}][unit_price]" class="form-control input-price text-right" min="0" step="100" value="${initPrice}" required oninput="calculateRow(${idx})">
        </td>
        <td style="text-align: right; font-weight: 600; font-family: var(--font-mono); vertical-align: middle;" id="subtotal-${idx}">
            Rp 0
        </td>
        <td style="text-align: center; vertical-align: middle;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="removeRow(${idx})" title="Hapus baris" style="padding: 0.25rem 0.5rem; color: var(--color-danger);">
                &times;
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    if (initialData && initialData.product_id) {
        handleProductChange(idx, false);
    } else {
        calculateRow(idx);
    }
}

function handleProductChange(idx, resetPrice = true) {
    const row = document.getElementById(`item-row-${idx}`);
    if (!row) return;

    const select = row.querySelector('.select-product');
    const selectedOpt = select.options[select.selectedIndex];

    if (selectedOpt && selectedOpt.value) {
        const price = selectedOpt.getAttribute('data-price') || 0;
        const unit = selectedOpt.getAttribute('data-unit') || 'unit';

        document.getElementById(`unit-label-${idx}`).textContent = unit;
        if (resetPrice) {
            row.querySelector('.input-price').value = price;
        }
    }
    calculateRow(idx);
}

function calculateRow(idx) {
    const row = document.getElementById(`item-row-${idx}`);
    if (!row) return;

    const qty = parseFloat(row.querySelector('.input-qty').value) || 0;
    const price = parseFloat(row.querySelector('.input-price').value) || 0;
    const subtotal = qty * price;

    document.getElementById(`subtotal-${idx}`).textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let grandTotal = 0;
    const rows = document.querySelectorAll('#itemsTableBody tr');

    rows.forEach(tr => {
        const qty = parseFloat(tr.querySelector('.input-qty')?.value) || 0;
        const price = parseFloat(tr.querySelector('.input-price')?.value) || 0;
        grandTotal += (qty * price);
    });

    document.getElementById('grandTotalDisplay').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
}

function removeRow(idx) {
    const row = document.getElementById(`item-row-${idx}`);
    if (row) {
        row.remove();
        calculateGrandTotal();
    }
}

document.getElementById('btnAddItem').addEventListener('click', () => {
    createProductRow();
});

// Initialize with at least 1 row
document.addEventListener('DOMContentLoaded', () => {
    <?php if (!empty($old['items'])): ?>
        <?php foreach ($old['items'] as $item): ?>
            createProductRow(<?= json_encode($item) ?>);
        <?php endforeach; ?>
    <?php else: ?>
        createProductRow();
    <?php endif; ?>
});
</script>
