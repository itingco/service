<?php

declare(strict_types=1);

require __DIR__.'/../../app/Services/ServiceQuotations/ServiceErpNumbering.php';

use App\Services\ServiceQuotations\ServiceErpNumbering;

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('FAILED: '.$message);
    echo "PASS: {$message}\n";
}

$sendTime = new DateTimeImmutable('2026-10-21 14:30:00', new DateTimeZone('Asia/Jakarta'));
check(ServiceErpNumbering::dateForSend($sendTime) === '261021', 'YYMMDD berasal dari tanggal saat kirim WIB, bukan dari nomor lama 230727');
check(ServiceErpNumbering::dateForSend(new DateTimeImmutable('2026-10-21 07:30:00+00:00')) === '261021', 'konversi UTC ke WIB');
check(ServiceErpNumbering::nextCounter(5, 4, 5) === 6, 'counter tertinggi per Service Type menjadi 0006');
check(ServiceErpNumbering::make('INC','SC-ACH',ServiceErpNumbering::dateForSend($sendTime),6,4) === 'INC/SC-ACH/261021/0006','contoh nomor tahun berjalan sesuai format');
check(ServiceErpNumbering::parse('INC/SC-ACH/230727/0005','SC-ACH')['width'] === 4, 'tanggal lama hanya digunakan untuk mengenali prefix dan lebar counter');

$repo = file_get_contents(__DIR__.'/../../app/Services/ServiceQuotations/ServiceQuotationRepository.php');
$ctrl = file_get_contents(__DIR__.'/../../app/Http/Controllers/ServiceQuotationController.php');
$list = file_get_contents(__DIR__.'/../../resources/views/service-quotations/index.blade.php');
$installer = file_get_contents(__DIR__.'/../../INSTALL_SERVICE_MASTER_MENU.php');
check(str_contains($repo,'function searchPaginated') && str_contains($repo,'->paginate(') && str_contains($repo,'erp_doc_no'), 'list menggunakan pagination server-side, mendukung pencarian nomor ERP');
check(str_contains($ctrl,"'status' => ['nullable', 'in:all,draft,sent']") && str_contains($ctrl,'searchPaginated($filters)'), 'controller menyediakan filter status dan query');
check(str_contains($list, '$row->erp_doc_no') && str_contains($list, '$row->damaged_item_name') && str_contains($list, '$rows->links()'), 'list menampilkan nomor ERP, nama barang, pagination');
check(str_contains($installer,'service-quotations.service-centers') && str_contains($installer, 'MASTER DATA'), 'installer link Master Data tersedia');
foreach (['index','show','create','print'] as $page) {
    $view = file_get_contents(__DIR__.'/../../resources/views/service-quotations/'.$page.'.blade.php');
    check(!str_contains($view, "route('service-quotations.service-centers'"), "tidak ada link master alamat di halaman {$page}");
}
