<?php

declare(strict_types=1);

require __DIR__.'/../../app/Services/ServiceQuotations/ServiceErpNumbering.php';
use App\Services\ServiceQuotations\ServiceErpNumbering;

$checks = [
    ['INC/SC-JND/261020/0129', 'SC-JND', '261021', 130, 'INC/SC-JND/261021/0130'],
    ['INC/SC-LNG/261020/0022', 'SC-LNG', '261021', 23, 'INC/SC-LNG/261021/0023'],
    ['SMI/SC-PKU/261020/0051', 'SC-PKU', '261021', 52, 'SMI/SC-PKU/261021/0052'],
];
foreach ($checks as [$prior, $type, $date, $next, $expected]) {
    $format = ServiceErpNumbering::parse($prior, $type);
    $actual = ServiceErpNumbering::make($format['prefix'], $type, $date, $next, $format['width']);
    if ($actual !== $expected) throw new RuntimeException("Expected {$expected}, got {$actual}");
}
foreach ([['BAD-NO', 'SC-JND'], ['INC/SC-JND/261020/0129', 'SC-LNG']] as [$prior,$type]) {
    try { ServiceErpNumbering::parse($prior, $type); throw new RuntimeException('Invalid format accepted'); }
    catch (RuntimeException $e) { if ($e->getMessage() === 'Invalid format accepted') throw $e; }
}
echo "PASS: ERP number series, type separation, pad and invalid number guards.\n";
