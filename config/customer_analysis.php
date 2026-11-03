<?php

return [
    'connections' => [
        'INGCO' => 'sqlsrv_ingco',
        'SMI' => 'sqlsrv_smi',
    ],

    // Histori transaksi lama hanya dicari ketika customer sudah tidak aktif.
    // Default 60 bulan agar tetap realistis dari sisi performa SQL Server.
    'history_months' => max(12, (int) env('CUSTOMER_ANALYSIS_HISTORY_MONTHS', 60)),
    'history_chunk_months' => max(1, (int) env('CUSTOMER_ANALYSIS_HISTORY_CHUNK_MONTHS', 12)),
    'per_page' => max(10, (int) env('CUSTOMER_ANALYSIS_PER_PAGE', 25)),
];
