# Update Penawaran Service: pindah storage draft ke DB_AppHub

Update ini **hanya file yang berubah** dibanding paket `sales_dashboard_service_quotation_sqlite_only_20261020.zip`. Tidak mencakup ulang file tampilan, RouteServiceProvider, master sparepart, maupun migration permission dashboard yang tidak berubah.

## 1. Ringkasan

| Fungsi | Koneksi / Database | Aksi |
|---|---|---|
| Header penawaran | `sqlsrv_apphub` -> `DB_AppHub` | Tabel baru `dbo.service_quotations` |
| Detail sparepart | `sqlsrv_apphub` -> `DB_AppHub` | Tabel baru `dbo.service_quotation_lines` |
| Counter nomor draft per service type + tahun | `sqlsrv_apphub` -> `DB_AppHub` | Tabel baru `dbo.service_quotation_counters` |
| Menu / role permission dashboard | `sqlite_targets` (tetap) | Migration permission yang sudah ada, tidak dipindahkan |
| Barang rusak, sparepart, stok & harga | `sqlsrv_ingco` / `sqlsrv_smi` | Baca saja, tidak memodifikasi |
| Similar items | `sqlsrv_apphub` -> `DB_AppHub` | Baca tabel master existing, tidak mengubah |
| Kirim ke ERP | Masih NONAKTIF | Tidak menulis ke ERP sampai mapping tabel tujuan disetujui |

**Dilarang menjalankan `php artisan migrate` tanpa koneksi** karena koneksi default Laravel project ini adalah `sqlsrv_ingco` (`AS_INGCO`). Installer di bawah selalu memilih koneksi tujuan eksplisit.

## 2. Install di Windows / XAMPP

1. Backup project dan DB_AppHub terlebih dahulu. Pastikan user SQL `DB_USERNAME_APPHUB` mempunyai permission `CREATE TABLE` untuk membuat tabel modul baru **hanya pada DB_AppHub** (beserta permission yang relevan untuk foreign key/index). Jangan menambahkan permission CREATE/ALTER pada AS_INGCO atau AS_SMI.
2. Timpa file dari ZIP ini ke root project Laravel yang mengandung `artisan`. **Jangan menimpa `.env` atau `config/database.php`** (tidak ada dalam paket).
3. Buka PowerShell di root project, lalu jalankan:

   ```powershell
   php artisan optimize:clear
   php INSTALL_SERVICE_APPHUB.php
   php artisan optimize:clear
   php artisan view:cache
   ```

   Installer mengunci target menggunakan pemeriksaan `SELECT DB_NAME()` dan driver `sqlsrv`, memverifikasi SQLite hanya untuk permission dashboard, lalu menjalankan tepat dua migration dengan koneksi berbeda:
   - schema penawaran: `--database=sqlsrv_apphub --path=database/migrations/2026_10_20_000900_create_service_quotations.php`
   - menu permission: `--database=sqlite_targets --path=database/migrations/2026_10_20_000910_grant_service_quotation_permission.php`

4. Uji halaman `/service-quotations`. Draft baru harus tersimpan di DB_AppHub, tidak di SQLite.

Jika ingin memverifikasi manual tanpa menyimpan:

```powershell
php artisan tinker
```

Dalam Tinker:

```php
DB::connection('sqlsrv_apphub')->selectOne('SELECT DB_NAME() AS DbName');
DB::connection('sqlsrv_apphub')->table('service_quotations')->count();
```

Bisa juga di SQL Server Management Studio:

```sql
USE DB_AppHub;
GO
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME IN (
  'service_quotations', 'service_quotation_lines', 'service_quotation_counters'
);
GO
```

## 3. Perhatian untuk instalasi sebelumnya

- Jika Anda **belum sempat** menjalankan migration SQLite pada patch sebelumnya, tidak perlu menjalankannya lagi. Jalankan hanya installer DB_AppHub.
- Jika migration SQLite sebelumnya **sudah berhasil** dan sudah ada draft penawaran, draft SQLite lama **tidak otomatis dipindahkan**. Jangan hapus tabel/data SQLite lama sebelum migrasi data dan verifikasi; hubungi saya untuk skrip pemindahan dengan menjaga nomor serta foreign key.
- Jika sebelumnya sudah menjalankan `PATCH_MENU_AND_PERMISSION.php`, tidak perlu menjalankannya kembali. Menu/permission tetap.
- `INSTALL_SERVICE_SQLITE_ONLY.php` lama kini diganti menjadi pesan penghentian, agar tidak sengaja membuat tabel baru di SQLite.

## 4. Batasan

- `Kirim ke ERP` tetap fail-closed/nonaktif; penulisan header/detail ERP dan nomor ERP sebenarnya belum bisa diaktifkan karena skema tujuan belum diterima.
- Tidak ada akses langsung ke database SQL Server milik pengguna dalam lingkungan pembuatan file, sehingga pengujian integrasi SQL Server harus dilakukan dari lokal.
- Penomoran **draft**, contoh `QSV-SC-JKT-2026-000001`, ditambahkan secara berurutan per service type/tahun di DB_AppHub menggunakan transaksi dan `sp_getapplock`.
- Jika `DB_AppHub` memiliki tabel `service_quotations` yang belum tercatat sebagai migration, installer akan berhenti (tidak menimpa tabel existing).
