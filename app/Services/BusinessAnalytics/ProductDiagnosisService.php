<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class ProductDiagnosisService
{
    /** @param array<string,mixed> $facts @return array{labels:list<string>,facts:list<array<string,mixed>>,inferences:list<array<string,string>>,primary:string} */
    public function diagnose(array $facts): array
    {
        $labels = [];
        $evidence = [];
        $inferences = [];

        $momentum = $this->nullableFloat($facts['momentum_3m'] ?? null);
        $yoy = $this->nullableFloat($facts['yoy_growth'] ?? null);
        $onlineShare = $this->nullableFloat($facts['online_share'] ?? null);
        $onlineYoy = $this->nullableFloat($facts['online_yoy_growth'] ?? null);
        $active = (int) ($facts['active_buyers'] ?? 0);
        $previousActive = (int) ($facts['previous_active_buyers'] ?? 0);
        $top5 = $this->nullableFloat($facts['top5_share'] ?? null);
        $cv = $this->nullableFloat($facts['volatility_cv'] ?? null);
        $sales3m = (float) ($facts['sales_3m'] ?? 0.0);
        $previous3m = (float) ($facts['previous_3m_sales'] ?? 0.0);

        if (($momentum !== null && $momentum <= -0.15) || ($yoy !== null && $yoy <= -0.15)) {
            $labels[] = 'DECLINING';
            $evidence[] = ['metric' => '3M Momentum', 'value' => $momentum, 'source' => 'INTERNAL'];
            $evidence[] = ['metric' => 'YoY Growth', 'value' => $yoy, 'source' => 'INTERNAL'];
            $inferences[] = [
                'text' => 'Penurunan terjadi cukup konsisten untuk diperlakukan sebagai masalah demand/coverage yang perlu intervensi, bukan sekadar fluktuasi satu periode.',
                'confidence' => $momentum !== null && $yoy !== null && $momentum < 0 && $yoy < 0 ? 'HIGH' : 'MEDIUM',
            ];
        } elseif (($momentum !== null && $momentum >= 0.15) || ($yoy !== null && $yoy >= 0.15)) {
            $labels[] = $previous3m <= 0.0 && $sales3m > 0.0 ? 'NEW DEMAND' : 'HEALTHY';
        } else {
            $labels[] = 'HEALTHY';
        }

        if ($sales3m <= 0.0 && $previous3m > 0.0) {
            $labels[] = 'LOST DEMAND';
            $evidence[] = ['metric' => 'Sales 3M', 'value' => $sales3m, 'source' => 'INTERNAL'];
        }

        if ($onlineShare !== null && $onlineShare < 0.20 && $onlineYoy !== null && $onlineYoy > 0.05) {
            $labels[] = 'CHANNEL GAP';
            $evidence[] = ['metric' => 'Online Share', 'value' => $onlineShare, 'source' => 'INTERNAL'];
            $evidence[] = ['metric' => 'Online YoY Growth', 'value' => $onlineYoy, 'source' => 'INTERNAL'];
            $inferences[] = [
                'text' => 'Kontribusi online masih rendah sementara pertumbuhannya positif; ini menunjukkan ruang ekspansi channel online layak diuji.',
                'confidence' => 'MEDIUM',
            ];
        }

        if ($top5 !== null && $top5 >= 0.55) {
            $labels[] = 'CUSTOMER CONCENTRATION RISK';
            $evidence[] = ['metric' => 'Top 5 Customer Share', 'value' => $top5, 'source' => 'INTERNAL'];
        }

        if ($cv !== null && $cv >= 0.45) {
            $labels[] = 'VOLATILE';
            $evidence[] = ['metric' => 'Sales Volatility CV', 'value' => $cv, 'source' => 'INTERNAL'];
        }

        if ($previousActive > 0 && $active < (int) floor($previousActive * 0.80)) {
            $evidence[] = ['metric' => 'Active Buyers', 'value' => $active, 'comparison' => $previousActive, 'source' => 'INTERNAL'];
            $inferences[] = [
                'text' => 'Jumlah pembeli aktif turun material; recovery customer dan perluasan buyer pool sebaiknya menjadi salah satu fokus eksperimen.',
                'confidence' => 'HIGH',
            ];
        }

        $labels = array_values(array_unique($labels));
        $priority = ['LOST DEMAND', 'DECLINING', 'CHANNEL GAP', 'CUSTOMER CONCENTRATION RISK', 'VOLATILE', 'NEW DEMAND', 'HEALTHY'];
        $primary = 'HEALTHY';
        foreach ($priority as $candidate) {
            if (in_array($candidate, $labels, true)) {
                $primary = $candidate;
                break;
            }
        }

        return ['labels' => $labels, 'facts' => $evidence, 'inferences' => $inferences, 'primary' => $primary];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
