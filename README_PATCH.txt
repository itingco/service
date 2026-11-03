PATCH: Business Intelligence - Sidebar ganda

File yang berubah:
resources/views/business-intelligence/index.blade.php

Cara pasang:
1. Backup file yang lama.
2. Salin folder resources/ ke root project sales-dashboard (timpa file yang sama).
3. Jalankan: php artisan view:clear
4. Di browser tekan Ctrl + Shift + R lalu buka Business Intelligence > Buka analisis.

Penjelasan:
- Sembunyikan sidebar dan mobile topbar hanya pada halaman analisis di dalam iframe.
- Sidebar aplikasi utama tetap ada.
- Menangani load ulang iframe setelah navigasi/filter.
- Tidak mengubah route, controller, database, autentikasi, atau permission.
- Mengandalkan akses same-origin dari parent ke iframe.

Catatan: Patch dibuat berdasarkan source repository main terbaru yang diperiksa pada 31 Oktober 2026. Belum diuji dalam runtime aplikasi.
