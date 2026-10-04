# Panduan Deployment & Operasional Docker (Zero to Running)
**Inventory Management System**

Dokumen ini adalah panduan lengkap step-by-step untuk menjalankan aplikasi Inventory Management menggunakan Docker, mulai dari pemeriksaan versi (environment readiness) hingga service aktif (UP) dan siap diakses.

---

## 1. Prerequisites & Version Check (Pemeriksaan Kesiapan Sistem)

Sebelum menjalankan container, pastikan Docker Daemon dan Docker Compose sudah terpasang dan aktif di sistem.

Buka Terminal (PowerShell / Command Prompt) dan jalankan:

```powershell
# 1. Cek versi Docker Engine
docker --version

# 2. Cek versi Docker Compose
docker compose version
```

> **Contoh Output yang Diharapkan:**
> ```text
> Docker version 27.x.x, build ...
> Docker Compose version v2.x.x
> ```

---

## 2. Pindah ke Direktori Project

Pastikan sesi terminal aktif berada tepat di direktori root project:

```powershell
cd "c:\Program Files\xampp\htdocs\inventory_management"
```

Verifikasi bahwa file `docker-compose.yaml` dan `Dockerfile` tersedia di direktori tersebut:

```powershell
# Untuk PowerShell:
Get-ChildItem Dockerfile, docker-compose.yaml

# Atau perintah umum:
dir Dockerfile
```

---

## 3. Memahami Arsitektur Multi-Container (DevOps Context)

Aplikasi ini menggunakan konsep **Multi-Container Architecture** yang diorkestrasi oleh Docker Compose:

1. **`inventory_web` (PHP 8.2 + Apache)**:
   - Dikonfigurasi dengan `mod_rewrite` dan ekstensi `pdo_mysql`.
   - Mengarahkan `DocumentRoot` ke folder `public/` untuk keamanan arsitektur MVC.
   - Menggunakan **Docker Volume Mount** (`.:/var/www/html`) sehingga setiap perubahan kode di komputer lokal langsung tersinkronisasi tanpa perlu build ulang image.
2. **`inventory_mysql` (MySQL 8.0)**:
   - Memiliki **Healthcheck** bawaan (`mysqladmin ping`).
   - Melakukan inisialisasi schema dan data awal secara otomatis dari file `database/schema-and-seed.sql`.
   - Web service dikonfigurasi dengan aturan `depends_on: { mysql: { condition: service_healthy } }` sehingga web server hanya akan menyala setelah database benar-benar siap menerima transaksi SQL.

---

## 4. Menjalankan Service (Build & Up)

Jalankan perintah berikut untuk mengompilasi image (jika belum ada) dan menjalankan seluruh container di background:

```powershell
docker compose up -d --build
```

**Penjelasan flag:**
- `up`: Membuat dan menjalankan container.
- `-d` (*detached*): Menjalankan container di background agar terminal tetap bisa digunakan.
- `--build`: Memaksa proses build image web dari `Dockerfile` lokal untuk memastikan dependensi terbaru terpasang.

---

## 5. Verifikasi Status Container (Health & Port Mapping)

Setelah perintah selesai, periksa apakah kedua container sudah berjalan dengan normal:

```powershell
docker compose ps
```
Atau:
```powershell
docker ps
```

### Indikator Keberhasilan:
| Service | Container Name | Status | Port Mapping | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| **web** | `inventory_web` | `Up` | `0.0.0.0:8080->80/tcp` | Web server aktif di port 8080 |
| **mysql** | `inventory_mysql` | `Up (healthy)` | `0.0.0.0:3306->3306/tcp` | Database siap melayani query |

---

## 6. Pemeriksaan Log Operasional (Debugging & Monitoring)

Untuk memastikan tidak ada kendala koneksi database atau error konfigurasi Apache:

```powershell
# Melihat log web server
docker logs --tail 25 inventory_web

# Melihat log database MySQL
docker logs --tail 25 inventory_mysql
```

Jika log menampilkan `AH00094: Command line: 'apache2 -D FOREGROUND'` dan tidak ada fatal error, artinya aplikasi berjalan sempurna.

---

## 7. Akses Aplikasi & Verifikasi Fungsional

Buka browser pilihan Anda dan akses endpoint berikut:

- **Halaman Utama / Login**:  
  `http://localhost:8080`
- **Healthcheck / API Ping**:  
  `http://localhost:8080/api/ping`

### Kredensial Default (dari `schema-and-seed.sql`):
- **Role Admin**:  
  Email: `admin@inventory.local` | Password: `password` *(atau sesuai konfigurasi seed data)*

---

## 8. Manajemen Operasional Tambahan

### Menghentikan Layanan (Stop):
```powershell
docker compose stop
```

### Menghidupkan Kembali (Restart tanpa Build ulang):
```powershell
docker compose up -d
```

### Mematikan dan Menghapus Container (Clean Teardown):
```powershell
docker compose down
```
> **Catatan Data Safety**: Volume database `mysql_data` tidak akan terhapus dengan perintah di atas. Data stok dan transaksi inventaris Anda tetap aman tersimpan di storage volume Docker.
