<?php

declare(strict_types=1);

require __DIR__.'/../../app/Services/ServiceQuotations/ServiceErpNumbering.php';

use App\Services\ServiceQuotations\ServiceErpNumbering;

function expect(bool $result, string $desc): void {
    if (!$result) throw new RuntimeException('FAIL: '.$desc);
    echo 'PASS: '.$desc."\n";
}

$fmt = ServiceErpNumbering::parse('INC/SC-ACH/230727/0005', 'SC-ACH');
expect($fmt['prefix'] === 'INC' && $fmt['date'] === '230727' && $fmt['width'] === 4, 'ambil prefix, tanggal, dan lebar counter dari nomor terakhir ERP');
$counter = ServiceErpNumbering::nextCounter(5, 8, 9);
expect($counter === 10, 'counter menggunakan angka terbesar yang ditemukan pada tabel ERP, bukan sekadar +1 dari baris terakhir');
expect(ServiceErpNumbering::make($fmt['prefix'], 'SC-ACH', ServiceErpNumbering::numberDate($fmt, 'last_erp'), $counter, $fmt['width']) === 'INC/SC-ACH/230727/0010', 'format nomor mengikuti pola ERP terakhir');
$sent = new DateTimeImmutable('2026-10-22 10:34:00', new DateTimeZone('Asia/Jakarta'));
expect(ServiceErpNumbering::numberDate($fmt, 'send_date', $sent) === '261022', 'mode opsional send_date menggunakan tanggal WIB');
try { ServiceErpNumbering::numberDate($fmt, 'unknown'); throw new RuntimeException('Mode tidak valid lolos'); }
catch (RuntimeException $e) { expect(str_contains($e->getMessage(), 'tidak dikenal'), 'konfigurasi tidak valid ditolak'); }

$gateway = file_get_contents(__DIR__.'/../../app/Services/ServiceQuotations/ServiceQuotationErpGateway.php');
expect(str_contains($gateway, 'FROM dbo.IC_Services WITH (HOLDLOCK)') && str_contains($gateway, 'MAX(TRY_CONVERT(bigint, ServiceCounter))') && str_contains($gateway, 'NumberSuffix'), 'gateway menggunakan nomor terkini serta MAX counter dan suffix dari ERP');
expect(str_contains($gateway, 'sp_getapplock') && str_contains($gateway, 'TABLOCKX'), 'alokasi nomor tetap dilindungi lock dalam transaksi');
$print = file_get_contents(__DIR__.'/../../resources/views/service-quotations/print.blade.php');
expect(!str_contains($print, '<strong>Service Type:</strong>') && !str_contains($print, '<strong>Nomor ERP:</strong>') && !str_contains($print, '<strong>Database:</strong>'), 'printout tidak lagi menampilkan tiga field internal ERP');
expect(str_contains($print, 'service-center-address') && str_contains($print, '14 (empat belas) hari kerja'), 'logo / alamat dan catatan 14 hari tetap ada');
