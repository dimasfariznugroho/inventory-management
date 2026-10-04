# Technical Debt & Architecture Guidelines

Dokumen ini mencatat catatan teknis, audit repository, pedoman standar implementasi, dan technical debt yang perlu dijaga konsistensinya di seluruh siklus pengembangan aplikasi.

---

## 1. Dynamic SQL Query & Parameter Binding Guideline

### 1.1 Latar Belakang Masalah (Root Cause SQLSTATE[HY093])
Pada `app/Repository/Database.php`, koneksi PDO dikonfigurasi dengan:
```php
PDO::ATTR_EMULATE_PREPARES => false
```
Artinya, PDO menggunakan **native prepared statement** dari MySQL engine langsung, bukan emulasi PHP. Dalam mode native prepare:
1. **Named parameter tidak boleh dipakai berulang dengan 1 binding**: Jika SQL menggunakan placeholder bernama berulang seperti `(p.name LIKE :search OR p.sku LIKE :search)` dan parameter hanya di-bind sekali (`['search' => "%term%"]`), MySQL native driver akan melempar error:
   ```
   SQLSTATE[HY093]: Invalid parameter number
   ```
2. **Kondisi terpisah berisiko desinkronisasi**: Memisahkan blok penambahan klausa SQL (`$sql .= ...`) dari blok penambahan `$params` membuat jumlah tanda placeholder (`?` atau `:name`) dalam query tidak sinkron dengan jumlah elemen dalam array `$params` saat filter opsional (seperti category atau status) bernilai kosong atau tidak dikirim.

---

### 1.2 Pola Wajib untuk Dynamic Query (Standard Pattern)
Semua method repository yang menyusun query secara dinamis **WAJIB** mematuhi aturan berikut:
1. Menggunakan array `$conditions = []` dan `$params = []`.
2. Penambahan klausa SQL dan penambahan value ke `$params` harus berada dalam **SATU BLOK KONDISI IF YANG SAMA**.
3. Gunakan positional placeholder (`?`). Jika sebuah klausa membutuhkan lebih dari satu placeholder (misalnya pencarian nama ATAU SKU), tambahkan parameter ke `$params` sebanyak jumlah placeholder yang ada.
4. Klausa WHERE dirakit menggunakan `implode(' AND ', $conditions)`.

Contoh implementasi standar:
```php
$conditions = [];
$params = [];

if ($search !== null && trim($search) !== '') {
    $conditions[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $term = '%' . trim($search) . '%';
    $params[] = $term;
    $params[] = $term;
}

if ($categoryId !== null && (int) $categoryId > 0) {
    $conditions[] = 'p.category_id = ?';
    $params[] = (int) $categoryId;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT p.*, c.name AS category_name,
           COALESCE(SUM(ps.quantity), 0) AS total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    {$whereClause}
    GROUP BY p.id, c.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

---

## 2. Status Audit Repository Terkait Query Dinamis

| Repository | Status | Catatan & Tindakan |
| :--- | :--- | :--- |
| `MySQLProductRepository` | **FIXED** | Method `findAllWithStock()` telah direfaktor ke pola `$conditions` + positional `?` dalam blok `if` yang sama. |
| `MySQLPurchaseOrderRepository` | **STANDARDIZED** | Method `findAll(?string $status, ?int $warehouseId)` sebelumnya memakai `WHERE 1=1` dan named params `:status`, `:warehouse_id`. Telah direfaktor mengikuti pola `$conditions` + positional `?` agar seragam. |
| `MySQLWarehouseRepository` | CLEAN / STATIC | Saat ini masih query statis. Saat penambahan filter lokasi/status di phase mendatang, wajib memakai pola `$conditions` di atas. |
| `MySQLCategoryRepository` | CLEAN / STATIC | Saat ini masih query statis. Jika di masa depan ada filter nama kategori, wajib memakai pola `$conditions`. |
| `MySQLSupplierRepository` | CLEAN / STATIC | Saat ini masih query statis. |
| `MySQLCustomerRepository` | CLEAN / STATIC | Saat ini masih query statis. |
| `MySQLStockRepository` | CLEAN / STATIC | Query statis berdasarkan ID produk dan gudang. |
| `MySQLStockLedgerRepository` | CLEAN / STATIC | Query statis dengan filter produk/gudang yang selalu pasti di-bind. |
| `MySQLUserRepository` | CLEAN / STATIC | Query statis dengan prepared statement standar. |

---

## 3. Checklist Sebelum Merge Filter Baru di Repository
Setiap kali menambahkan filter pencarian baru di repository mana pun:
- [ ] Apakah jumlah `?` pada string persis sama dengan jumlah `array_push` / `$params[] = ...` di dalam blok `if` yang sama?
- [ ] Apakah query sudah diuji ketika SEMUA filter bernilai kosong/null?
- [ ] Apakah query sudah diuji ketika HANYA 1 filter (misal keyword saja) yang terisi?
- [ ] Apakah tidak ada lagi `PDO::ATTR_EMULATE_PREPARES` dependency (tidak menggunakan named parameter berulang)?

---

## 4. Kebijakan Arsitektur Mutasi Stok: Larangan Edit Stok Manual di UI

### 4.1 Prinsip Fundamental & Keputusan Desain
Dalam arsitektur InventoryHub, **tidak ada dan tidak boleh disediakan form atau endpoint untuk mengedit angka stok secara langsung/manual di antarmuka pengguna (UI)** (misalnya tidak ada field input "stok" di form tambah/edit produk, tidak ada endpoint `POST /products/{id}/stock`, atau tombol override manual).

### 4.2 Justifikasi Integritas Data & Audit Trail
1. **Single Source of Truth & Audit Immutability**:
   - Angka stok fisik (`product_stock.quantity`) adalah saldo terakumulasi (materialized balance) yang mencerminkan pergerakan fisik barang riil di setiap gudang.
   - Mengizinkan input stok manual bebas akan merusak integritas sistem dan menghilangkan jejak audit (*audit trail bypass*), menciptakan selisih (*reconciliation gap*) antara angka di `product_stock` dan total mutasi di `stock_ledger`.
2. **Keterikatan Wajib Service + Ledger (Atomic Transaction)**:
   - Setiap mutasi kuantitas stok **HANYA** boleh dieksekusi melalui domain service resmi yang dibungkus dalam transaksi database atomik (`PDO::beginTransaction()` / `commit()` / `rollBack()`):
     - **Inflow (Penerimaan Barang)**: Melalui `PurchaseOrderService::processGoodsReceipt()` saat memproses Goods Receipt dari Purchase Order berstatus `Ordered` atau `PartiallyReceived`. Service ini menambah stok di `product_stock` sekaligus mencatat baris mutasi bertipe `Receipt` di `stock_ledger`.
     - **Outflow (Pengeluaran Barang)**: Melalui `SalesOrderService` saat fulfillment Sales Order (mencatat mutasi bertipe `Issue` di `stock_ledger`).
     - **Penyesuaian Fisik (Stock Adjustment)**: Hanya boleh dilakukan melalui modul audit opname resmi dengan entri bertipe `Adjustment` dan catatan pertanggungjawaban yang jelas di `stock_ledger`.
3. **Bukti Struktural pada Implementasi**:
   - Form Tambah Produk (`/admin/products/create`) dan Form Edit Produk (`/admin/products/{id}/edit`) secara ketat hanya menerima atribut master produk: `sku`, `name`, `category_id`, `unit`, `purchase_price`, `selling_price`, `reorder_point`, `image`, dan `is_active`.
   - Tidak ada kolom input kuantitas stok di form tersebut.
   - Endpoint detail produk (`/products/{id}`) hanya menampilkan kuantitas stok secara read-only berdasarkan agregasi per gudang dari tabel `product_stock`.

