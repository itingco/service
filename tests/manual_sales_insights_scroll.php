<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$index = file_get_contents($root.'/resources/views/sales-insights/index.blade.php');
$management = file_get_contents($root.'/resources/views/sales-insights/partials/management-insights.blade.php');

$checks = [
    'shared fixed scroll height' => str_contains($index, '.insight-equal-scroll'),
    'vertical scrolling enabled' => str_contains($index, 'overflow-y:auto'),
    'stable scrollbar gutter' => str_contains($index, 'scrollbar-gutter:stable'),
    'pace table uses equal scroll class' => str_contains($management, 'insight-table-wrap insight-equal-scroll'),
    'action list uses equal scroll class' => str_contains($management, 'insight-action-scroll insight-equal-scroll'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Sales insights scroll checks failed:\n- ".implode("\n- ", $failed)."\n");
    exit(1);
}

echo "Sales insights equal scroll checks passed.\n";
