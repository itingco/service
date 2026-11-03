# HOTFIX Penawaran Service — 21 Oktober 2026

Paket ini berisi **HANYA file baru/berubah** dari paket Penawaran Service terakhir (termasuk patch Kirim ERP dari 21 Oktober 2026), bukan project Laravel lengkap.

## Perubahan

1. **Nomor ERP**: pola lama tetap `PREFIX/SERVICE_TYPE/YYMMDD/COUNTER` (misalnya `INC/SC-ACH/261021/0006`). YYMMDD dihitung berdasarkan `Asia/Jakarta` pada **saat Kirim ke ERP**, tidak memakai `GETDATE()` SQL Server. Nomor counter diambil dari nilai tertinggi `ServiceCounter` dan suffix nomor yang sudah tersimpan untuk Service Type bersangkutan; counter berlanjut saat tanggal berubah, bukan reset harian. Pola prefix dan jumlah digit counter mengikuti nomor terakhir Service Type tersebut. Pemeriksaan dan INSERT ada dalam transaksi/lock pada ERP terkait.
2. **Edit draft**: tombol Edit Draft pada detail dan riwayat muncul hanya untuk penawaran berstatus `draft`. Customer, keluhan, barang rusak, Service Type, sparepart, gudang, dan Qty dapat diperbarui. Setiap simpan melakukan validasi ulang barang, stok & harga ERP. Jika sumber/Service Type berubah, nomor **draft DB_AppHub** baru diberikan dari serinya; nomor lama tidak digunakan ulang. Setelah `sent`, edit ditolak juga pada backend.
3. **Race edit vs. Kirim ERP**: penawaran DB_AppHub dikunci selama kirim dan penandaan sukses, agar tidak bisa diubah bersamaan. Mekanisme recovery ERP marker tetap dipertahankan.
4. **Preview & cetak**: logo INGCO Indonesia atau WADFOW asli dari file lampiran sesuai sumber ERP, tampilkan nomor ERP jika sudah terkirim, Qty tanpa desimal, dan catatan resmi biaya jasa GRATIS di bagian bawah halaman print.
5. **Tidak ada perubahan skema database atau migrasi**. Draft di DB_AppHub. Pengiriman hanya melakukan INSERT service ERP setelah tombol Kirim ke ERP ditekan; AS_INGCO/AS_SMI tidak diubah pada instalasi.

## Cara instalasi (Windows XAMPP)

1. Backup file project yang akan tertimpa, khususnya `app/Http/Controllers/ServiceQuotationController.php`, `app/Services/ServiceQuotations/`, `resources/views/service-quotations/`, `routes/service_quotations.php`.
2. Ekstrak ZIP **langsung pada root project Laravel** (contoh `C:\xampp82\htdocs\sales-report-web`) sehingga path `app/`, `resources/`, `routes/`, dan `public/` sejajar folder existing.
3. Pada PowerShell:

```powershell
php artisan optimize:clear
php -d zend.assertions=1 -d assert.exception=1 tests/Smoke/service_quotation_revision_test.php
php artisan route:list --path=service-quotations
php artisan view:cache
```

4. Refresh browser `Ctrl + F5`.

## Alur uji

- Buka `service-quotations`. Klik **Detail** draft -> **Edit Draft**. Ganti Qty atau tambah sparepart -> Simpan. Periksa angka dan status tetap draft. Coba Qty melebihi stok: penyimpanan harus ditolak.
- Preview & Cetak dari AS_INGCO => logo INGCO. Dari AS_SMI => logo WADFOW. Periksa catatan bebas ongkos jasa di bagian bawah, dan nomor ERP setelah dikirim.
- **Kirim ERP hanya di environment testing yang aman**, karena tombol melakukan INSERT nyata. Periksa nomor dengan SELECT dari `IC_Services`, bandingkan suffix dengan counter maksimum dan tanggal WIB saat dikirim. Edit dokumen `sent` harus ditolak.

## Keterangan penting

- **Dokumen yang sudah telanjur dikirim** dengan nomor yang keliru (misalnya `INC/SC-ACH/230727/0005`) **tidak diubah otomatis**. Patch hanya mempengaruhi pengiriman baru. Koreksi historis ERP perlu prosedur terpisah dan persetujuan bagian ERP.
- Jika ERP lain menghasilkan nomor baru secara paralel melalui jalur di luar aplikasi, perlu validasi mekanisme penomoran bersama agar semua aplikasi menggunakan serial/lock yang sama. Patch mengunci transaksi yang dihasilkan dari modul Laravel ini, tetapi tidak mengganti mekanisme penomoran ERP lain.
- Pola tanggal `YYMMDD` mengikuti contoh ERP. Jika yang dimaksud `YYYYMMDD` (8 digit), perlu dikonfirmasi karena berbeda dari nomor yang sudah ada.
- Teks permintaan nomor 3 dari user ("untuk preview di bawah masukan juga") belum lengkap; kebutuhan tambahan yang belum disebut tidak diubah.
