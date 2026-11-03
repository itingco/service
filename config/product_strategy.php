<?php

declare(strict_types=1);

return [
    'master_connection' => env('PRODUCT_STRATEGY_CONNECTION', 'sqlsrv_apphub'),
    'focus_table' => env('PRODUCT_STRATEGY_FOCUS_TABLE', 'dbo.ItemFocus'),
    'similar_table' => env('PRODUCT_STRATEGY_SIMILAR_TABLE', 'dbo.ItemSimmilar'),

    'analytics_connection' => env('PRODUCT_STRATEGY_ANALYTICS_CONNECTION', 'sqlite_targets'),
    'analytics_table' => env('PRODUCT_STRATEGY_ANALYTICS_TABLE', 'business_analytics_monthly_sales'),
];
