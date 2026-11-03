# Hotfix: reset counter ERP tiap bulan, per Service Type

## Aturan final
- Data penawaran/draft tetap ada di **DB_AppHub**.
- Nomor ERP ditentukan **saat tombol Kirim ke ERP** ditekan di AS_INGCO atau AS_SMI.
- Format `PREFIX/SERVICE-TYPE/YYMMDD/NNNN`, tanggal WIB saat pengiriman.
- Counter diperiksa ulang dari **dbo.IC_Services** database ERP tujuan, hanya dokumen dengan **Service Type dan periode YYMM yang sama**.
- Untuk bulan baru tanpa nomor ERP, counter mulai **0001**. Semua tanggal di bulan yang sama berbagi urutan counter.
- Jenis service lain dan database ERP lain memiliki urutan sendiri.
- Contoh 22/10/2026: jika belum ada dokumen `INC/SC-ACH/2610...`, hasil `INC/SC-ACH/261022/0001`; jika counter terakhir Oktober adalah `0005`, hasil `INC/SC-ACH/261022/0006`.
- Nomor ERP dokumen lama **tidak dimodifikasi**, termasuk tujuh draft salah tandai sebelumnya.

## File yang berubah
1. `app/Services/ServiceQuotations/ServiceQuotationErpGateway.php`: SQL mencari counter hanya untuk YYMM berjalan, `CHARINDEX` marker tetap aman, transaksi dan lock ERP tetap sama.
2. `app/Services/ServiceQuotations/ServiceErpNumbering.php`: helper bulan dan penanganan bulan tanpa counter.
3. `config/service_quotations.php`: dokumentasi aturan bulan.
4. `tests/Smoke/service_erp_future_only_test.php`: uji Oktober/November dan query safe-marker.

## Instalasi
1. Backup keempat file terkait di Laravel lokal.
2. Ekstrak ZIP ini ke root folder project, timpa hanya file pada path yang sama.
3. Jalankan:

```powershell
php artisan optimize:clear
php tests/Smoke/service_erp_future_only_test.php
php artisan view:cache
```

4. Jalankan `CEK_COUNTER_PER_BULAN_READ_ONLY.sql` pada SQL Server (ganti database/Service Type sesuai pengiriman) untuk mengetahui nomor berikutnya pada waktu eksekusi.
5. Uji **satu draft BARU** di ERP testing. Verifikasi `IC_Services.ServiceNumber`, `ServiceCounter`, marker `Remarks`, `IC_ServiceSpareParts`, dan status DB_AppHub.

Tidak ada migration, tidak ada perintah SQL UPDATE, ALTER, atau DELETE pada ERP. Pemasangan file ini sendiri tidak menulis ke database.

**Batasan:** Pengujian yang tersedia adalah pemeriksaan PHP dan logika dengan data simulasi. Query dan pengiriman langsung ke ERP asli belum dijalankan dari sini. Jika user ERP lain tidak mengikuti penguncian tabel, tetap gunakan pengujian concurrency dan idealnya unique constraint nomor dokumen sesuai SOP ERP.
