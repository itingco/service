<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layoutPath = $root.'/resources/views/layouts/app.blade.php';
$strategyPath = $root.'/resources/views/stock-aging/strategy-review.blade.php';

$layout = file_get_contents($layoutPath);
$strategy = file_get_contents($strategyPath);

if ($layout === false || $strategy === false) {
    fwrite(STDERR, "Unable to read Blade views.\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$masterPos = strpos($layout, '>MASTER DATA<');
$productFocusPos = strpos($layout, "route('product-strategy.product-focus.index')");
$similarItemsPos = strpos($layout, "route('product-strategy.similar-items.index')");
$settingsPos = strpos($layout, '>PENGATURAN<');
$advisorPos = strpos($layout, "route('business-analytics.product-advisor.index')");

$expect($masterPos !== false, 'Sidebar must contain a MASTER DATA section.');
$expect($productFocusPos !== false, 'Sidebar must contain Product Focus menu.');
$expect($similarItemsPos !== false, 'Sidebar must contain Similar Items menu.');
$expect($settingsPos !== false, 'Sidebar must still contain PENGATURAN section.');
$expect($advisorPos !== false, 'Sidebar must still contain Product Strategy Advisor.');

if ($masterPos !== false && $productFocusPos !== false && $similarItemsPos !== false && $settingsPos !== false && $advisorPos !== false) {
    $expect($advisorPos < $masterPos, 'Product Strategy Advisor must remain in LAPORAN before MASTER DATA.');
    $expect($masterPos < $productFocusPos, 'Product Focus must be inside/after MASTER DATA section.');
    $expect($productFocusPos < $similarItemsPos, 'Similar Items must follow Product Focus in MASTER DATA.');
    $expect($similarItemsPos < $settingsPos, 'MASTER DATA menus must appear before PENGATURAN.');
}

if (preg_match('/<thead><tr>(.*?)<\/tr><\/thead>/s', $strategy, $headerMatch) === 1) {
    preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $headerMatch[1], $cells);
    $labels = array_map(
        static fn (string $value): string => trim(html_entity_decode(strip_tags($value))),
        $cells[1] ?? []
    );
    $expected = ['Product Focus', 'Item Code', 'Item Name', 'Qty', 'Sisa Stok', 'Sales MTD', 'Age', 'Status'];
    $expect($labels === $expected, 'Stock Strategy columns must be: '.implode(' | ', $expected).'. Got: '.implode(' | ', $labels));
} else {
    $failures[] = 'Unable to find Stock Strategy table header.';
}

$salesCellPos = strpos($strategy, "\$money(\$item['sales_mtd'] ?? 0)");
$ageCellPos = strpos($strategy, "\$item['age']");
if ($salesCellPos !== false && $ageCellPos !== false) {
    $expect($salesCellPos < $ageCellPos, 'Age cell must be rendered after Sales MTD.');
} else {
    $failures[] = 'Unable to locate Sales MTD or Age cell in Stock Strategy table body.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "PASS: MASTER DATA sidebar placement and Stock Strategy column order are correct.\n";
