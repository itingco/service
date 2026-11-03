<?php

declare(strict_types=1);

return [
    // Storage draft Penawaran Service WAJIB di DB_AppHub (koneksi yang sudah ada).
    // Bukan AS_INGCO / AS_SMI dan bukan SQLite.
    'local_connection' => 'sqlsrv_apphub',
    // Service Type mengikuti database barang rusak, bukan satu koneksi statis.
    // INGCO => sqlsrv_ingco; SMI => sqlsrv_smi (lihat ServiceDocTypeRepository).
    // USP tidak menerima parameter; jalankan EXEC langsung pada ERP yang dipilih.
    // Filter TableName = serviceType dilakukan di PHP setelah seluruh hasil diterima.
    'doc_types_procedure' => 'dbo.USP_HRM_DocTypes_QueryAll',
    'doc_types_table_name' => 'serviceType',
    'max_lines' => 30,
    'max_lookup' => 60,

    // Kirim ke ERP menulis IC_Services + IC_ServiceSpareParts hanya saat tombol
    // benar-benar ditekan. Tidak ada migrasi/perubahan skema ERP.
    // Set SERVICE_ERP_SEND_ENABLED=false di .env untuk menonaktifkan sementara.
    // Gateway memeriksa nama database, tabel/kolom wajib, CustomerID, stok,
    // ID item/gudang, AverageCost & nomor terakhir sebelum transaksi INSERT.
    'erp' => [
        'enabled' => (bool) env('SERVICE_ERP_SEND_ENABLED', true),
        // Nomor ERP selalu memakai tanggal pengiriman saat ini (YYMMDD, WIB),
        // sedangkan counter di-reset per bulan kalender (YYMM), per Service Type,
        // per database ERP. Nomor terbesar yang dihitung hanya dari bulan berjalan.
        // Variable SERVICE_ERP_NUMBER_DATE_MODE lama tidak lagi digunakan.
        'timezone' => env('SERVICE_ERP_TIMEZONE', 'Asia/Jakarta'),
    ],
];
