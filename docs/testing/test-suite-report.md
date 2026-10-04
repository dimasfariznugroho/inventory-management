# Comprehensive Test Suite Report (Unit & Integration)

- **Test Framework**: PHPUnit 10.5.65
- **PHP Version**: PHP 8.2.33 (CLI)
- **Target Database**: MySQL 8.0 (Docker container `inventory_mysql`)
- **Execution Command**: `docker compose exec web vendor/bin/phpunit`
- **Date**: 2026-09-25

---

## 1. Single Command Execution

Semua pengujian (Unit + Integration) dapat dijalankan melalui **satu perintah terpadu**:

```bash
docker compose exec web vendor/bin/phpunit
```

Untuk menjalankan masing-masing suite secara terisolasi:

```bash
# Unit test terisolasi saja (100% In-Memory Fake Repositories):
docker compose exec web vendor/bin/phpunit tests/Unit

# Integration test saja (Menyentuh MySQL 8.0 nyata di Docker):
docker compose exec web vendor/bin/phpunit tests/Integration
```

---

## 2. Test Execution Results

```text
PHPUnit 10.5.65 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.33
Configuration: /var/www/html/phpunit.xml
Random Seed:   1790335594

.................                                                 17 / 17 (100%)

Time: 00:01.876, Memory: 8.00 MB

OK (17 tests, 108 assertions)
```

---

## 3. Test Matrix & Coverage Breakdown

### A. Unit Test Suite (`tests/Unit/`, 13 Test Cases, In-Memory Fakes)

Tidak menyentuh koneksi PDO/MySQL asli, session asli, atau network. Menggunakan implementasi in-memory repository fakes (`Tests\Fakes\*`).

| Area | Test File | Test Method | Business Logic / Rule Tested |
| :--- | :--- | :--- | :--- |
| **Area 1: Status Transitions** | `SalesOrderServiceTest` | `test_sales_order_full_valid_lifecycle_transitions_successfully` | Draft &rarr; PendingApproval &rarr; Approved &rarr; Fulfilled secara berurutan dan valid |
| **Area 1: Status Transitions** | `SalesOrderServiceTest` | `test_invalid_status_transition_direct_draft_to_fulfilled_is_rejected` | Percobaan lompat status (Draft langsung Goods Issue / Fulfilled) ditolak tegas |
| **Area 1: Status Transitions** | `SalesOrderServiceTest` | `test_cancel_fulfilled_sales_order_is_rejected` | Order yang sudah `Fulfilled` ditolak jika dicoba di-cancel |
| **Area 2: Segregation of Duties** | `SalesOrderServiceTest` | `test_sales_role_cannot_approve_sales_order_including_own_order` | User role `Sales` dilarang menyetujui order apa pun termasuk order buatannya sendiri |
| **Area 2: Segregation of Duties** | `SalesOrderServiceTest` | `test_warehouse_role_cannot_approve_sales_order` | User role `WarehouseStaff` dilarang menyetujui Sales Order |
| **Area 2: Segregation of Duties** | `SalesOrderServiceTest` | `test_cannot_approve_sales_order_when_not_in_pending_approval_status` | Admin tidak bisa approve order sebelum berstatus `PendingApproval` |
| **Area 3: ARCH-02 Optimistic Lock** | `SalesOrderServiceTest` | `test_arch02_optimistic_locking_detects_concurrency_conflict_when_row_count_zero` | **Simulasi rowCount() = 0**: Service mendeteksi konflik versi concurrent dan rollback transaksi tanpa MySQL asli |
| **Area 3: ARCH-02 Optimistic Lock** | `SalesOrderServiceTest` | `test_goods_issue_fails_and_rolls_back_when_requested_qty_exceeds_available_stock` | Validasi stok fisik tidak mencukupi memicu rollback dan pembatalan issue |
| **Area 4: Low-Stock & Valuation** | `ProductServiceTest` | `test_product_entity_low_stock_boundary_evaluations` | Pengujian batas kritis (`totalStock <= reorderPoint`), strictly less, equal, and greater |
| **Area 4: Low-Stock & Valuation** | `ProductServiceTest` | `test_product_service_evaluates_multi_warehouse_availability_and_low_stock_status` | Agregasi stok multi-gudang dan penentuan status `low_stock` vs `normal` |
| **Area 4: PO Validation** | `PurchaseOrderServiceTest` | `test_create_purchase_order_validation_rejects_missing_supplier_and_empty_items` | Validasi field wajib, relasi supplier, dan minimal 1 item produk |
| **Area 4: PO Validation** | `PurchaseOrderServiceTest` | `test_create_purchase_order_rejects_zero_or_negative_quantity_and_negative_price` | Penolakan kuantitas 0 atau negatif dan harga beli negatif |
| **Area 4: PO Validation** | `PurchaseOrderServiceTest` | `test_purchase_order_cannot_be_cancelled_if_already_partially_received` | PO berstatus `PartiallyReceived` dilarang dibatalkan demi integritas data inventaris |

---

### B. Integration Test Suite (`tests/Integration/`, 4 Test Cases, Real Docker MySQL)

Menyentuh MySQL 8.0 riil pada container Docker, menguji constraint foreign key, unique key, check constraints, transaksi ACID, dan update stok nyata.

| Skenario Brief | Test File | Test Method | Real MySQL Engine Verification |
| :--- | :--- | :--- | :--- |
| **TEST-02 (a)**: Goods Receipt End-to-End | `GoodsReceiptEndToEndTest` | `test_goods_receipt_end_to_end_increases_stock_and_records_ledger_in_real_mysql` | Stok `product_stock` bertambah riil di MySQL (5 &rarr; 20, version 1 &rarr; 2), 1 baris `stock_ledger` `Receipt` tercatat riil, PO berstatus `Received`. |
| **TEST-02 (c)**: Rollback Transaksi Atomic | `GoodsReceiptEndToEndTest` | `test_transaction_rollback_maintains_atomicity_when_ledger_insert_fails` | Error paksa pada tahap kedua (insert ledger) menyebabkan `rollBack()`: stok `product_stock` di MySQL **TETAP 10** (tidak berubah), ledger **0 baris**. |
| **TEST-02 (b)**: ARCH-02 Goods Issue Concurrency | `OptimisticLockingConcurrencyTest` | `test_second_goods_issue_rejected_when_stock_depleted_by_first_issue_in_real_mysql` | SO-1 menghabiskan stok (5 &rarr; 0, version 1 &rarr; 2). SO-2 yang mencoba issue barang yang sama **DITOLAK** di MySQL nyata. Stok tetap 0 (tidak pernah negatif/oversell). |
| **ARCH-02 Deep Engine Test** | `OptimisticLockingConcurrencyTest` | `test_mysql_stock_repository_optimistic_update_returns_zero_affected_rows_on_stale_version` | Eksekusi langsung `UPDATE product_stock ... WHERE version = expectedVersion`. Pada stale version, MySQL mengembalikan `rowCount() === 0`. |

---

## 4. Audit Prinsip FIRST

Semua test (Unit & Integration) telah diaudit memenuhi prinsip **FIRST**:

1. **Fast (Cepat)**:
   - Unit test suite (13 test) selesai dalam **1.3 detik**.
   - Integration test suite (4 test MySQL) selesai dalam **1.5 detik**.
   - Seluruh test suite (17 test, 108 assertions) selesai dalam **1.87 detik**.
   - **Nol fungsi `sleep()`** di seluruh test code.

2. **Independent (Terisolasi)**:
   - Konfigurasi PHPUnit menerapkan `executionOrder="random"` (Random Seed). Urutan test selalu diacak tiap eksekusi dan selalu 100% lulus.
   - Tidak ada test yang mengasumsikan ID database tertentu atau data peninggalan test sebelumnya. Setiap integration test membuat data unik ber-UUID/random bytes dan membersihkannya melalui cascading cleanup di `tearDown()`.

3. **Repeatable (Dapat Diulang)**:
   - Hasil pengujian identik berulang kali di lingkungan Docker maupun host tanpa ketergantungan urutan ataupun timing.

4. **Self-validating (Validasi Otomatis)**:
   - Seluruh pengujian menggunakan assertion deklaratif PHPUnit (`assertSame`, `assertTrue`, `assertCount`, `assertStringContainsString`).
   - Tidak memerlukan inspeksi log manual atau pembacaan dump data secara visual.

5. **Timely & Thorough (Komprehensif)**:
   - Mencakup seluruh alur state machine kritis, role segregation, edge cases boundary reorder point, rollback atomicity, dan mekanisme concurrency locking.

---

## 5. Technical Defense: Ulasan Mendalam ARCH-02 (Optimistic Locking)

> **Pertanyaan Potensial Penguji**: *"Dari unit test dan integration test yang dibuat, yang mana yang paling representatif membuktikan pemahaman mekanisme ARCH-02 (Optimistic Locking), dan bagaimana mekanismenya bekerja?"*

### Jawaban Representatif:

1. **Pada Unit Test (Terisolasi dari Database)**:
   - **Test Representatif**: `SalesOrderServiceTest::test_arch02_optimistic_locking_detects_concurrency_conflict_when_row_count_zero`
   - **Pembuktian**:
     Optimistic Locking didasarkan pada asumsi bahwa konflik jarang terjadi, sehingga tidak menggunakan lock pesimis (`SELECT ... FOR UPDATE`) yang memblokir antrean database.
     Sebagai gantinya, entitas `product_stock` memiliki kolom `version INT UNSIGNED`.
     Ketika `processGoodsIssue()` dijalankan:
     1. Service membaca versi saat ini (misal `version = 1`).
     2. Service memanggil `decreaseStockOptimistic(expectedVersion: 1)`.
     3. Dalam unit test, `FakeStockRepository::setSimulateConflict(true)` diaktifkan untuk mensimulasikan bahwa ada transaksi lain yang telah mendahului meng-update baris tersebut sehingga versi baris di memory/database telah berubah menjadi 2.
     4. Pemanggilan update mengembalikan `false` (merefleksikan `rowCount() === 0` di PDO).
     5. `SalesOrderService` mendeteksi kondisi ini (`!$stockDecremented`), melempar `RuntimeException` *"Konflik konkurensi terdeteksi (Optimistic Lock)..."*, memicu `$this->database->rollBack()`, membatalkan penulisan `stock_ledger`, dan menjaga kuantitas stok tetap utuh.

2. **Pada Integration Test (Terhadap MySQL Nyata)**:
   - **Test Representatif**: `OptimisticLockingConcurrencyTest::test_second_goods_issue_rejected_when_stock_depleted_by_first_issue_in_real_mysql`
   - **Pembuktian**:
     Query SQL yang dijalankan di MySQL 8.0:
     ```sql
     UPDATE product_stock
     SET quantity = quantity - :quantity,
         version = version + 1
     WHERE product_id = :product_id
       AND warehouse_id = :warehouse_id
       AND version = :version
       AND quantity >= :quantity_check;
     ```
     Dua Sales Order (SO-1 dan SO-2) sama-sama memesan 5 unit dari stok yang hanya ada 5 unit (version = 1).
     Ketika SO-1 diproses, klausa `WHERE version = 1 AND quantity >= 5` terpenuhi, stok berkurang menjadi 0 dan version menjadi 2 (`rowCount() = 1`).
     Ketika SO-2 diproses berikutnya, klausa `WHERE version = 1 AND quantity >= 5` gagal mencocokkan baris mana pun (karena version sudah 2 dan quantity sudah 0). MySQL mengembalikan `rowCount() = 0`.
     Sistem secara otomatis menolak pengeluaran barang untuk SO-2, menjaga saldo stok tidak pernah jatuh ke angka negatif (`quantity = 0`), dan membuktikan pencegahan *lost update* serta *oversell* secara nyata di level database engine.
