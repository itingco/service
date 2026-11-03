# PATCH Penawaran Service — Printout & Nomor ERP Berdasar Counter ERP

Target: patch sebelumnya `PATCH_SERVICE_NUMBERING_MASTER_SEARCH_PAGINATION_20261021.zip` terpasang.

## Perubahan

1. Printout **tidak menampilkan** Service Type, Nomor ERP, dan Database di area data customer. Logo, alamat Service Center, nomor penawaran QSV, barang rusak, harga, dan ketentuan 14 hari tetap ada.
2. Saat **Kirim ke ERP**, koneksi yang dipilih (AS_INGCO atau AS_SMI) membaca **IC_Services** pada database tersebut, memfilter **ServiceType** yang sama, mengambil format/prefix/tanggal dari nomor terakhir menurut `ServiceID DESC`, lalu mengambil `MAX(ServiceCounter)` DAN suffix numerik terbesar dari semua nomor Service Type yang sama. Counter baru adalah nilai tertinggi + 1. Proses di dalam transaksi dilindungi application lock dan table lock.
3. Sesuai contoh terakhir Anda, **tanggal dalam nomor mengikuti template terakhir dari ERP**, bukan otomatis berubah menjadi tanggal pengiriman. Contoh apabila nomor terakhir adalah `INC/SC-ACH/230727/0005` dan counter tertinggi adalah `5`, nomor baru menjadi `INC/SC-ACH/230727/0006`. Jika counter tertinggi ternyata `9`, nomor menjadi `.../0010`. Ini contoh saja—angka aktual wajib dibaca langsung dari tabel ERP saat kirim.
4. Supaya fleksibel jika kebijakan berubah menjadi tahun/tanggal berjalan, mode dapat dipilih melalui `.env`:
   `SERVICE_ERP_NUMBER_DATE_MODE=last_erp` (default) atau `SERVICE_ERP_NUMBER_DATE_MODE=send_date` (tanggal pengiriman Asia/Jakarta, mis. `261022`).

> **Perhatian:** `230727` bermakna 27 Juli 2023 menurut format YYMMDD. Patch ini sengaja mengikuti contoh terbaru Anda. Jika maksud sebenarnya adalah tanggal hari berjalan pada 22 Oktober 2026, gunakan mode `send_date` agar menjadi `261022`. Pada kedua mode, counter tetap dibaca dari ERP.

## Pemasangan Windows

1. Backup empat file PHP/Blade lama yang ada pada paket ini.
2. Ekstrak ZIP ke root project, misalnya `C:\xampp82\htdocs\sales-report-web`, dengan mempertahankan struktur folder dan overwrite 4 file existing. Jangan timpa `.env`.
3. Jalankan:

```powershell
php artisan optimize:clear
php tests/Smoke/service_counter_follow_erp_test.php
php artisan view:cache
```

Tidak perlu menjalankan migration maupun installer. Pemasangan **tidak mengubah** tabel ERP, DB_AppHub, atau data lama.

## Query verifikasi (hanya SELECT) di SSMS

```sql
USE AS_INGCO;
GO
SELECT TOP (20) ServiceID, ServiceType, ServiceNumber, ServiceCounter, CreateDate
FROM dbo.IC_Services
WHERE ServiceType = 'SC-ACH'
ORDER BY ServiceID DESC;

SELECT MAX(TRY_CONVERT(bigint, ServiceCounter)) AS LastCounterField,
       MAX(CASE WHEN CHARINDEX('/', REVERSE(ServiceNumber)) > 0
           THEN TRY_CONVERT(bigint,
                   RIGHT(ServiceNumber, CHARINDEX('/', REVERSE(ServiceNumber)) - 1))
           ELSE NULL END) AS LastCounterInNumber
FROM dbo.IC_Services
WHERE ServiceType = 'SC-ACH';
```

Counter berikutnya = `MAX(LastCounterField, LastCounterInNumber) + 1`.
Keduanya dicari pada database ERP yang dipilih, dan hanya Service Type yang sama.

## Batasan

- Nomor lama yang telah tercatat di ERP **tidak diubah** dan draft yang sudah terkirim tidak dikirim ulang.
- Tanpa koneksi ke SQL Server Anda, angka counter berikutnya belum dapat saya tentukan pasti. Tes paket bersifat smoke/static dan bukan pengiriman riil.
- Saat uji fungsi Kirim ERP, lakukan pada ERP testing terlebih dahulu. Fitur ini melakukan INSERT pada ERP saat tombol diklik.
