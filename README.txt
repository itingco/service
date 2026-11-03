PATCH CUSTOMER PHONE & ADDRESS - SERVICE QUOTATIONS

PENTING: repository GitHub dan kode lokal bisa berbeda (terutama patch diskon). Paket ini
memakai installer PHP dengan pemeriksaan anchor agar tidak menimpa fitur yang ada.
Tidak ada file PHP lama yang diganti dengan versi GitHub.

1. Backup project / database DB_AppHub.
2. Salin apply_customer_contact_patch.php ke ROOT Laravel (selevel artisan).
3. Salin database/migrations/2026_10_28_180300_add_customer_contact_to_service_quotations.php
   ke database/migrations/ pada project.
4. Di PowerShell (root project):
   php apply_customer_contact_patch.php
5. Jalankan HANYA migration ini (hindari migration SQLite targets lama):
   php artisan migrate --database=sqlsrv_apphub --path=database/migrations/2026_10_28_180300_add_customer_contact_to_service_quotations.php
6. php artisan optimize:clear
7. Refresh browser Ctrl+Shift+R.

Hasil:
- Nomor telepon dan alamat terisi di form create/edit; kolom optional untuk data lama.
- Tersimpan di DB_AppHub.service_quotations (customer_phone, customer_address).
- Ditampilkan di detail, penawaran A4, dan receipt jika template lokal dikenali.
- Saat kirim ERP: IC_Services.CustomerPhone dan IC_Services.CustomerAddress.
- Tidak mengubah AR_Customers/master customer, harga, diskon, atau sparepart.
- Dokumen ERP yang sudah terkirim tidak diubah / disinkron ulang.

Catatan: cek panjang & nullability kolom ERP CustomerAddress/CustomerPhone di SQL Server
sebelum menggunakan di produksi. Server akan menolak pengiriman jika kolom tidak tersedia.
