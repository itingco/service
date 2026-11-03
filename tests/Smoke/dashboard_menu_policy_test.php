<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Support\DashboardMenuPolicy;

$policy = new DashboardMenuPolicy();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert($policy->allows('admin', [], 'stock_aging') === true, 'Administrator must always have full access.');
$assert($policy->allows('sales', ['sales_dashboard', 'sales_kpi'], 'sales_kpi') === true, 'Granted menu should be allowed.');
$assert($policy->allows('sales', ['sales_dashboard', 'sales_kpi'], 'stock_aging') === false, 'Ungrant menu should be denied.');
$assert($policy->allows('', ['sales_dashboard'], 'sales_dashboard') === false, 'Empty role should be denied.');

fwrite(STDOUT, "PASS: dashboard menu policy behavior.\n");
