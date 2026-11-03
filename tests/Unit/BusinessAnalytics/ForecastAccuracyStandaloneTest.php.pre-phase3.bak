<?php

declare(strict_types=1);

require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ForecastEngine.php';

use App\Services\BusinessAnalytics\ForecastEngine;

function assertOk(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$series = [];
$start = new DateTimeImmutable('2024-01-01');
for ($i=0; $i<30; $i++) {
    $d = $start->modify("+{$i} month");
    $season = 1.0 + (sin(((int)$d->format('n')) / 12 * 2 * M_PI) * .10);
    $series[$d->format('Y-m')] = 3_000_000_000 * (1 + $i * .005) * $season;
}
$engine = new ForecastEngine();
$accuracy = $engine->backtest($series, 6);
assertOk(isset($accuracy['wape'], $accuracy['mape'], $accuracy['accuracy_score']), 'backtest fields missing');
assertOk($accuracy['tested_periods'] >= 4, 'must test multiple periods');
assertOk($accuracy['accuracy_score'] >= 0 && $accuracy['accuracy_score'] <= 1, 'accuracy score range');
assertOk($accuracy['wape'] < .25, 'smooth synthetic series should have acceptable WAPE');
echo "PASS forecast_accuracy_test\n";
