<?php

return [
    'provider' => env('BUSINESS_ADVISOR_PROVIDER', 'disabled'),
    'model' => env('BUSINESS_ADVISOR_MODEL', 'gpt-5.6-terra'),
    'api_key' => env('BUSINESS_ADVISOR_API_KEY'),
    'web_research' => env('BUSINESS_ADVISOR_WEB_RESEARCH', true),
    'timeout_seconds' => max(10, (int) env('BUSINESS_ADVISOR_TIMEOUT_SECONDS', 45)),
];
