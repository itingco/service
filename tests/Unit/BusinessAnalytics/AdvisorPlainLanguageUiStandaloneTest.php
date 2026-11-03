<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$view = file_get_contents($root.'/resources/views/business-analytics/product-advisor.blade.php');
$pdf = file_get_contents($root.'/resources/views/business-analytics/pdf/product-advisor.blade.php');
$js = file_get_contents($root.'/public/js/product-strategy-advisor.js');

function checkUi(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

foreach (['Buyer Problem','Selling Angle','First Test','Strategy Action Lab','Market Opportunity Finder'] as $old) {
    checkUi(!str_contains($view, $old), 'Old UI jargon remains: '.$old);
    checkUi(!str_contains($pdf, $old), 'Old PDF jargon remains: '.$old);
}
foreach (['Peluang Pasar','Kenapa Cocok','Cara Menawarkan','Aksi yang Dicoba','Saran Tindakan','Berhasil jika','Ubah / hentikan jika'] as $required) {
    checkUi(str_contains($view, $required), 'Missing simple UI label: '.$required);
}
foreach (['Peluang Pasar','Kenapa Cocok','Cara Menawarkan','Aksi yang Dicoba','Saran Tindakan'] as $required) {
    checkUi(str_contains($pdf, $required), 'Missing simple PDF label: '.$required);
}
foreach (['FAKTA','KESIMPULAN','SARAN TINDAKAN','TES PENJUALAN','SUMBER INTERNET'] as $required) {
    checkUi(str_contains($js, $required), 'Missing simple chat label: '.$required);
}

echo "PASS: Plain-language UI/PDF/chat labels\n";
