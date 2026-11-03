PATCH DISKON SPAREPART SERVICE - 27 Oktober 2026

Paket ini merupakan PATCH LANJUTAN dari patch penerimaan service/struk sebelumnya.
Paket ini tidak berisi file Laravel lengkap siap timpa: app/*.php dan Blade lama dipertahankan, lalu diperbarui otomatis oleh installer PHP.

FITUR
- Diskon per baris sparepart: Tanpa Diskon / Persen (%) / Nominal (Rp).
- Diskon nominal dikenakan pada harga x qty untuk baris itu.
- Harga RETAIL tetap berasal dari ERP (tidak bisa diedit).
- Jumlah akhir per baris dan subtotal dihitung otomatis (tidak bisa diedit).
- Validasi ulang oleh Laravel agar nilai total POST tidak bisa dimanipulasi.
- Penyimpanan tipe, nilai, dan nominal potongan pada DB_AppHub.
- Edit draft memuat diskon lama; preview dan cetak A4 menampilkan diskon.
- Format struk penerimaan 80mm tetap sama (tidak mencetak harga).

PASANG (WINDOWS POWERSHELL)
1. Backup folder project dan database DB_AppHub.
2. Selesaikan dulu patch sebelumnya, agar penyimpanan tanpa sparepart & struk berhasil.
3. Ekstrak ISI ZIP ke root Laravel, misal C:\xampp82\htdocs\sales-report-web
4. Buka PowerShell pada root project.
5. Jalankan:
   php apply_discount_patch.php
   php artisan migrate --database=sqlsrv_apphub
   php artisan optimize:clear
6. Refresh Chrome: Ctrl+Shift+R.

File yang diubah oleh installer:
  app/Http/Controllers/ServiceQuotationController.php
  resources/views/service-quotations/create.blade.php
  resources/views/service-quotations/show.blade.php
  resources/views/service-quotations/print.blade.php
File baru:
  database/migrations/2026_10_27_001500_add_service_line_discounts.php

Installer akan membuat backup otomatis dan memvalidasi potongan kode sebelum mengubah file.
Jika 'PATCH STOPPED', jangan lanjut migrasi; kirim pesan kesalahannya.

PENTING:
Harga diskon tersimpan di draft DB_AppHub. Integrasi ERP yang ada tetap mengirim data sparepart dan ongkos biaya dasar seperti sebelumnya; harga jual diskon TIDAK dikirim ke kolom harga ERP karena tabel detail ERP saat ini tidak memiliki kolom harga jual menurut source yang ditinjau. Validasi transaksi penjualan akhir secara terpisah.

BELUM diuji pada database SQL Server pengguna. Uji pada database cadangan/staging terlebih dahulu.
