<?php

declare(strict_types=1);

return [
    // DO NOT assume PriceLevelID=1 means RETAIL. AS_INGCO uses ID=3;
    // AS_SMI may use another ID. Match by the name in IC_PriceLevels instead.
    'retail_price_level_name' => env('SPAREPART_RETAIL_PRICE_LEVEL_NAME', 'RETAIL'),
    // Reference price is displayed separately ONLY if RETAIL is missing.
    // It must never be mislabeled as HET / RETAIL.
    'reference_price_level_name' => env('SPAREPART_REFERENCE_PRICE_LEVEL_NAME', 'HDB'),
    'similar_depth' => (int) env('SPAREPART_SIMILAR_DEPTH', 2),
    'max_alternatives' => (int) env('SPAREPART_MAX_ALTERNATIVES', 80),
];
