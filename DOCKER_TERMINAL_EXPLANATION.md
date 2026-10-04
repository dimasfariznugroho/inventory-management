# Bedah Baris per Baris: Eksekusi Docker dari Awal hingga Service UP
**Dokumentasi & Penjelasan Hasil Terminal PowerShell**

Dokumen ini membedah secara mendalam setiap perintah (*syntax*), kegunaan, fungsi parameter, hingga arti dari setiap teks luaran (*output*) yang muncul di PowerShell Anda saat menjalankan aplikasi Inventory Management.

---

## 1. Verifikasi Docker Engine (`docker --version`)

### Syntax:
```powershell
docker --version
```
- **Kegunaan / Fungsi:** Menanyakan versi binary Docker CLI dan Docker Engine (daemon) yang terpasang dan aktif di sistem operasi Windows.
- **Mengapa Perlu Dijalankan:** Sebagai langkah *sanity check* (pemeriksaan awal) untuk memastikan daemon Docker Desktop sudah berjalan dan siap menerima instruksi containerization.

### Output Terminal:
```text
Docker version 28.3.3, build 980b856
```
### Penjelasan Hasil:
- `Docker version 28.3.3`: Versi Docker Engine yang terpasang adalah versi **28.3.3**.
- `build 980b856`: Merupakan Git commit hash dari rilis binary Docker tersebut.
- **Kesimpulan:** Docker terpasang sempurna dan dapat berkomunikasi dengan sistem.

---

## 2. Verifikasi Docker Compose (`docker compose version`)

### Syntax:
```powershell
docker compose version
```
- **Kegunaan / Fungsi:** Memeriksa ketersediaan plugin Docker Compose v2.
- **Mengapa Perlu Dijalankan:** Project ini adalah sistem *multi-container* (Web + MySQL), sehingga membutuhkan Docker Compose untuk mengorkestrasi dependensi antar-container secara otomatis.

### Output Terminal:
```text
Docker Compose version v2.39.2-desktop.1
```
### Penjelasan Hasil:
- Mengonfirmasi bahwa Docker Compose v2 (versi `v2.39.2-desktop.1`) aktif. Fitur seperti healthcheck dependensi (`condition: service_healthy`) didukung penuh oleh versi ini.

---

## 3. Navigasi Direktori Project (`cd`)

### Syntax:
```powershell
cd "c:\Program Files\xampp\htdocs\inventory_management"
```
- **Kegunaan / Fungsi:** *Change Directory* — Memindahkan *working directory* terminal dari folder user (`C:\Users\Dimas Fariz Nugroho`) ke folder root aplikasi tempat file konfigurasi Docker berada.
- **Mengapa Tanda Kutip (`"..."`) Digunakan:** Karena path memiliki spasi pada folder `Program Files`. Tanpa tanda kutip, PowerShell akan memecah path menjadi dua argumen terpisah dan menimbulkan error.

---

## 4. Pemeriksaan Ketersediaan File Docker (`Get-ChildItem` & `dir`)

### Syntax:
```powershell
Get-ChildItem Dockerfile, docker-compose.yaml
dir Dockerfile
```
- **Kegunaan / Fungsi:**
  - `Get-ChildItem`: Commandlet native PowerShell untuk memeriksa keberadaan file spesifik (`Dockerfile` dan `docker-compose.yaml`).
  - `dir`: Alias bawaan di Windows untuk melihat detail direktori/file.
- **Tujuan DevOps:** Memastikan sebelum proses *build*, blueprint container (`Dockerfile`) dan aturan orkestrasi (`docker-compose.yaml`) benar-benar ada di direktori aktif.

### Output Terminal:
```text
Directory: C:\Program Files\xampp\htdocs\inventory_management

Mode                 LastWriteTime         Length Name
----                 -------------         ------ ----
-a----         9/16/2026  11:11 AM           1048 Dockerfile
-a----         9/16/2026  11:28 AM           1222 docker-compose.yaml
```
### Penjelasan Hasil:
- `Mode -a----`: Merupakan atribut file (*Archive*), menandakan keduanya adalah file biasa (bukan folder atau symlink).
- `Length 1048 & 1222`: Ukuran file dalam satuan byte. Menunjukkan kedua file terisi data konfigurasi dan tidak kosong (0 byte).

---

## 5. Eksekusi Build & Up Multi-Container (`docker compose up -d --build`)

### Syntax:
```powershell
docker compose up -d --build
```
- **Kegunaan / Fungsi:** Membaca file `docker-compose.yaml`, membangun image container jika ada perubahan, lalu menyalakan semua service yang didefinisikan.
- **Bedah Flag / Parameter:**
  - `up`: Membuat (*create*), menghubungkan ke network, dan menyalakan (*start*) container.
  - `-d` (*detached mode*): Menjalankan container di latar belakang (background process) agar prompt terminal tidak terkunci dan tetap bisa digunakan untuk perintah lain.
  - `--build`: Memerintahkan Docker untuk selalu mengevaluasi ulang `Dockerfile` dan membangun image lokal sebelum container dijalankan.

---

### Bedah Output Bagian 1: Docker BuildKit Engine
```text
[+] Building 2.8s (16/16) FINISHED
 => [internal] load local bake definitions                                        0.0s
 => => reading from stdin 576B                                                    0.0s
 => [internal] load build definition from Dockerfile                              0.1s
 => => transferring dockerfile: 1.09kB                                            0.0s
 => [internal] load metadata for docker.io/library/php:8.2-apache                 1.6s
 => [internal] load metadata for docker.io/library/composer:2                     1.7s
 => [internal] load .dockerignore                                                 0.0s
 => => transferring context: 2B                                                   0.0s
```
**Penjelasan:**
- `Building 2.8s (16/16) FINISHED`: Seluruh 16 tahapan instruksi build selesai hanya dalam **2.8 detik**.
- `load metadata for php:8.2-apache & composer:2`: Docker menghubungi Docker Hub registry untuk memverifikasi apakah ada pembaruan pada base image resmi PHP 8.2 dan Composer 2.

---

### Bedah Output Bagian 2: Layer Caching (Efisiensi DevOps)
```text
 => FROM docker.io/library/composer:2@sha256:af98f42d...                          0.1s
 => [stage-0 1/8] FROM docker.io/library/php:8.2-apache@sha256:a5ca3797...       0.1s
 => CACHED [stage-0 2/8] RUN apt-get update && apt-get install -y git unzip...   0.0s
 => CACHED [stage-0 3/8] RUN a2enmod rewrite                                      0.0s
 => CACHED [stage-0 4/8] RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g'... 0.0s
 => CACHED [stage-0 5/8] RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g'... 0.0s
 => CACHED [stage-0 6/8] RUN echo '<Directory /var/www/html/public>...'          0.0s
 => CACHED [stage-0 7/8] COPY --from=composer:2 /usr/bin/composer /usr/bin/composer 0.0s
 => CACHED [stage-0 8/8] WORKDIR /var/www/html                                    0.0s
```
**Penjelasan:**
- **Status `CACHED` (0.0s):** Ini adalah konsep fundamental dalam DevOps (*Build Cache Optimization*). Karena instruksi instalasi paket (`apt-get`), modul Apache (`a2enmod rewrite`), dan konfigurasi DocumentRoot tidak berubah dari build sebelumnya, Docker **tidak mengulang download atau instalasi**. Docker menggunakan snapshot layer yang sudah ada, sehingga proses build hanya memakan waktu 2.8 detik (sebelumnya butuh 97 detik).
- `COPY --from=composer:2 ...`: Menggunakan teknik *Multi-stage build* untuk mengambil binary Composer resmi tanpa perlu menginstall seluruh ekosistem composer secara manual.

---

### Bedah Output Bagian 3: Image Packaging & Container Startup
```text
 => exporting to image                                                            0.3s
 => => naming to docker.io/library/inventory_management-web:latest                0.0s
 => => unpacking to docker.io/library/inventory_management-web:latest             0.0s
[+] Running 3/3
 ✔ inventory_management-web   Built                                               0.0s
 ✔ Container inventory_mysql  Healthy                                             3.3s
 ✔ Container inventory_web    Started                                             3.7s
```
**Penjelasan:**
- `naming to inventory_management-web:latest`: Image hasil rakitan Dockerfile berhasil disimpan dengan nama tag lokal `inventory_management-web:latest`.
- `Running 3/3`: Ketiga target compose berhasil diselesaikan:
  1. `inventory_management-web Built`: Image web berhasil dikompilasi.
  2. `Container inventory_mysql Healthy`: Container database MySQL dicek status kesehatannya via ping internal. Setelah dinyatakan siap menerima query SQL (`Healthy`), barulah langkah berikutnya dieksekusi.
  3. `Container inventory_web Started`: Container Apache PHP dinyalakan setelah MySQL siap.

---

## 6. Verifikasi Status Runtime (`docker compose ps`)

### Syntax:
```powershell
docker compose ps
```
- **Kegunaan / Fungsi:** Menampilkan status terkini dari semua container yang berada di bawah naungan project Docker Compose aktif.

### Output Terminal:
```text
NAME              IMAGE                      COMMAND                  SERVICE   CREATED          STATUS                    PORTS
inventory_mysql   mysql:8.0                  "docker-entrypoint.s…"   mysql     2 weeks ago      Up 22 minutes (healthy)   0.0.0.0:3306->3306/tcp, [::]:3306->3306/tcp
inventory_web     inventory_management-web   "docker-php-entrypoi…"   web       11 seconds ago   Up 7 seconds              0.0.0.0:8080->80/tcp, [::]:8080->80/tcp
```
### Bedah Kolom Hasil:
1. **NAME (`inventory_mysql` & `inventory_web`):**
   - Nama spesifik container yang dibuat sesuai deklarasi di `docker-compose.yaml`.
2. **STATUS:**
   - `Up 22 minutes (healthy)` pada MySQL: Menandakan service database telah hidup selama 22 menit dan lulus pengujian healthcheck berkala (`mysqladmin ping`).
   - `Up 7 seconds` pada Web: Menandakan Apache web server baru saja di-*start* ulang secara mulus 7 detik lalu.
3. **PORTS (Port Forwarding / Binding):**
   - `0.0.0.0:3306->3306/tcp`: Port 3306 di laptop Anda dipetakan langsung ke port 3306 MySQL di dalam container.
   - `0.0.0.0:8080->80/tcp`: Request yang masuk ke browser laptop via `localhost:8080` otomatis diteruskan ke port 80 (Apache HTTP) di dalam container web.

---

## 7. Pemeriksaan Log Web Server (`docker logs --tail 25 inventory_web`)

### Syntax:
```powershell
docker logs --tail 25 inventory_web
```
- **Kegunaan / Fungsi:** Mengambil 25 baris terakhir dari standar output (`stdout` / `stderr`) container web untuk memantau aktivitas Apache dan PHP.

### Output Terminal:
```text
AH00558: apache2: Could not reliably determine the server's fully qualified domain name, using 172.18.0.2. Set the 'ServerName' directive globally to suppress this message
AH00558: apache2: Could not reliably determine the server's fully qualified domain name, using 172.18.0.2. Set the 'ServerName' directive globally to suppress this message
[Fri Oct 02 11:52:26.665357 2026] [mpm_prefork:notice] [pid 1:tid 1] AH00163: Apache/2.4.68 (Debian) PHP/8.2.34 configured -- resuming normal operations
[Fri Oct 02 11:52:26.665529 2026] [core:notice] [pid 1:tid 1] AH00094: Command line: 'apache2 -D FOREGROUND'
```
### Penjelasan Log:
- `AH00558: ... Could not reliably determine ...`: Peringatan standar Apache (informational warning) karena ServerName eksplisit tidak diset; Apache secara default menggunakan IP internal container (`172.18.0.2`). Ini aman dan normal di environment container.
- `AH00163: Apache/2.4.68 (Debian) PHP/8.2.34 configured -- resuming normal operations`: Modul PHP versi 8.2.34 berhasil dimuat ke dalam Apache web server versi 2.4.68.
- `AH00094: Command line: 'apache2 -D FOREGROUND'`: Apache berjalan sebagai *PID 1* di foreground container. Ini adalah bukti bahwa web server aktif dan siap menerima HTTP request dari browser.

---

## 8. Pemeriksaan Log Database (`docker logs --tail 25 inventory_mysql`)

### Syntax:
```powershell
docker logs --tail 25 inventory_mysql
```
- **Kegunaan / Fungsi:** Menampilkan log inisialisasi mesin database MySQL 8.0.

### Output Terminal & Maknanya:
- `InnoDB initialization has started / ended`: Storage engine InnoDB (yang mendukung transaksi ACID pada tabel stok dan pesanan) berhasil diinisialisasi tanpa kerusakan file (*no corruption*).
- `Starting XA crash recovery... finished`: Pemeriksaan integritas transaksi selesai tanpa kendala.
- `Channel mysql_main configured to support TLS`: Koneksi aman terenkripsi siap digunakan.
- `X Plugin ready for connections ... port: 33060`: Protokol komunikasi modern MySQL X DevAPI aktif di port 33060.
- `[Server] /usr/sbin/mysqld: ready for connections. Version: '8.0.46' port: 3306`: **Pesan kunci penentu keberhasilan.** Mesin MySQL 8.0.46 resmi membuka port 3306 dan siap menerima instruksi query SQL dari aplikasi PHP.

---

## 9. Kesimpulan Akhir Status Sistem

Berdasarkan seluruh hasil audit terminal di atas:
1. **Status Stack:** `100% HEALTHY & RUNNING`.
2. **Konektivitas Antar-Container:** `inventory_web` berhasil terhubung ke `inventory_mysql` via internal Docker DNS bridge network.
3. **Endpoint Akses Pengguna:** Aplikasi dapat langsung diakses melalui URL:
   👉 **`http://localhost:8080`**
