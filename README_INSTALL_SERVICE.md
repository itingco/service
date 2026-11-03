# Patch Laravel — Penawaran Service

Sumber: `ingco-it/sales-dashboard`, branch `main`, diperiksa 20 Oktober 2026.

## Cakupan

1. Halaman `GET /service-quotations` (riwayat), `/service-quotations/create` (input).
2. Pilih customer, ERP sumber, service type dari `dbo.USP_HRM_DocTypes_QueryAll` dengan filter `serviceType`.
3. Cari `IC_Items` untuk barang rusak, lalu tampilkan kode sparepart yang diawali **kode barang rusak + `-SP-`**.
4. Saat komponen dipilih, cek stok `USP_IC_Items_Calculate`, master `ItemSimilar`, dan harga RETAIL pada AS_INGCO/AS_SMI.
5. Simpan draft ke SQLite `sqlite_targets`. Harga, stok dan relasi Similar Items **diperiksa ulang pada server** saat disimpan; stok tidak di-reserve.
6. Preview/cetak penawaran HTML (Print / Save PDF), snapshot harga/qty.
7. Tombol Kirim ERP tersedia, tetapi **default dikunci** sampai tabel header/detail, mapping field, mandatory columns, stored procedure, serta penomoran ERP disepakati.

## Instalasi lokal

**Backup project dan database sqlite_targets dahulu.** Gunakan PHP 8.2+ dan dependency Laravel yang sudah terpasang. ZIP hanya berisi file baru dan satu file `app/Providers/RouteServiceProvider.php` yang berubah; tidak berisi `.env`, `vendor`, atau database.

1. Extract isi ZIP langsung ke **root Laravel project** Anda (folder yang berisi `artisan`), dengan overwrite bila diminta.
2. Jalankan patch idempotent berikut untuk menambahkan menu ke sidebar dan permission key, tanpa mengganti semua isi layout/config lokal:
   ```bash
   php PATCH_MENU_AND_PERMISSION.php
   ```
   Script membuat backup `config/dashboard_security.php.backup-before-service-quotation` dan `resources/views/layouts/app.blade.php.backup-before-service-quotation`.
3. Jalankan:
   ```bash
   php artisan optimize:clear
   php artisan migrate --force
   php artisan route:list --path=service-quotations
   php artisan view:cache
   ```
4. Buka `/service-quotations`. Role yang sebelumnya mempunyai menu `sparepart_search` otomatis diberi akses `service_quotations` lewat migration. Administrator juga bisa akses; pengaturan role bisa diubah dari menu Keamanan.

**Penting**: Migration baru memakai koneksi `sqlite_targets`. Verifikasi dulu path file `TARGET_DB_DATABASE` agar tidak memakai SQLite produksi secara tidak sengaja.

## Konfigurasi yang harus diverifikasi

- `SERVICE_DOC_TYPES_CONNECTION=sqlsrv_ingco` (default). Pastikan hasil prosedur punya field `DocTypeID` atau `ID`, `DocTypeCode` atau `DocType`, dan `Name` / `Description` / `DocTypeName`, serta nilai filter `TableName` jika prosedur tidak menerima argumen `@TableName`.
- Stok ERP dari USP existing membutuhkan permission EXEC dan akses harga IC_ItemPriceList. Harga RETAIL bukan selalu per UOM stok terkecil; konfirmasi UOM saat approval.
- Pencarian sparepart hanya untuk pola `[kode induk]-SP-...`, diambil dari ERP sumber barang induk. Similar hanya dipakai sesudah sparepart dipilih.

## Pengiriman ERP — belum siap dinyalakan

Belum ada lampiran skema tabel tujuan ERP dalam percakapan ini. Karena itu, **jangan set** `SERVICE_ERP_WRITE_ENABLED=true` ataupun `SERVICE_ERP_SCHEMA_CONFIRMED=true`. Contoh adapter mapping ada pada `config/service_quotations.php` + `ServiceQuotationErpGateway.php`, **tidak boleh digunakan ke ERP asli sebelum kontrak tabel, mandatory field, relasi header/detail, nomor dokumen, trigger dan prosedur ERP dikonfirmasi**.

Mohon lampirkan SQL struktur tabel header/detail, contoh satu dokumen service valid, aturan nomor akhir tiap serviceType, serta nama database tujuan. Setelah itu mapping adapter bisa dipastikan dan dites tanpa asumsi.

Penomoran **draft lokal**: `QSV-[SERVICE_TYPE]-[TAHUN]-[000001]`, counter terpisah per service type/tahun dalam `sqlite_targets`, atomic transaction. **Nomor ERP**: baru dialokasikan saat pengiriman, dengan query dokumen terakhir dan lock; format akhirnya wajib disesuaikan dengan nomor dokumen ERP yang asli.

## Catatan pengujian

- Pemeriksaan `php -l` dapat dilakukan tanpa SQL Server, tetapi pencarian stok, hasil USP, dan insert ERP harus diuji pada ERP development/staging.
- Harga dalam draft adalah snapshot, bukan penawaran dengan reservasi stok.
- Format cetak menyebut total **sparepart**; belum menghitung ongkos jasa maupun pajak karena belum ada skema atau parameter.
- Modul menggunakan permission `service_quotations`; jangan lupa jalankan patch menu + migration.
- Perubahan pada `RouteServiceProvider.php` mengikuti sumber `main`. Jika ada custom perubahan lokal pada provider, bandingkan sebelum menimpa.
