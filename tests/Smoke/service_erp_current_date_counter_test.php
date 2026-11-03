<?php

declare(strict_types=1);

require __DIR__.'/../../app/Services/ServiceQuotations/ServiceErpNumbering.php';

use App\Services\ServiceQuotations\ServiceErpNumbering;

function verify(bool $passed, string $message): void
{
    if (!$passed) {
        throw new RuntimeException('FAIL: '.$message);
    }
    echo 'PASS: '.$message.PHP_EOL;
}

$last = ServiceErpNumbering::parse('INC/SC-ACH/230727/0005', 'SC-ACH');
$sent = new DateTimeImmutable('2026-10-22 10:34:00', new DateTimeZone('Asia/Jakarta'));
$counter = ServiceErpNumbering::nextCounter(5, 5, 5);
$number = ServiceErpNumbering::make($last['prefix'], 'SC-ACH', ServiceErpNumbering::dateForSend($sent), $counter, $last['width']);
verify($number === 'INC/SC-ACH/261022/0006', 'tanggal kirim YYMMDD saat ini + counter terakhir ERP 5 -> 6');

$other = new DateTimeImmutable('2026-12-31 18:15:00', new DateTimeZone('UTC'));
verify(ServiceErpNumbering::dateForSend($other) === '270101', 'konversi zona waktu Asia/Jakarta sebelum mengambil tanggal');
verify(ServiceErpNumbering::nextCounter(5, 8, 12) === 13, 'ambil counter terbesar lintas tanggal dari ERP');

$gateway = file_get_contents(__DIR__.'/../../app/Services/ServiceQuotations/ServiceQuotationErpGateway.php');
verify(str_contains($gateway, 'ServiceErpNumbering::dateForSend($erpNow)')
    && !str_contains($gateway, "config('service_quotations.erp.number_date_mode'")
    && !str_contains($gateway, 'ServiceErpNumbering::numberDate('), 'gateway tidak mewarisi tanggal nomor ERP sebelumnya');
verify(str_contains($gateway, 'MAX(TRY_CONVERT(bigint, ServiceCounter))')
    && str_contains($gateway, 'NumberSuffix')
    && str_contains($gateway, 'WHERE ServiceType = ?')
    && str_contains($gateway, 'sp_getapplock'), 'gateway tetap membaca counter tertinggi per Service Type dan memakai transaction lock');

echo 'OK — pengujian nomor ERP selesai (tanpa koneksi SQL Server)'.PHP_EOL;
