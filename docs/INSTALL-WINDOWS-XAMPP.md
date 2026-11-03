# Instalasi Windows 11 + XAMPP 8.2

## 1. Pastikan terminal memakai PHP 8.2+

```powershell
where.exe php
php -v
php --ini
```

Path pertama sebaiknya menunjuk ke `C:\xampp82\php\php.exe`.

## 2. Extension wajib

```powershell
php -m | findstr /I "sqlsrv pdo_sqlsrv pdo_sqlite sqlite3 mbstring openssl fileinfo curl zip xml gd"
```

Untuk SQLite, aktifkan pada `php.ini`:

```ini
extension=pdo_sqlite
extension=sqlite3
```

Restart Apache setelah mengubah `php.ini`.

## 3. Instal dependency dan SQLite target

```powershell
cd C:\xampp82\htdocs\sales-report-web
composer install
copy .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate --database=sqlite_targets --force
php artisan sales-kpi:import-legacy-targets
php artisan optimize:clear
```

Untuk update project yang sudah memiliki `.env`, jangan menyalin `.env.example`. Gunakan `UPDATE_AFTER_OVERWRITE.bat`.

## 4. Konfigurasi aplikasi

Tambahkan atau periksa:

```dotenv
APP_URL=http://localhost/sales-report-web/public
TARGET_DB_DATABASE=database/database.sqlite
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD=gunakan-password-yang-kuat
```

Ganti password contoh sebelum aplikasi digunakan.

## 5. URL

```text
Login               : /login
Dashboard KPI       : /sales-kpi
Pengaturan target   : /sales-kpi/targets
Dashboard penjualan : /sales-dashboard
```

Contoh melalui Apache:

```text
http://localhost/sales-report-web/public/login
```

## 6. Apache tanpa `php artisan serve`

```apache
<VirtualHost *:80>
    ServerName sales-report.local
    DocumentRoot "C:/xampp82/htdocs/sales-report-web/public"

    <Directory "C:/xampp82/htdocs/sales-report-web/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Tambahkan ke hosts Windows:

```text
127.0.0.1 sales-report.local
```

## 7. Verifikasi

```powershell
php artisan about
php artisan test
php tests/manual_sales_kpi_targets.php
php tests/manual_sales_kpi.php
php tests/manual_sales_kpi_export.php
```

`tests/manual_sales_kpi_targets.php` memeriksa koneksi SQLite, tabel target, rollback transaksi, konfigurasi login, dan route utama.

## 8. Perilaku target

- Satu target untuk satu `LiniPenjualan` gabungan.
- Target tidak dipisah `AS_INGCO` dan `AS_SMI`.
- Target tidak dipisah per bulan.
- Mengubah target juga mengubah hasil laporan periode lama ketika laporan tersebut dibuka kembali.
- Target kosong atau nol berarti **Target belum diatur**.
