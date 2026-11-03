# SERVICE SPAREPART - OVERLAY PROJECT

Base repo yang dianalisa:
- ingco-it/sales-dashboard
- branch: main
- commit: 7751c08a0365d66aecd4e2e42255cf1b310cb5fe
- commit date: 2026-11-02

Overlay ini sengaja hanya berisi file yang berubah.

## Konsep
Buat copy baru dari project `sales-dashboard`, misalnya:

sales-dashboard/
service-sparepart/

Lalu TIMPA isi folder `service-sparepart/` dengan file dari overlay ini.

Project service tetap memakai logic yang sudah ada dari repo asal:
- app/Http/Controllers/ServiceQuotationController.php
- app/Http/Controllers/ServiceQuotationReportingController.php
- app/Http/Controllers/SparepartSearchController.php
- app/Services/ServiceQuotations/*
- app/Services/Spareparts/*
- resources/views/service-quotations/*
- resources/views/spareparts/*
- routes/service_quotations.php
- config/service_quotations.php
- config/sparepart_search.php
- config/product_strategy.php

File tersebut tidak disertakan dalam ZIP karena TIDAK BERUBAH.

## Yang berubah pada project Service
- Hanya route Service / Sparepart / Security yang dimuat.
- Route API dan seluruh route Sales Dashboard tidak dimuat.
- User Service menggunakan SQLite sendiri.
- Role default:
  - admin: semua menu Service + Security
  - service: Penawaran Service + Pencarian Sparepart
  - viewer: Pencarian Sparepart
- Form user Service tidak lagi memakai mapping Sales Person ERP.
- Login dan layout diberi branding Service Sparepart.

## Sangat penting tentang database user
Gunakan:

TARGET_DB_DATABASE=database/service-security.sqlite

Jangan gunakan database/database.sqlite milik Sales Dashboard.

## Cara install local
1. Copy project lama menjadi folder baru `service-sparepart`.
2. Timpa file dengan isi overlay ini.
3. Copy data koneksi DB dari `.env` Sales Dashboard ke `.env` project Service.
4. Pastikan minimal nilai ini berbeda:

APP_NAME="Service Sparepart INGCO & SMI"
APP_URL=http://localhost/service-sparepart/public
TARGET_DB_DATABASE=database/service-security.sqlite
SESSION_COOKIE=service_sparepart_session
DASHBOARD_DEVICE_COOKIE=service_sparepart_device_token

5. Clear cache:

php artisan optimize:clear

6. Buat user database Service terpisah:

php artisan dashboard-security:install --username=admin --password="PasswordKuatAnda" --name="Service Administrator"

7. Cek route:

php artisan route:list

Route utama yang harus ada:
- /login
- /spareparts/search
- /service-quotations
- /service-quotations/create
- /service-quotations/sparepart-report
- /service-quotations/service-centers
- /service-quotations/service-customers
- /security/users
- /security/roles
- /security/devices
- /security/audits

Route sales-dashboard, sales-kpi, customer-analysis, business-analytics, stock-aging tidak boleh muncul pada project Service.

## JANGAN jalankan generic migration di project Service
Jangan jalankan:

php artisan migrate

untuk bootstrap user Service.

Gunakan command:

php artisan dashboard-security:install

Command pada overlay ini sudah diubah agar hanya menjalankan 4 migration security SQLite. Hal ini mencegah migration project asal mencoba membuat ulang tabel Service di DB_AppHub.

## DB_AppHub
Data Penawaran Service tetap memakai DB_AppHub seperti project asal. Pemisahan yang dilakukan di sini adalah:
- aplikasi / route terpisah
- login/user terpisah
- SQLite security terpisah

Data transaksi penawaran tetap satu sumber di DB_AppHub agar histori dan integrasi ERP tidak pecah.
