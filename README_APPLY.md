# PATCH SALES DASHBOARD - REMOVE SERVICE / SPAREPART

Base repo yang dianalisa:
- ingco-it/sales-dashboard
- branch: main
- commit: 7751c08a0365d66aecd4e2e42255cf1b310cb5fe
- commit date: 2026-11-02

## File yang berubah
1. app/Providers/RouteServiceProvider.php
2. config/dashboard_security.php

## Efek
- Menu Penawaran Service hilang dari Sales Dashboard.
- Menu Pencarian Sparepart hilang dari Sales Dashboard.
- Semua permission `service_quotations` dan `sparepart_search` tidak lagi ada di katalog permission Sales Dashboard.
- routes/service_quotations.php tidak lagi dimuat oleh Sales Dashboard.
- Route lama `/spareparts/search` masih ada di routes/web.php dari source lama, tetapi akan selalu ditolak oleh middleware permission karena permission key sudah tidak ada. Ini sengaja untuk membuat patch minimal dan aman tanpa menyentuh route sales lain.
- Source controller/service/view lama tidak dihapus secara fisik supaya rollback dan histori migration tetap aman.

## Cara pasang
Timpa 2 file patch ini ke root project sales-dashboard sesuai struktur folder, lalu jalankan:

php artisan optimize:clear
php artisan route:list

Pastikan route `service-quotations/*` sudah tidak muncul.

Untuk `/spareparts/search`, request akan menghasilkan 403 karena permission sudah dikeluarkan dari katalog Sales Dashboard. Jika ingin source dan route sparepart dihapus fisik juga, lakukan setelah project service terpisah sudah lolos test.
