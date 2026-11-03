<?php

declare(strict_types=1);

require __DIR__.'/../../app/Services/ServiceQuotations/ServiceErpNumbering.php';

use App\Services\ServiceQuotations\ServiceErpNumbering;

$parsed = ServiceErpNumbering::parse('INC/SC-ACH/230727/0005', 'SC-ACH');
assert($parsed['prefix'] === 'INC');
assert($parsed['width'] === 4);
$next = ServiceErpNumbering::nextCounter(4, 5, 5);
assert($next === 6);
assert(ServiceErpNumbering::make($parsed['prefix'], 'SC-ACH', '261021', $next, $parsed['width']) === 'INC/SC-ACH/261021/0006');
assert(ServiceErpNumbering::nextCounter(9, 5, 12) === 13);
try {
    ServiceErpNumbering::parse('INC/SC-ACH/INVALID/0005', 'SC-ACH');
    throw new RuntimeException('Invalid number must fail');
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'Invalid number must fail') throw $e;
}

$root = dirname(__DIR__, 2);
$gateway = file_get_contents($root.'/app/Services/ServiceQuotations/ServiceQuotationErpGateway.php');
$repo = file_get_contents($root.'/app/Services/ServiceQuotations/ServiceQuotationRepository.php');
$print = file_get_contents($root.'/resources/views/service-quotations/print.blade.php');
if (!str_contains($gateway, "'Asia/Jakarta'") || str_contains($gateway, 'GETDATE()')) throw new RuntimeException('Nomor masih memakai jam SQL Server.');
if (!str_contains($repo, "!== 'draft'")) throw new RuntimeException('Edit draft belum dibatasi.');
foreach (['ingco-indonesia.png', 'wadfow.png', 'tanpa biaya (gratis)'] as $needle) {
    if (!str_contains($print, $needle)) throw new RuntimeException('Print belum lengkap: '.$needle);
}

echo "PASS: serial, tanggal YYMMDD, edit draft, branding, note bebas biaya jasa\n";
