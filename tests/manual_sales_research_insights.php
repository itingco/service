<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dto = $root.'/app/Data/SalesResearchInsights.php';
$service = $root.'/app/Services/SalesResearchInsightService.php';

if (!is_file($dto) || !is_file($service)) {
    throw new RuntimeException('SalesResearchInsights atau SalesResearchInsightService belum tersedia.');
}

require $dto;
require $service;

use App\Services\SalesResearchInsightService;

function assertSameStrict(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

$current = [
    ['item_code'=>'A1','item_name'=>'Produk Alpha','grade'=>'A','line'=>'ONLINE','customer_code'=>'C1','customer_name'=>'Customer 1','line_total'=>600.0,'quantity'=>6.0,'discount'=>5.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'A1','item_name'=>'Produk Alpha','grade'=>'A','line'=>'ONLINE','customer_code'=>'C2','customer_name'=>'Customer 2','line_total'=>400.0,'quantity'=>4.0,'discount'=>5.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'A1','item_name'=>'Produk Alpha','grade'=>'A','line'=>'JAKARTA','customer_code'=>'C3','customer_name'=>'Customer 3','line_total'=>200.0,'quantity'=>2.0,'discount'=>5.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'B1','item_name'=>'Produk Baru','grade'=>'B','line'=>'JAKARTA','customer_code'=>'C1','customer_name'=>'Customer 1','line_total'=>500.0,'quantity'=>5.0,'discount'=>0.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'C1','item_name'=>'Produk Turun','grade'=>'B','line'=>'MEDAN','customer_code'=>'C9','customer_name'=>'Customer 9','line_total'=>200.0,'quantity'=>2.0,'discount'=>20.0,'discount2'=>5.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'D1','item_name'=>'Produk Clearance','grade'=>'','line'=>'MEDAN','customer_code'=>'C8','customer_name'=>'Customer 8','line_total'=>100.0,'quantity'=>1.0,'discount'=>30.0,'discount2'=>0.0,'discontinued'=>true,'clearance'=>true],
];

$previous = [
    ['item_code'=>'A1','item_name'=>'Produk Alpha','grade'=>'A','line'=>'ONLINE','customer_code'=>'C1','customer_name'=>'Customer 1','line_total'=>800.0,'quantity'=>8.0,'discount'=>0.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'C1','item_name'=>'Produk Turun','grade'=>'B','line'=>'MEDAN','customer_code'=>'C9','customer_name'=>'Customer 9','line_total'=>500.0,'quantity'=>5.0,'discount'=>10.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
    ['item_code'=>'E1','item_name'=>'Produk Hilang','grade'=>'A','line'=>'JAKARTA','customer_code'=>'C7','customer_name'=>'Customer 7','line_total'=>300.0,'quantity'=>3.0,'discount'=>0.0,'discount2'=>0.0,'discontinued'=>false,'clearance'=>false],
];

$insights = (new SalesResearchInsightService())->build($current, $previous);

$signals = [];
foreach ($insights->productSignals as $row) {
    $signals[$row['item_code']] = $row;
}

assertSameStrict('SCALE UP', $signals['A1']['signal'], 'Produk Alpha seharusnya SCALE UP.');
assertSameStrict('PERMINTAAN BARU', $signals['B1']['signal'], 'Produk Baru seharusnya PERMINTAAN BARU.');
assertSameStrict('EVALUASI', $signals['C1']['signal'], 'Produk Turun seharusnya EVALUASI.');
assertSameStrict('FASE KELUAR', $signals['D1']['signal'], 'Produk Clearance seharusnya FASE KELUAR.');
assertSameStrict('PERMINTAAN HILANG', $signals['E1']['signal'], 'Produk Hilang seharusnya PERMINTAAN HILANG.');
assertSameStrict('B1', $insights->newDemand[0]['item_code'], 'New demand salah.');
assertSameStrict('E1', $insights->lostDemand[0]['item_code'], 'Lost demand salah.');
assertSameStrict('ONLINE', $signals['A1']['primary_line'], 'Primary line Produk Alpha salah.');
assertSameStrict(3, $signals['A1']['customer_count'], 'Customer breadth Produk Alpha salah.');
assertSameStrict('C1', $insights->discountWatch[0]['item_code'], 'Discount watch salah.');
assertSameStrict('A', $insights->gradeDemand[0]['grade'], 'Grade demand urutan pertama salah.');

echo "Sales research insight checks passed.\n";
