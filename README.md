# Sales Report Dashboard — INGCO & SMI

Aplikasi laporan penjualan berbasis **Laravel 12 + Blade** untuk SQL Server `AS_INGCO` dan `AS_SMI`.

## Login dan keamanan perangkat

Semua halaman laporan, ekspor, DataTable, pengaturan target, dan administrasi keamanan dilindungi oleh:

- akun pribadi per pengguna;
- password yang disimpan sebagai hash Laravel;
- perangkat yang harus disetujui administrator;
- role dan pembatasan halaman;
- audit login dan aktivitas;
- rate limit percobaan login;
- session idle timeout.

```text
/login
```

Atur administrator bootstrap pada `.env`:

```dotenv
DASHBOARD_ADMIN_NAME="Dashboard Administrator"
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD="PasswordKuat!2026Admin"
DASHBOARD_DEVICE_COOKIE_SECURE=false
```

Gunakan `DASHBOARD_DEVICE_COOKIE_SECURE=true` pada production HTTPS, kemudian jalankan:

```bash
php artisan dashboard-security:install
```

Perangkat admin pertama otomatis disetujui. Perangkat berikutnya menunggu approval melalui `/security/devices`. Detail tersedia di `docs/DASHBOARD-DEVICE-SECURITY.md`.

## Halaman

```text
/sales-dashboard       Dashboard penjualan operasional
/sales-insights        Management & R&D insights
/sales-kpi             Dashboard KPI gabungan
/sales-kpi/targets     Pengaturan target KPI (admin)
/security/users        Pengguna dashboard (admin)
/security/devices      Approval perangkat (admin)
/security/audits       Audit login dan aktivitas (admin)
```

## Dashboard Penjualan

Dashboard Penjualan menampilkan ringkasan per `LiniPenjualan` tanpa grafik. Kolom **Contribution** dihitung dari omzet MTD satu lini dibagi total omzet MTD sesuai filter aktif.

Di bawah ringkasan tersedia DataTable data mentah satu baris per item invoice, lengkap dengan sumber database, customer, sales, item, grade, status discontinued/item reject, quantity, `LineTotal`, dan contribution lini. Export Excel menghasilkan dua sheet: `Ringkasan Sales` dan `Data Mentah`.

## Dashboard KPI selalu gabungan

Dashboard KPI selalu mengambil kedua sumber:

```text
AS_INGCO + AS_SMI
```

Tidak ada filter database pada halaman KPI. Nilai `LiniPenjualan` yang sama dari kedua database digabung menjadi satu baris:

```text
Omzet Gabungan = Omzet AS_INGCO + Omzet AS_SMI
```

Kolom `Database` tetap tersedia pada detail transaksi untuk penelusuran sumber data. Jika salah satu database gagal, laporan gabungan dihentikan agar tidak menampilkan hasil parsial.

Filter `LiniPenjualan` berlaku pada kartu, skor, grafik, tabel rekap, detail, serta ekspor. Filter Discontinued, Item Reject, Grade, dan pencarian hanya membatasi tabel detail.

## Target KPI

Target disimpan pada SQLite lokal:

```text
database/database.sqlite
```

Ada satu target gabungan untuk setiap `LiniPenjualan`; target tidak dipisah menurut database atau bulan. Target berlaku untuk seluruh periode laporan dan dapat diedit melalui:

```text
/sales-kpi/targets
```

Halaman pengaturan:

- mengambil daftar lini bulan berjalan dari kedua SQL Server;
- tetap menampilkan target lama tanpa transaksi bulan berjalan;
- tidak mengizinkan nama lini ditambah atau diubah manual;
- mengedit target langsung di tabel;
- menyimpan semua perubahan dalam satu transaksi;
- menganggap target kosong atau `0` sebagai **Target belum diatur**.

## Instalasi / pembaruan Windows

Backup `.env`, ekstrak project, kembalikan `.env`, lalu jalankan:

```bat
UPDATE_AFTER_OVERWRITE.bat
```

Proses tersebut menjalankan:

```powershell
composer install
php artisan migrate --database=sqlite_targets --force
php artisan sales-kpi:import-legacy-targets
php artisan dashboard-security:install
php artisan optimize:clear
php artisan test
```

Import target lama hanya berjalan ketika tabel target masih kosong. Target yang sudah diedit melalui aplikasi tidak ditimpa.

Untuk instalasi baru, jalankan:

```bat
INSTALL_WINDOWS.bat
```

## Konfigurasi `.env`

```dotenv
APP_URL=http://localhost/sales-report-web/public

TARGET_DB_DATABASE=database/database.sqlite
DASHBOARD_ADMIN_NAME="Dashboard Administrator"
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD="PasswordKuat!2026Admin"
DASHBOARD_SESSION_IDLE_MINUTES=30
DASHBOARD_DEVICE_COOKIE_SECURE=false
```

Konfigurasi SQL Server tetap menggunakan `DB_HOST_INGCO`, `DB_DATABASE_INGCO`, `DB_USERNAME_INGCO`, `DB_PASSWORD_INGCO`, dan pasangan variabel `SMI`.

## Ekspor

```text
/sales-kpi/export/excel
/sales-kpi/export/pdf
```

Excel utama KPI berisi enam sheet. Setiap DataTable detail juga memiliki tombol **Export Semua Hasil ke Excel** yang mengambil seluruh data sesuai filter dan pencarian detail, bukan hanya halaman yang terlihat.

Ringkasan KPI dilengkapi pie chart kontribusi omzet serta bar chart vertikal skor per lini. Grafik pada tab Target, Growth, Discontinued/Item Reject, dan Grade juga menggunakan bar vertikal. Detail item tetap memiliki kolom `Database`.

## Verifikasi

```powershell
php artisan test
php tests/manual_dashboard.php
php tests/manual_web_assets.php
php tests/manual_sales_kpi.php
php tests/manual_sales_kpi_assets.php
php tests/manual_sales_kpi_export.php
php tests/manual_sales_kpi_errors.php
php tests/manual_sales_kpi_targets.php
php tests/manual_dashboard_security.php
```

## Persyaratan

- PHP 8.2+
- Laravel 12 dependencies melalui Composer
- ODBC Driver 17/18
- extension `sqlsrv`, `pdo_sqlsrv`, `pdo_sqlite`, `sqlite3`, `gd`, `zip`, `mbstring`, `openssl`, `fileinfo`, dan XML

Dokumentasi Windows: `docs/INSTALL-WINDOWS-XAMPP.md`

Dokumentasi rumus: `docs/SALES-KPI-CALCULATION.md`

Dokumentasi keamanan: `docs/DASHBOARD-DEVICE-SECURITY.md`
