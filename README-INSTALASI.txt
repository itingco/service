PATCH PENAWARAN SERVICE - 02 NOVEMBER 2026

Sumber: ingco-it/sales-dashboard branch main.
Ekstrak ZIP ke root project Laravel (overwrite file yang sama).

FITUR
1. Status persetujuan: pending / approved / cancel. Status draft/sent untuk ERP tetap terpisah.
2. Filter tanggal, customer/kode, database, status; export SEMUA baris hasil filter.
3. Laporan sparepart per rentang tanggal; menunjukkan item, qty, nilai, customer, nomor penawaran dan nomor ERP.
4. Export CSV UTF-8 yang dapat dibuka di Microsoft Excel (BUKAN format native .xlsx).

INSTALASI (WAJIB)
Backup dulu project dan database DB_AppHub.
php artisan migrate --database=sqlsrv_apphub --path=database/migrations/2026_11_02_000100_add_approval_status_to_service_quotations.php --force
php artisan optimize:clear
php artisan route:list --path=service-quotations

UJI
/service-quotations
/service-quotations/sparepart-report

PERHATIAN
- Status approval tidak menggantikan status pengiriman ERP draft/sent.
- Data sparepart adalah ITEM DI PENAWARAN, bukan pergerakan stok aktual/pemakaian fisik.
- Belum diintegrasikan ke mutasi persediaan ERP.
- Status Cancel pada penawaran terkirim ERP ditolak. Penawaran Cancel yang masih draft tidak dapat dikirim ke ERP.
- Jangan jalankan migration pada AS_INGCO/AS_SMI.
- Belum diuji menggunakan database server sesungguhnya. Lakukan pada local/staging terlebih dahulu.
