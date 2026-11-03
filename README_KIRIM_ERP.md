# PATCH Kirim ke ERP — IC_Services / IC_ServiceSpareParts

Berdasarkan workbook `service.xlsx` (contoh data, bukan metadata constraint SQL).

## Cara pasang

Ekstrak ZIP di root Laravel dan timpa file yang sama:

`C:\xampp82\htdocs\sales-report-web`

Lalu:

```powershell
php artisan optimize:clear
php tests/Smoke/service_erp_numbering_test.php
php artisan view:cache
```

**Tidak perlu migration, tidak perlu menjalankan INSTALL_SERVICE_APPHUB.php lagi.**

## Alur pengiriman

1. Draft dan harga RETAIL tetap di DB_AppHub.
2. Pada halaman detail draft, opsional isi `CustomerID ERP` yang valid dari `AR_Customers`. Jika kosong, pencarian memakai nama customer yang cocok persis dan unik di master ERP. **Tidak pernah otomatis mengambil CustomerID milik pelanggan lain.**
3. Tekan `Kirim ke ERP` untuk menguji INSERT pada ERP yang sesuai dengan `erp_source`.
4. Gateway mengecek apakah koneksi benar-benar AS_INGCO/AS_SMI, apakah SKU dan WarehouseID ada di ERP yang sama, Qty bulat/tersedia, dan apakah ada data sebelumnya dengan marker AppHub yang sama.
5. ServiceNumber memakai format dari nomor terakhir *per ServiceType di ERP tujuan*. Contoh yang diberikan: `INC/SC-JND/261020/0129` -> counter berikutnya 130, dengan tanggal pengiriman `yyMMdd`.
6. INSERT `IC_Services` dan `IC_ServiceSpareParts` dilakukan dalam satu transaksi SQL Server. Tidak ada perubahan schema ataupun UPDATE/DELETE record ERP lama.
7. Setelah commit berhasil, status draft menjadi `sent` di DB_AppHub. Jika pembaruan status AppHub gagal, klik ulang memulihkan status dari marker tanpa menggandakan dokumen ERP.

## Pengisian kolom

- `IC_Services`: ServiceNumber, ServiceCounter, ReceivedDate, CustomerID, CustomerName, ItemID, ServiceType, CurrencyID (dari dokumen terbaru untuk service type itu), Problems (keluhan jika ada), created/modified audit, metadata dasar `New`, dan marker AppHub di Remarks.
- `IC_ServiceSpareParts`: ServiceID, ServiceDetailID jika bukan identity, ItemID, UOMLevel=1, WarehouseID, Quantity, AverageCost **jika tersedia dari sumber COST ERP**, serta audit dasar. `Inv=0` (belum di-invoice).
- Kolom lain yang tidak memiliki input / nilainya kosong **tidak dimasukkan dalam INSERT**. SQL Server boleh mengisinya dengan NULL/DEFAULT. Jika kolom wajib NOT NULL tanpa DEFAULT belum termapping, keseluruhan transaksi dibatalkan dan pesan kolom muncul.

## Penting: kolom harga

Spreadsheet tabel `IC_ServiceSpareParts` tidak memiliki kolom harga jual, hanya `AverageCost` (= harga pokok). Karena itu `unit_price` dan `line_total` penawaran **tetap di DB_AppHub** dan tidak dicopy ke `AverageCost`. Integrasi bukan pembuatan invoice atau sales order.

Jika kolom `AverageCost` wajib, gateway mencari `IC_Items.AverageCost` **hanya jika field itu benar-benar ada**. Jika tidak ada sumber AverageCost yang valid, pengiriman diblokir dan Anda diminta mengkonfirmasi sumber biaya pokok ERP, daripada mengisi 0 atau mengubah harga RETAIL menjadi cost.

## Validasi sebelum produksi

- Lakukan pengiriman pertama di database ERP *testing/sandbox atau backup yang aman*; ini adalah **INSERT ERP sungguhan**.
- Jalankan `CHECK_ERP_SERVICE_SCHEMA_READONLY.sql` pada AS_INGCO dan AS_SMI untuk melihat identity/default/nullable, sumber AverageCost, dan pola nomor terakhir.
- Jika ERP memiliki TRIGGER, validasi ERP lain, syarat flag atau nomor khusus, kirim hasil error dan info schema. Contoh Excel **tidak cukup untuk menjamin semua constraint dan efek trigger**.
- Part dari database lain tidak dapat dimasukkan dalam service ERP target. Buat draft dengan item yang sumbernya sama untuk kirim ke satu ERP.
- Untuk menonaktifkan pengiriman: `SERVICE_ERP_SEND_ENABLED=false` di `.env`, kemudian `php artisan optimize:clear`.

## Rollback patch kode

Timpa kembali file dari backup project. Patch tidak memodifikasi struktur tabel AS_INGCO, AS_SMI, ataupun DB_AppHub.
