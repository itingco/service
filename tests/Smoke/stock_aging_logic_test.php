<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Contracts\SalesDataSource;
use App\Contracts\StockAgingDataSource;
use App\Data\StockAgingFilter;
use App\Services\StockAgingAggregator;
use App\Services\StockAgingService;
use App\Support\StockAgingRowNormalizer;
use App\Support\StockAgingSalesRowNormalizer;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$stock = new class implements StockAgingDataSource {
    public function fetch(string $source, DateTimeImmutable $date): array
    {
        if ($source === 'INGCO') {
            return [
                (object) ['ItemID'=>10,'Item Code'=>'A','Item Name'=>'Alpha','WarehouseID'=>1,'Warehouse Code'=>'W1','Warehouse Name'=>'INGCO REGULAR','Age'=>20,'Smallest On Hand'=>2,'Price KHUSUS-1'=>100,'Sisa Stok'=>200,'Discontinue'=>0,'Clearance'=>1],
                // Clearance must be derived from warehouse name/code containing SALE, not the USP Clearance field.
                (object) ['ItemID'=>11,'Item Code'=>'B','Item Name'=>'Beta','WarehouseID'=>2,'Warehouse Code'=>'W-SALE','Warehouse Name'=>'INGCO SALE','Age'=>140,'Smallest On Hand'=>1,'Price KHUSUS-1'=>300,'Sisa Stok'=>300,'Discontinue'=>1,'Clearance'=>0],
            ];
        }

        return [
            // Same ItemID as INGCO on purpose. It must remain isolated by source.
            (object) ['ItemID'=>10,'Item Code'=>'A-SMI','Item Name'=>'Alpha SMI','WarehouseID'=>1,'Warehouse Code'=>'W1','Warehouse Name'=>'SMI W1','Age'=>50,'Smallest On Hand'=>4,'Price KHUSUS-1'=>50,'Sisa Stok'=>200,'Discontinue'=>0,'Clearance'=>0],
        ];
    }
};

$sales = new class implements SalesDataSource {
    public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        if ($source === 'INGCO') {
            return [
                (object) ['ItemID'=>10,'WarehouseID'=>1,'LineTotal'=>80],
                (object) ['ItemID'=>10,'WarehouseID'=>1,'LineTotal'=>-20],
                (object) ['ItemID'=>11,'WarehouseID'=>2,'LineTotal'=>15],
            ];
        }

        return [(object) ['ItemID'=>10,'WarehouseID'=>1,'LineTotal'=>150]];
    }
};

$service = new StockAgingService(
    stockDataSource: $stock,
    salesDataSource: $sales,
    stockNormalizer: new StockAgingRowNormalizer(),
    salesNormalizer: new StockAgingSalesRowNormalizer(),
    aggregator: new StockAgingAggregator(),
    cacheSeconds: 0,
);

$report = $service->generate(StockAgingFilter::from('2026-08-22', 'all', 'all'));
$assert(count($report->sources) === 2, 'Combined database selection must return INGCO and SMI.');
$assert(abs($report->grandTotal['sisa_stock'] - 700.0) < 0.001, 'Grand stock value should be 700.');
$assert(abs($report->grandTotal['sales_mtd'] - 225.0) < 0.001, 'Grand sales must include negative return: 80-20+15+150=225.');
$assert(abs($report->over120Stock - 300.0) < 0.001, '>120 stock should be 300.');

$ingcoOnly = $service->generate(StockAgingFilter::from('2026-08-22', 'all', 'ingco'));
$assert(array_keys($ingcoOnly->sources) === ['INGCO'], 'INGCO database selection must fetch only INGCO.');
$assert(abs($ingcoOnly->grandTotal['sisa_stock'] - 500.0) < 0.001, 'INGCO-only stock should be 500.');
$assert(abs($ingcoOnly->grandTotal['sales_mtd'] - 75.0) < 0.001, 'INGCO-only sales should be 75.');

$smiOnly = $service->generate(StockAgingFilter::from('2026-08-22', 'all', 'smi'));
$assert(array_keys($smiOnly->sources) === ['SMI'], 'SMI database selection must fetch only SMI.');
$assert(abs($smiOnly->grandTotal['sisa_stock'] - 200.0) < 0.001, 'SMI-only stock should be 200.');

$clearance = $service->generate(StockAgingFilter::from('2026-08-22', 'clearance', 'all'));
$assert(abs($clearance->grandTotal['sisa_stock'] - 300.0) < 0.001, 'Clearance must mean warehouse name/code contains SALE.');
$assert(abs($clearance->grandTotal['sales_mtd'] - 15.0) < 0.001, 'Clearance sales must match only items stored in SALE warehouse.');

$normal = $service->generate(StockAgingFilter::from('2026-08-22', 'normal', 'all'));
$assert(abs($normal->grandTotal['sisa_stock'] - 400.0) < 0.001, 'USP Clearance flag must be ignored when warehouse does not contain SALE.');

$both = $service->generate(StockAgingFilter::from('2026-08-22', 'both', 'all'));
$assert(abs($both->grandTotal['sisa_stock'] - 300.0) < 0.001, 'Both filter means discontinued AND warehouse SALE.');

fwrite(STDOUT, "PASS: stock aging database filter, warehouse SALE clearance, matching, buckets, and totals.\n");
