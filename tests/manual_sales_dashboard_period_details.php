<?php

declare(strict_types=1);

require dirname(__DIR__).'/app/Services/SalesDashboardService.php';

use App\Services\SalesDashboardService;

if (!method_exists(SalesDashboardService::class, 'detailsForPeriod')) {
    throw new RuntimeException('SalesDashboardService::detailsForPeriod belum tersedia.');
}

$method = new ReflectionMethod(SalesDashboardService::class, 'detailsForPeriod');
if (!$method->isPublic()) {
    throw new RuntimeException('detailsForPeriod harus public agar bisa dipakai SalesInsightsController.');
}

if ($method->getNumberOfParameters() !== 3) {
    throw new RuntimeException('detailsForPeriod harus menerima filter, start, dan end.');
}

echo "Sales dashboard period detail contract checks passed.\n";
