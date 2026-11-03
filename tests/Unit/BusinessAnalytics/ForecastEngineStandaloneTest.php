<?php

declare(strict_types=1);

require_once __DIR__.'/../../../app/Services/BusinessAnalytics/ForecastEngine.php';

use App\Services\BusinessAnalytics\ForecastEngine;

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assertNear(float $actual, float $expected, float $tolerance, string $message): void {
    if (abs($actual - $expected) > $tolerance) {
        fwrite(STDERR, "FAIL: {$message}. actual={$actual}, expected={$expected}\n");
        exit(1);
    }
}

$engine = new ForecastEngine();

// 36 months with recurring seasonality and gentle growth.
$series = [];
for ($year = 2024; $year <= 2026; $year++) {
    for ($month = 1; $month <= 12; $month++) {
        if ($year === 2026 && $month > 8) {
            break;
        }
        $index = count($series);
        $season = [1=>0.86,2=>0.90,3=>0.98,4=>1.02,5=>1.05,6=>1.08,7=>1.02,8=>1.10,9=>1.14,10=>1.18,11=>1.24,12=>1.34][$month];
        $value = 10_000_000_000 * $season * (1 + ($index * 0.006));
        $series[sprintf('%04d-%02d', $year, $month)] = $value;
    }
}

$result = $engine->forecast($series, 3);
assertTrue(count($result) === 3, 'forecast harus menghasilkan 3 bulan');
assertTrue($result[0]['period'] === '2026-09', 'bulan pertama harus September 2026');
assertTrue($result[1]['period'] === '2026-10', 'bulan kedua harus Oktober 2026');
assertTrue($result[2]['period'] === '2026-11', 'bulan ketiga harus November 2026');
assertTrue($result[0]['forecast'] > 0, 'nilai forecast harus positif');
assertTrue($result[0]['lower'] <= $result[0]['forecast'], 'lower range tidak boleh di atas forecast');
assertTrue($result[0]['upper'] >= $result[0]['forecast'], 'upper range tidak boleh di bawah forecast');
assertTrue($result[0]['confidence'] >= 0 && $result[0]['confidence'] <= 1, 'confidence harus 0..1');

$short = $engine->forecast(['2026-07' => 100, '2026-08' => 120], 3);
assertTrue(count($short) === 3, 'series pendek tetap menghasilkan 3 bulan');
assertNear($short[0]['forecast'], 130.0, 35.0, 'forecast pendek harus mengikuti arah tren secara masuk akal');

$status = ForecastEngine::targetStatus(105, 100);
assertTrue($status === 'ABOVE TARGET', '105% target harus ABOVE TARGET');
assertTrue(ForecastEngine::targetStatus(95, 100) === 'ON TRACK', '95% target harus ON TRACK');
assertTrue(ForecastEngine::targetStatus(80, 100) === 'AT RISK', '80% target harus AT RISK');
assertTrue(ForecastEngine::targetStatus(80, null) === 'NO TARGET', 'target kosong harus NO TARGET');

echo "PASS ForecastEngineStandaloneTest\n";
