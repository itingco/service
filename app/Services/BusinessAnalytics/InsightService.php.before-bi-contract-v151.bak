<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class InsightService
{
    /**
     * @param array<string,float> $trend
     * @param list<array<string,mixed>> $breakdown
     * @return list<array{type:string,title:string,text:string}>
     */
    public function build(array $trend, array $breakdown): array
    {
        if ($trend === []) {
            return [[
                'type' => 'warning',
                'title' => 'Data analytics belum tersedia',
                'text' => 'Jalankan php artisan analytics:sync --months=36 untuk mengisi histori.',
            ]];
        }

        $keys = array_keys($trend);
        $last = end($keys);
        $current = $trend[$last] ?? 0.0;
        $priorYear = date('Y-m', strtotime($last.'-01 -1 year'));
        $yoy = isset($trend[$priorYear]) && $trend[$priorYear] > 0.0 ? ($current / $trend[$priorYear]) - 1.0 : null;

        $values = array_values($trend);
        $last3 = array_sum(array_slice($values, -3));
        $previous3 = array_sum(array_slice($values, -6, 3));
        $momentum = $previous3 > 0.0 ? ($last3 / $previous3) - 1.0 : null;

        $top5 = array_sum(array_map(static fn (array $r): float => (float) $r['sales'], array_slice($breakdown, 0, 5)));
        $total = array_sum(array_map(static fn (array $r): float => (float) $r['sales'], $breakdown));
        $concentration = $total > 0.0 ? $top5 / $total : null;

        $insights = [];
        if ($yoy !== null) {
            $insights[] = [
                'type' => $yoy >= 0 ? 'success' : 'danger',
                'title' => 'Pertumbuhan YoY',
                'text' => sprintf('Penjualan bulan terakhir %s %.1f%% dibanding bulan yang sama tahun lalu.', $yoy >= 0 ? 'naik' : 'turun', abs($yoy) * 100),
            ];
        }
        if ($momentum !== null) {
            $insights[] = [
                'type' => $momentum >= 0 ? 'success' : 'warning',
                'title' => 'Momentum 3 Bulan',
                'text' => sprintf('Akumulasi 3 bulan terakhir %s %.1f%% dibanding 3 bulan sebelumnya.', $momentum >= 0 ? 'menguat' : 'melemah', abs($momentum) * 100),
            ];
        }
        if ($concentration !== null) {
            $insights[] = [
                'type' => $concentration >= 0.55 ? 'warning' : 'info',
                'title' => 'Konsentrasi Kontributor',
                'text' => sprintf('Top 5 kontributor menyumbang %.1f%% dari penjualan pada level analisis saat ini.', $concentration * 100),
            ];
        }

        $positive = array_values(array_filter($breakdown, static fn (array $r): bool => ($r['yoy_growth'] ?? null) !== null && $r['yoy_growth'] > 0));
        usort($positive, static fn (array $a, array $b): int => $b['yoy_growth'] <=> $a['yoy_growth']);
        if ($positive !== []) {
            $row = $positive[0];
            $insights[] = [
                'type' => 'success',
                'title' => 'Growth Driver',
                'text' => sprintf('%s menjadi pendorong pertumbuhan terkuat dengan YoY %.1f%%.', $row['label'], $row['yoy_growth'] * 100),
            ];
        }

        $negative = array_values(array_filter($breakdown, static fn (array $r): bool => ($r['yoy_growth'] ?? null) !== null && $r['yoy_growth'] < 0));
        usort($negative, static fn (array $a, array $b): int => $a['yoy_growth'] <=> $b['yoy_growth']);
        if ($negative !== []) {
            $row = $negative[0];
            $insights[] = [
                'type' => 'danger',
                'title' => 'Area Perlu Perhatian',
                'text' => sprintf('%s mengalami penurunan YoY %.1f%% dan layak ditelusuri lebih lanjut.', $row['label'], abs($row['yoy_growth']) * 100),
            ];
        }

        return array_slice($insights, 0, 5);
    }
}
