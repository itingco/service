# HOTFIX – Service Type USP tanpa parameter

Patch **tambahan** untuk project sales-dashboard yang sudah memasang HOTFIX Service Type per database sebelumnya. Hanya berisi file yang baru/berubah, bukan project Laravel lengkap.

## Perubahan

- AS_INGCO: `DB::connection('sqlsrv_ingco')->select('EXEC dbo.USP_HRM_DocTypes_QueryAll')`
- AS_SMI: `DB::connection('sqlsrv_smi')->select('EXEC dbo.USP_HRM_DocTypes_QueryAll')`
- Tidak ada `@TableName`, tidak ada binding/parameter untuk USP.
- Setelah EXEC, hasilnya difilter di PHP menggunakan field `TableName` (case-insensitive dan variasi penamaan kolom yang didukung) agar hanya `serviceType` ditampilkan.
- Type yang tidak memuat identitas tabel **tidak ditampilkan**, untuk mencegah tercampurnya dokumen tipe lainnya. Jika kosong, periksa nama field dalam `storage/logs/laravel.log` (peringatan `Service quotation: unmapped service type USP result`).
- Saat menyimpan, pilihan service type divalidasi ulang dari sumber ERP yang dipilih.
- **Tidak ada DDL/DML** ke AS_INGCO maupun AS_SMI. Penawaran tetap disimpan pada DB_AppHub sesuai patch sebelumnya.

## Install pada Windows / XAMPP

1. Backup file lokal yang akan ditimpa bila ada modifikasi khusus.
2. Ekstrak ZIP ini dan timpa sesuai struktur folder Laravel di `C:\xampp82\htdocs\sales-report-web`.
3. Jalankan dari root Laravel:

```powershell
php artisan optimize:clear
php tests/Smoke/service_doc_type_no_parameter_test.php
php tests/Smoke/service_doc_type_sources_test.php
php artisan view:cache
```

Tidak perlu migration, installer, perubahan .env, atau pembuatan tabel.

## Uji secara manual

1. Buka `/service-quotations/create`, pilih AS_INGCO, pastikan daftar Service Type hanya dari AS_INGCO.
2. Ganti ke AS_SMI, pastikan dropdown memuat Service Type dari AS_SMI.
3. Buat satu draft dan verifikasi penyimpanannya tetap di DB_AppHub.

## Jika dropdown tetap kosong

Jalankan di SSMS **tanpa parameter** pada **masing-masing** database:

```sql
USE AS_INGCO;
GO
EXEC dbo.USP_HRM_DocTypes_QueryAll;
GO

USE AS_SMI;
GO
EXEC dbo.USP_HRM_DocTypes_QueryAll;
GO
```

Bagikan **nama kolom** hasil USP dan contoh beberapa baris yang `TableName`-nya `serviceType` (hapus data rahasia bila ada). Nama kolom seperti `DocTypeCode` dan `DocTypeName` adalah kemungkinan alias, bukan kontrak yang sudah diverifikasi. Jangan pakai sintaks `SELECT ... FROM (EXEC ...)`, karena tidak valid dalam SQL Server.
