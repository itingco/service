PATCH SERVICE - SERIAL NUMBER & STRUK TANDA TERIMA

1. Backup project dan database DB_AppHub.
2. Copy folder resources/ dan database/ ke root Laravel (merge folder).
3. Copy apply_serial_patch.php ke root Laravel, jalankan:
   php apply_serial_patch.php
4. Jalankan HANYA migration ini (bukan migrate umum):
   php artisan migrate --database=sqlsrv_apphub --path=database/migrations/2026_10_30_000001_add_service_receipt_fields.php
5. php artisan optimize:clear
6. Tes buat draft, edit, cetak tanda terima, dan Kirim ERP di database testing terlebih dahulu.

Mapping: service_quotations.serial_number -> AS_INGCO/AS_SMI.dbo.IC_Services.SerialNumber
Status, description, accessories hanya disimpan di DB_AppHub dan dicetak.
Tidak mengubah record ERP yang sudah dikirim sebelumnya.
ERP insert akan menolak jika SerialNumber tidak terdapat di skema ERP.
Jika patcher menampilkan 'Tidak cocok', jangan paksa; kirim pesan tersebut dan file terbaru.
