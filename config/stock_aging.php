<?php

return [
    'procedure' => env('STOCK_AGING_PROCEDURE', 'USP_Dashboard_SisaStokWarehouse'),
    'cache_seconds' => max(0, (int) env('STOCK_AGING_CACHE_SECONDS', 300)),
    'detail_per_page' => max(25, (int) env('STOCK_AGING_DETAIL_PER_PAGE', 100)),
];
