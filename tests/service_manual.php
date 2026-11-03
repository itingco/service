<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Contracts\SalesDataSource;
use App\Data\SalesByItemFilter;
use App\Services\SalesByItemAggregator;
use App\Services\SalesByItemService;
use App\Support\SalesRowNormalizer;

final class FakeSalesDataSource implements SalesDataSource
{
    public array $requestedSources = [];

    public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $this->requestedSources[] = $source;

        return match ($source) {
            'INGCO' => [(object) ['ItemCode' => 'A001', 'ItemName' => 'Bor', 'Quantity' => 2, 'LineTotal' => 100]],
            'SMI' => [(object) ['ItemCode' => 'A001', 'ItemName' => 'Bor', 'Quantity' => 1, 'LineTotal' => 50]],
            default => [],
        };
    }
}

$source = new FakeSalesDataSource();
$service = new SalesByItemService($source, new SalesRowNormalizer(), new SalesByItemAggregator());
$report = $service->generate(SalesByItemFilter::from('all', '2026-07-22'));

if ($source->requestedSources !== ['INGCO', 'SMI']) {
    throw new RuntimeException('Service tidak meminta kedua database.');
}
if (count($report->rows) !== 1 || $report->rows[0]->totalSales !== 150.0) {
    throw new RuntimeException('Service tidak menggabungkan data dengan benar.');
}

fwrite(STDOUT, "PASS: service requests and combines INGCO and SMI\n");
