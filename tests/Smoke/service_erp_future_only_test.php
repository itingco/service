<?php

declare(strict_types=1);

// Test logika nomor tanpa koneksi DB. Jalankan setelah menimpa patch.
require_once dirname(__DIR__, 2).'/app/Services/ServiceQuotations/ServiceErpNumbering.php';

use App\Services\ServiceQuotations\ServiceErpNumbering;

$last = ServiceErpNumbering::parse('INC/SC-ACH/230727/0005', 'SC-ACH');
$oct = new DateTimeImmutable('2026-10-22 15:00:00', new DateTimeZone('Asia/Jakarta'));
$date = ServiceErpNumbering::dateForSend($oct);
if ($date !== '261022' || ServiceErpNumbering::monthPrefix($last['prefix'], 'SC-ACH', $date) !== 'INC/SC-ACH/2610') {
    throw new RuntimeException('Periode bulan berjalan salah.');
}
// Catatan 2023 atau September 2026 tidak boleh ikut dihitung pada Oktober 2026.
$erpNumbers = [
    ['number' => 'INC/SC-ACH/230727/0005', 'counter' => 5],
    ['number' => 'INC/SC-ACH/260930/0099', 'counter' => 99],
    ['number' => 'INC/SC-BDG/261022/0123', 'counter' => 123],
    ['number' => 'INC/SC-ACH/261005/0004', 'counter' => 4],
    ['number' => 'INC/SC-ACH/261021/0005', 'counter' => 5],
];
$monthPrefix = ServiceErpNumbering::monthPrefix('INC', 'SC-ACH', $date);
$currentMonth = array_filter($erpNumbers, static fn (array $row): bool => str_starts_with($row['number'], $monthPrefix));
$counters = array_map(static fn (array $row): int => $row['counter'], $currentMonth);
$next = ServiceErpNumbering::nextCounter(...array_values($counters));
$docNo = ServiceErpNumbering::make($last['prefix'], 'SC-ACH', $date, $next, $last['width']);
if ($docNo !== 'INC/SC-ACH/261022/0006') {
    throw new RuntimeException('Counter di bulan berjalan salah: '.$docNo);
}
// Bulan berikutnya jika tanpa transaksi pada bulan itu mulai kembali 0001.
$novDate = '261101';
$nov = ServiceErpNumbering::make('INC', 'SC-ACH', $novDate, ServiceErpNumbering::nextCounter(), 4);
if ($nov !== 'INC/SC-ACH/261101/0001') throw new RuntimeException('Counter belum reset di bulan baru.');

$source = file_get_contents(dirname(__DIR__, 2).'/app/Services/ServiceQuotations/ServiceQuotationErpGateway.php');
if (!is_string($source)
    || substr_count($source, 'CHARINDEX(?, CONVERT(nvarchar(max), Remarks)) > 0') !== 2
    || str_contains($source, 'Remarks LIKE ?')
    || !str_contains($source, 'ServiceErpNumbering::monthPrefix(')
    || !str_contains($source, 'AND LEFT(LTRIM(RTRIM(ServiceNumber)), ?) = ?')
    || !str_contains($source, '$prefixLength + 4, $serviceType, $prefixLength, $monthPrefix')
    || str_contains($source, '(int) $lastNumber->ServiceCounter,')
    || !str_contains($source, 'ServiceErpNumbering::dateForSend($erpNow)')) {
    throw new RuntimeException('Gateway belum menggunakan counter per bulan dan marker literal.');
}

echo "PASS - Oktober memakai counter 0006, November reset 0001; marker ERP aman.\n";
