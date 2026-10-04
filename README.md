# Inventory & Order Management System

Sistem manajemen inventaris dan pemesanan multi-gudang dibangun dengan **PHP 8.2+ Native**, arsitektur berlapis murni (Controller &rarr; Service &rarr; Repository &rarr; Entity), serta concurrency-safe transaction handling.

---

## 🛠️ Persyaratan Lingkungan (Technology Stack)

- **Backend:** PHP 8.2+ Native (Zero framework, Zero external ORM/DI)
- **Frontend:** Semantik HTML5, Custom Vanilla CSS, Vanilla JS (Fetch API)
- **Database:** MySQL 8.0 (Akses data eksklusif via PDO Prepared Statements)
- **Containerization:** Docker & Docker Compose

---

## 🏗️ Arsitektur Berlapis (Phase 0 Foundation)

Sistem menerapkan prinsip *Separation of Concerns* (SoC) dan *Dependency Inversion* dengan arah dependency satu arah:

```text
[ HTTP Request ]
       ↓
[ public/index.php ] (Front-Controller & Composition Root)
       ↓
[ Controller Layer ] (App\Controller\PingController)
       ↓ (Constructor Injection)
[ Service Layer ] (App\Service\PingService)
       ↓ (Constructor Injection via Interface)
[ Repository Interface ] (App\Repository\PingRepositoryInterface)
       ↓ (Implemented by)
[ MySQL Repository ] (App\Repository\MySQLPingRepository)
       ↓ (Receives Database PDO instance)
[ Database / MySQL 8 ]
       ↓ (Returns Domain Model)
[ Entity Layer ] (App\Entity\PingResult)
       ↓ (Passes data to View)
[ View / HTML Layout ] (views/ping/index.php)
```

---

## 🚀 Cara Menjalankan Aplikasi (Docker Compose)

### 1. Prasyarat
Pastikan Docker Desktop sudah terinstal dan berjalan pada komputer Anda.

### 2. Jalankan Container dari Kondisi Bersih
Buka terminal pada direktori proyek dan jalankan perintah:

```bash
docker compose up --build -d
```

Docker akan mengompilasi image PHP 8.2 Apache dengan ekstensi `pdo_mysql` dan menyalakan service `mysql` 8.0 beserta `web`.

### 3. Cek Status Container
Pastikan container berjalan dengan sehat (*healthy*):

```bash
docker compose ps
```

Hasil normal:
- `inventory_mysql`: Status `Up (healthy)` pada port `3306`
- `inventory_web`: Status `Up` pada port `8080`

### 4. Akses Aplikasi di Browser
Buka browser dan kunjungi:
- **Halaman Validasi Arsitektur (HTML):** [http://localhost:8080/](http://localhost:8080/) atau [http://localhost:8080/ping](http://localhost:8080/ping)
- **Live Healthcheck Endpoint (JSON):** [http://localhost:8080/api/ping](http://localhost:8080/api/ping)

Halaman akan menampilkan waktu aktual server MySQL (`SELECT NOW()`), versi engine MySQL (`VERSION()`), nama schema terhubung (`DATABASE()`), dan status `ALIVE (1)`.

### 5. Mematikan Service
Jika selesai, matikan container dengan perintah:

```bash
docker compose down
```

---

## 📁 Struktur Direktori

```text
├── app/
│   ├── Controller/          # Presentation layer (HTTP request handling)
│   ├── Entity/              # Domain entities
│   ├── Repository/          # Data access interfaces & PDO implementations
│   └── Service/             # Core business rules & transaction logic
├── config/                  # Native environment (.env) and DB configurations
├── database/                # SQL schema migrations & seeders (Phase 1+)
├── docs/                    # Architecture diagrams, ADRs, quality & test logs
├── public/                  # Public web root
│   ├── assets/              # Vanilla CSS & JS assets
│   ├── .htaccess            # Apache rewrite rules
│   └── index.php            # Front-controller & Composition Root
├── scripts/                 # Standalone CLI & cron scripts (Phase 6)
├── tests/                   # Unit (in-memory) & Integration (real DB) tests
├── views/                   # PHP semantic HTML templates
├── .env.example             # Template variabel lingkungan
├── composer.json            # PSR-4 autoloading definition
├── Dockerfile               # PHP 8.2 Apache container definition
└── docker-compose.yaml      # Multi-container orchestration (web + mysql)
```

---

## 🧪 Pengujian Otomatis & Analisis Statis (PHPUnit & PHPStan)

### 1. Menjalankan Seluruh Test Suite (Unit + Integration) dengan SATU Perintah
Jalankan seluruh test (Unit test terisolasi in-memory + Integration test menyentuh MySQL 8.0 Docker):

```bash
docker compose exec web vendor/bin/phpunit
```

Untuk menjalankan test suite secara terpisah:
```bash
# Hanya Unit Tests (100% In-Memory Fake Repositories, no DB):
docker compose exec web vendor/bin/phpunit tests/Unit

# Hanya Integration Tests (Real MySQL 8.0, ACID rollback, ARCH-02 concurrency):
docker compose exec web vendor/bin/phpunit tests/Integration
```

Laporan hasil pengujian dan ulasan teknis ARCH-02 tersedia di:
- 📄 **[Laporan Hasil Test Suite (PHPUnit)](docs/testing/test-suite-report.md)**

### 2. Menjalankan Analisis Statis (PHPStan Level 5+)
Jalankan verifikasi kualitas kode dan type safety terhadap seluruh direktori `app/`:

```bash
docker compose exec web vendor/bin/phpstan analyse --debug --memory-limit=512M
```

Laporan hasil analisis statis (0 critical error) tersedia di:
- 📄 **[Laporan Analisis Statis PHPStan](docs/quality/static-analysis-report.md)**

---

## 📚 Dokumentasi Teknis & Arsitektur Lengkap
Untuk ulasan mendalam mengenai ERD, normalisasi 3NF, audit query, transaksi ACID, indexing, dan keamanan sistem, silakan merujuk ke:
- 📄 **[Laporan Arsitektur & Database](docs/LAPORAN_ARSITEKTUR_DAN_DATABASE.md)**
- 📄 **[Technical Debt & Query Guidelines](docs/quality/tech-debt.md)**
- 📄 **[Laporan Hasil Pengujian PHPUnit](docs/testing/test-suite-report.md)**
- 📄 **[Laporan Kualitas Kode PHPStan](docs/quality/static-analysis-report.md)**

