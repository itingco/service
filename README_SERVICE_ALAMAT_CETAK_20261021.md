# PATCH Penawaran Service — Master Alamat & Catatan Cetak (21 Oktober 2026)

**Hanya file baru/berubah dari PATCH_SERVICE_EDIT_NUMBERING_PRINT_LOGO_20261021.zip**. Jangan install langsung ke versi project sebelum patch tersebut karena file lain (ERP, route, print, dll.) diperlukan. Backup file Laravel yang akan tertimpa.

## Perubahan

1. Halaman baru **Master Alamat Service Center** di `/service-quotations/service-centers` (menu pada halaman riwayat, formulir penawaran, dan detail). Pilih **AS_INGCO** atau **AS_SMI**, pilih Service Type dari `EXEC USP_HRM_DocTypes_QueryAll` dari ERP bersangkutan, isi **satu textarea multiline**, simpan. Master tidak mengubah tabel ERP.
2. Satu tabel baru **hanya di DB_AppHub**: `dbo.service_type_addresses`. Setiap kombinasi `erp_source` dan `doc_type_code` mempunyai satu alamat, agar kode Service Type yang sama pada dua database tidak bertabrakan.
3. Pada preview/cetak A4, **logo INGCO atau WADFOW di kiri**, **nama Service Center dan alamat per Service Type di kanan**. Baris baru alamat dipertahankan pada printout. Menggunakan logo yang **sudah ada dari patch sebelumnya** `public/images/service-quotation/*`, tidak perlu upload lagi.
4. Footer printout tetap memuat keterangan **jasa perbaikan gratis** dan ditambah: **"Apabila pelanggan tidak memberikan konfirmasi atas penawaran perbaikan ini dalam waktu 14 (empat belas) hari kerja sejak tanggal penawaran, maka proses perbaikan dianggap batal."**
5. Tidak mengubah penomoran, mapping ERP, stok, atau status penawaran. Ketentuan 14 hari ini **hanya teks dalam printout**, tidak otomatis mengubah status dokumen.

## Instalasi Windows

Dari Laravel root, misalnya `C:\xampp82\htdocs\sales-report-web`:

1. Ekstrak ZIP ini ke root Laravel, timpa hanya file yang disediakan (jangan overwrite `.env` atau database).
2. Jalankan di PowerShell:

```powershell
php artisan optimize:clear
php INSTALL_SERVICE_ADDRESS_APPHUB.php
php tests/Smoke/service_type_address_print_test.php
php artisan view:cache
```

**Penting:** Installer membuat **satu tabel baru hanya pada `DB_AppHub`**. Sebelum migration, installer mengecek `SELECT DB_NAME()` dan membatalkan proses jika koneksi tidak mengarah tepat ke `DB_AppHub`. Jangan jalankan `php artisan migrate` tanpa menentukan database (default project dapat menuju `AS_INGCO`).

3. Buka `/service-quotations`, klik **Master Alamat Service Center**. Pilih database dan Service Type, isi alamat multiline lalu **Simpan Alamat**. Ulangi untuk Service Type lain.
4. Buka detail penawaran (Draft atau Terkirim) → **Preview & Cetak**. Pastikan logo dan alamat benar, serta catatan 14 hari kerja muncul setelah keterangan biaya jasa gratis.

## Verifikasi tabel (opsional di SSMS, read only)

```sql
USE DB_AppHub;
GO
SELECT erp_source, doc_type_code, address, updated_at
FROM dbo.service_type_addresses
ORDER BY erp_source, doc_type_code;
```

## Catatan operasional

- Alamat tidak diambil dari master `AS_INGCO`/`AS_SMI`. USP ERP hanya dipakai untuk membaca daftar Service Type; alamat Anda input manual dan disimpan dalam `DB_AppHub`.
- Jika alamat suatu Service Type belum diisi, printout menampilkan **"Alamat Service Center belum diatur"** agar tidak menggunakan alamat fiktif. Isi master terlebih dahulu.
- Printout mengambil **alamat terbaru dari master** pada saat dicetak, termasuk untuk dokumen lama. Jika kelak perlu menyimpan *snapshot alamat per penawaran* yang tidak berubah setelah dicetak, hal itu membutuhkan perubahan terpisah.
- Hanya pengguna yang punya permission modul `service_quotations` dapat mengakses master. Pengaturan akses master lebih rinci bisa ditambahkan jika diperlukan.
- Tabel ERP `AS_INGCO` dan `AS_SMI` **tidak diubah** oleh patch maupun installer ini.
- Pengujian sintaks dan smoke test dapat dijalankan tanpa ERP, tetapi pengujian koneksi dan tampilan sebenarnya tetap dilakukan di lingkungan lokal Anda.
