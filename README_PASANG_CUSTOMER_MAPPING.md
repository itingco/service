# PATCH: CustomerID ERP mengikuti Master Customer per Service Type

## Tujuan
- `IC_Services.CustomerName`: **nama customer yang diketik dalam penawaran**, tetap verbatim.
- `IC_Services.CustomerID`: ID customer dari **Master Customer Service** per kombinasi **database ERP + Service Type**.
- Pilihan customer bersumber dari `AR_Customers` di database ERP yang dipilih (read-only).
- Mapping hanya disimpan pada tabel baru `DB_AppHub.dbo.service_type_customers`.
- Tabel `AS_INGCO` dan `AS_SMI` **tidak dimodifikasi saat instalasi atau mengisi master**.
- Saat tombol Kirim ERP, insert transaksi service akan tetap berlangsung mengikuti modul ERP yang sudah ada.

## Pemasangan (Windows / PowerShell)
1. Backup project dan DB_AppHub, ekstrak ZIP di root `C:\xampp82\htdocs\sales-report-web`.
2. Jalankan:

```powershell
php artisan optimize:clear
php INSTALL_SERVICE_CUSTOMERS_APPHUB.php
php INSTALL_SERVICE_CUSTOMER_MENU.php
php tests/Smoke/service_type_customer_mapping_test.php
php artisan route:list --path=service-quotations
php artisan view:cache
```

**Jangan** jalankan `php artisan migrate` tanpa `--database`. Installer memverifikasi database aktif harus `DB_AppHub`, lalu menjalankan **hanya** migration baru yang eksplisit.

## Penggunaan
1. Sidebar > **MASTER DATA** > **Master Customer Service** atau `/service-quotations/service-customers`.
2. Pilih AS_INGCO / AS_SMI.
3. Pilih Service Type dari USP_HRM_DocTypes_QueryAll (tanpa parameter).
4. Cari customer melalui nama, kode, atau CustomerID di AR_Customers, lalu klik hasil.
5. Tekan **Simpan Mapping CustomerID**. Ulangi untuk setiap Service Type yang digunakan.
6. Buka **penawaran baru** berstatus Draft dan klik **Kirim ke ERP**.
7. Sistem memeriksa CustomerID mapping benar-benar masih ada di AR_Customers tujuan. Jika belum ada mapping, pengiriman diblokir.

**Contoh:** Penawaran atas nama `BAPAK BAPAK`, Service Type `SC-ACH`, mapping dipilih `AR_Customers.CustomerID=123`. Maka `IC_Services.CustomerName='BAPAK BAPAK'`, `IC_Services.CustomerID=123` pada AS_INGCO. Nama customer pada AR_Customers tidak dipakai menggantikan nama penawaran.

## SQL verifikasi (SELECT saja)
```sql
USE DB_AppHub;
SELECT erp_source, doc_type_code, customer_id, customer_code, customer_name
FROM dbo.service_type_customers ORDER BY erp_source, doc_type_code;
```

Tidak memerlukan migrasi ulang tabel quotation. Tujuh penawaran lama yang salah nomor ERP tetap tidak diubah.

## Catatan testing
PHP lint dan uji kontrak statis dapat dijalankan tanpa database; uji kirim ERP sungguhan harus dilakukan pada lingkungan ERP testing karena aplikasi ini akan melakukan INSERT ke ERP ketika tombol Kirim ditekan.
