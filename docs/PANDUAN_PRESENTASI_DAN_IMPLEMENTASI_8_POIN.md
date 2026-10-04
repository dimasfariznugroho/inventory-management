# 🎤 Panduan & Naskah Presentasi 5-7 Menit (Bahasa Awam & Praktis)
**Project:** Sistem Manajemen Inventaris & Pemesanan Multi-Gudang  
**Durasi Presentasi:** 5 – 7 Menit (Padat, Jelas, & Langsung ke Intinya)

---

## 🧭 Peta Cepat: File Mana Melakukan Apa? (Cheat Sheet)

Bila penguji bertanya: *"Di mana letak koneksi DB? Query SQL ada di mana? Login diatur di mana?"*, ini jawabannya:

| Bagian | Nama File | Fungsi Sederhananya |
| :--- | :--- | :--- |
| **Koneksi Database** | `app/Repository/Database.php` | **Pintu utama ke MySQL.** File ini yang menghubungkan PHP ke database menggunakan PDO. |
| **Password & Akun DB** | `config/database.php` & `.env` | Menyimpan username (`inventory_user`), password (`secret`), nama database (`inventory_db`), dan host. |
| **Query SQL (Tulis/Baca DB)** | Folder `app/Repository/MySQL*.php` | **Dapur query SQL.** Semua perintah `SELECT`, `INSERT`, `UPDATE` ada di sini (misal: `MySQLProductRepository.php` untuk barang, `MySQLStockRepository.php` untuk stok). |
| **Pintu Masuk Utama** | `public/index.php` | **Satpam utama web.** Setiap kali halaman dibuka, file ini yang pertama kali menyambut, menyambungkan database ke controller, dan memilih halaman yang tepat. |
| **Sistem Login & Hak Akses** | `app/Service/AuthSession.php` | **Pengatur kartu akses.** Mengingat siapa yang sedang login, jabatannya apa (Admin/Sales/Gudang), dan otomatis menendang keluar jika 30 menit tidak ada aktivitas. |
| **Pencegat Halaman (Penjaga Pintu)** | Folder `app/Controller/*.php` | Setiap Controller mengecek: *"User sudah login belum? Kalau Sales dilarang buka halaman Admin!"*. |
| **Aturan Bisnis & Transaksi** | Folder `app/Service/*.php` | **Otak sistem.** Menghitung stok, memastikan sales tidak menyetujui order miliknya sendiri, dan menjaga transaksi aman. |
| **Tampilan Halaman (HTML)** | Folder `views/**/*.php` | Menampilkan tabel, tombol, dan warna ke layar monitor pengguna. |

---

## 🔗 Peta Hubungan (Relasi) Antar-Tabel Database (Bahasa Simpel)

Database kita punya 12 tabel, intinya terhubung seperti rantai di dunia nyata:

1. **Akun Pengguna (`users`)**:
   - Terhubung ke **Pemesanan Pembelian (`purchase_orders`)**: Mencatat siapa admin yang beli barang ke supplier.
   - Terhubung ke **Pemesanan Penjualan (`sales_orders`)**: Mencatat staf Sales mana yang input pesanan dan Admin mana yang menyetujui.
   - Terhubung ke **Buku Mutasi (`stock_ledger`)**: Mencatat petugas gudang mana yang memindahkan barang.

2. **Gudang (`warehouses`) & Produk (`products`)**:
   - Keduanya bertemu di tabel **Stok Produk (`product_stock`)**: Menjawab pertanyaan *"Di Gudang Jakarta, Laptop Pro ada berapa biji?"*.
   - Tabel ini punya pelindung khusus: `quantity >= 0` (stok tidak boleh minus) dan kolom `version` (penanda versi biar tidak rebutan stok).

3. **Pesanan & Isinya (Header & Detail Item)**:
   - Satu surat pesanan (`purchase_orders` / `sales_orders`) terhubung ke tabel item (`purchase_order_items` / `sales_order_items`) karena dalam 1 pesanan bisa beli/jual lebih dari 1 macam barang.

4. **Buku Catatan Sejarah (`stock_ledger`)**:
   - Setiap ada barang masuk atau keluar, tabel ini mencatat detik kejadian, jenis pergerakan (+ atau -), jumlah barang, dan nomor surat jalan referensinya.

---

## 🔐 Bagaimana Cara Login & Hak Akses Bekerja?

1. **User Memasukkan Email & Password**:
   - Dicek oleh `app/Service/AuthService.php`. Password dicocokkan menggunakan enkripsi aman `password_verify` (bukan teks polos).
2. **Kartu Akses Disimpan**:
   - Jika cocok, `app/Service/AuthSession.php` membuat sesi dan menyimpan ID serta Role user (Admin / Sales / WarehouseStaff).
3. **Pengecekan di Setiap Halaman**:
   - Di file Controller (misal `UserController.php`), ditaruh perintah:
     `AuthSession::requireAuth();` &rarr; Kalau belum login, lempar ke `/login`.
     `AuthSession::requireRole(['Admin']);` &rarr; Kalau yang buka staf Sales, langsung tolak dengan pesan **403 - Akses Ditolak**.
4. **Pencegahan Korupsi / Kongkalikong (Segregation of Duties)**:
   - Di file `SalesOrderService.php`, ada aturan tegas: Sales boleh membuat order, tapi **hanya Admin yang boleh menyetujui**. Sales dilarang menyetujui order apa pun, termasuk orderannya sendiri.

---

## ⏱️ Naskah Presentasi 5-7 Menit (Siap Dibaca)

*Gunakan panduan waktu ini agar presentasi pas 5–7 menit dan tidak terpotong:*

### 🎙️ Menit 0:00 - 1:00 &bull; Pembukaan & Fitur (Poin 1)
> *"Selamat pagi/siang Bapak/Ibu penguji. Hari ini saya mempresentasikan **Sistem Manajemen Inventaris dan Pemesanan Multi-Gudang** yang dibangun menggunakan **PHP 8.2 Native murni tanpa framework** dan **MySQL 8.0**.*
>
> *Progress project saat ini sudah **100% selesai**. Fitur utamanya mencakup:*
> 1. *Manajemen stok terpisah di multi-gudang (Jakarta, Surabaya, Medan).*
> 2. *Siklus lengkap pembelian barang dari supplier (Purchase Order & Goods Receipt).*
> 3. *Siklus lengkap penjualan ke pelanggan (Sales Order & Goods Issue).*
> 4. *Pemisahan hak akses ketat antara Admin, Sales, dan Staf Gudang.*
> 5. *Proteksi rebutan stok bersamaan (Optimistic Locking) agar stok tidak pernah minus.*
> 6. *Dashboard laporan otomatis dan API data ketersediaan barang."*

---

### 🎙️ Menit 1:00 - 2:00 &bull; Struktur File & Alur PHP (Poin 2 & 3)
> *"Untuk struktur kodingnya, saya tidak mencampur aduk file HTML dan query database menjadi satu. Saya membaginya menjadi 4 lapisan rapi:*
> - *Pertama, **`public/index.php`** sebagai gerbang utama. Semua permintaan halaman masuk lewat sini.*
> - *Kedua, **Controller** di folder `app/Controller/` yang bertugas menangkap klik user dan mengecek apakah user sudah login.*
> - *Ketiga, **Service** di folder `app/Service/` sebagai otak bisnis yang menghitung jumlah stok dan memvalidasi aturan.*
> - *Keempat, **Repository** di folder `app/Repository/` yang khusus menulis perintah SQL.*
>
> *Dengan cara ini, aplikasi sangat mudah dirawat dan dites secara terpisah."*

---

### 🎙️ Menit 2:00 - 3:00 &bull; Koneksi Database & Hubungan Tabel (Poin 4)
> *"Mengenai integrasi database:*
> - *Koneksi database dibuat terpusat di satu file, yaitu **`app/Repository/Database.php`** menggunakan PDO. File ini bersifat 'Lazy', artinya koneksi ke MySQL baru dibuka hanya saat dibutuhkan query, sehingga menghemat memori server.*
> - *Perintah SQL disebar rapi per tabel di folder `app/Repository/`, contohnya `MySQLProductRepository.php` untuk produk dan `MySQLStockRepository.php` untuk stok fisik.*
> - *Relasi database kita terdiri dari 12 tabel yang saling mengunci dengan **Foreign Key**. Contohnya: produk tidak bisa dihapus sembarangan jika masih ada stok di gudang atau masih terikat pesanan pelanggan. Selain itu, ada tabel `stock_ledger` yang mencatat riwayat keluar-masuk barang secara permanen."*

---

### 🎙️ Menit 3:00 - 4:00 &bull; Keamanan, Hak Akses, & Error (Poin 5)
> *"Di sisi keamanan:*
> 1. *Untuk **Hak Akses (Auth)**, dikelola oleh file **`app/Service/AuthSession.php`**. Sesi login aman dengan proteksi timeout 30 menit. Di setiap controller, ada pengecekan izin role.*
> 2. *Ada aturan **Segregation of Duties**: staf Sales dilarang keras menyetujui Sales Order miliknya sendiri; hanya Admin yang boleh melakukan approval.*
> 3. *Untuk **Keamanan Data**, 100% query SQL menggunakan **PDO Prepared Statements** berparameter sehingga kebal dari SQL Injection. Password dienkripsi dengan Bcrypt.*
> 4. *Untuk **Penanganan Error**, jika terjadi kesalahan di server, web tidak pernah menampilkan pesan error teknis atau baris kodingan database yang bocor, melainkan menampilkan halaman ramah pengguna (403, 404, atau 500)."*

---

### 🎙️ Menit 4:00 - 5:30 &bull; Pengujian (Test) & Demo Singkat (Poin 6 & 7)
> *"Untuk membuktikan kualitas kodingan, kami telah membuat sistem pengujian otomatis menggunakan **PHPUnit 10**:*
> - *Terdapat **13 Unit Test** yang berjalan 100% di memori tanpa butuh database sama sekali dalam waktu **1.3 detik**.*
> - *Terdapat **4 Integration Test** yang menyentuh langsung database MySQL di Docker.*
> - *Kualitas kodingan juga diaudit dengan **PHPStan Level 5** dengan hasil **0 error** di seluruh 60 file.*
>
> *(Sambil memperlihatkan layar browser):*
> *Di layar ini kita bisa lihat:*
> 1. *Admin membuat Purchase Order ke supplier, lalu Staf Gudang menerima barang (Goods Receipt), seketika stok bertambah dan tercatat di buku besar mutasi.*
> 2. *Sales membuat pesanan (Sales Order), statusnya PendingApproval. Sales tidak bisa meng-approve.*
> 3. *Admin menyetujui pesanan tersebut.*
> 4. *Gudang mengeluarkan barang. Jika ada 2 pesanan yang berebut 1 sisa barang terakhir di detik yang sama, sistem secara otomatis menolak pesanan kedua lewat mekanisme Optimistic Locking, sehingga stok tetap 0 dan tidak pernah minus."*

---

### 🎙️ Menit 5:30 - 6:30 &bull; Kendala, Solusi, & Penutup (Poin 8)
> *"Sebagai penutup, ada 2 kendala nyata yang berhasil kami selesaikan:*
> 1. *Kendala **Rebutan Stok (Race Condition)** saat banyak order bersamaan. Kami selesaikan dengan teknik **Optimistic Locking (ARCH-02)** pada query UPDATE MySQL menggunakan kolom versi.*
> 2. *Kendala **Ketergantungan Database saat Testing**. Kami selesaikan dengan teknik Repository Fake di folder `tests/Fakes/` sehingga testing logika bisnis bisa dites dalam hitungan detik tanpa database asli.*
>
> *Sebagai refleksi untuk peningkatan berikutnya, file inisialisasi `public/index.php` ke depannya dapat disederhanakan lagi menggunakan Service Container otomatis jika jumlah modul bertambah banyak.*
>
> *Sekian presentasi dari saya, terima kasih dan saya siap menerima masukan atau pertanyaan dari Bapak/Ibu penguji."*

---

## 💡 Jawaban Cepat Jika Penguji Bertanya Teknis:

1. **"Apa itu ARCH-02 / Optimistic Locking yang kamu sebutkan tadi?"**
   > *"Sederhananya begini Pak/Bu: sistem tidak mengunci database lama-lama. Tapi tabel stok diberi nomor versi (misal versi 1). Saat mau mengurangi stok, SQL akan mengecek: `UPDATE stok SET jumlah = jumlah - 1, versi = versi + 1 WHERE versi = 1`. Kalau ada orang lain yang sudah mendahului, versinya sudah berubah jadi 2, maka perintah ini mengembalikan hasil 0 baris yang berubah. Dari situ sistem tahu ada bentrokan, transaksi langsung dibatalkan, dan barang tidak akan terjual dobel (mencegah stok minus)."*

2. **"Kenapa pakai Repository Pattern kalau tanpa framework?"**
   > *"Biar kodingan kita gampang dites, Pak/Bu. Saat aplikasi jalan beneran, Service memakai `MySQLStockRepository`. Tapi saat dijalankan di Unit Test, Service kita pasangi `FakeStockRepository` yang cuma pakai array memori, sehingga kita bisa menguji logika bisnis dalam 1 detik tanpa menyalakan MySQL."*

3. **"Di mana letak pengecekan agar Sales tidak menyetujui ordernya sendiri?"**
   > *"Pengecekannya ada 2 lapis, Pak/Bu. Lapis pertama di halaman web (tombol persetujuan tidak dimunculkan untuk Sales). Lapis kedua yang paling penting ada di file `app/Service/SalesOrderService.php` pada fungsi `approveSalesOrder`, di mana sistem mengecek role user. Kalau role-nya bukan Admin, sistem langsung menolak proses tersebut."*
