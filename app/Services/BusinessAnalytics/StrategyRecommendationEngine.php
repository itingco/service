<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class StrategyRecommendationEngine
{
    /** @param array<string,mixed> $metrics @return array<string,mixed> */
    public function build(array $metrics): array
    {
        $online = (array) ($metrics['online'] ?? []);
        $offline = (array) ($metrics['offline'] ?? []);
        $customer = (array) ($metrics['customer'] ?? []);
        $product = (array) ($metrics['product'] ?? []);
        $forecast = (array) ($metrics['forecast'] ?? []);

        $growthScore = $this->scoreGrowth($metrics['yoy_growth'] ?? null, $metrics['momentum_3m'] ?? null);
        $channelScore = $this->scoreChannels($online, $offline);
        $customerScore = $this->scoreCustomer($customer, (float) ($metrics['current_sales'] ?? 0.0));
        $productScore = $this->scoreProduct($product, (float) ($metrics['current_sales'] ?? 0.0));
        $forecastScore = $this->scoreForecast($forecast);
        $health = (int) round(($growthScore * .25) + ($channelScore * .20) + ($customerScore * .20) + ($productScore * .15) + ($forecastScore * .20));

        $actions = [];
        $risks = [];
        $opportunities = [];

        $onlineYoY = $this->num($online['yoy_growth'] ?? null);
        $onlineMoM = $this->num($online['mom_growth'] ?? null);
        $onlineShare = $this->num($online['share'] ?? null);
        if ($onlineYoY !== null && $onlineYoY > 0.08) {
            $opportunities[] = [
                'title' => 'Momentum online positif',
                'impact' => 'HIGH',
                'text' => sprintf('Channel online tumbuh %.1f%% YoY dengan share %.1f%%.', $onlineYoY * 100, ($onlineShare ?? 0) * 100),
            ];
            $actions[] = $this->action(
                priority: $onlineMoM !== null && $onlineMoM < 0 ? 'TINGGI' : 'SEDANG',
                channel: 'ONLINE',
                title: 'Scale promo online yang sudah punya demand',
                hypothesis: 'Demand tahunan online masih kuat; promo terukur pada item/customer yang sudah tumbuh dapat memulihkan momentum bulanan tanpa menyebar diskon ke seluruh katalog.',
                evidence: [
                    sprintf('Growth online YoY %.1f%%', $onlineYoY * 100),
                    sprintf('Share online %.1f%%', ($onlineShare ?? 0) * 100),
                    $onlineMoM !== null ? sprintf('Momentum online MoM %.1f%%', $onlineMoM * 100) : 'Momentum MoM belum tersedia',
                ],
                action: 'Jalankan campaign 7–14 hari pada top growing items online: voucher minimum belanja + bundle/add-on, bukan diskon blanket.',
                kpi: 'Revenue online per day + conversion proxy (invoice count) + average order value',
                duration: 14,
                threshold: 'Revenue online harian naik ≥ 10% vs baseline 4 minggu dan AOV tidak turun > 5%',
                stopRule: 'Hentikan/ubah offer bila setelah 7 hari uplift revenue < 3% atau AOV turun > 10%',
            );
        } elseif ($onlineShare !== null) {
            $actions[] = $this->action(
                priority: 'TINGGI',
                channel: 'ONLINE',
                title: 'Bangun demand online dengan acquisition test',
                hypothesis: 'Share online masih dapat ditingkatkan dengan kampanye akuisisi terarah pada produk yang sudah terbukti laku offline.',
                evidence: [
                    sprintf('Share online %.1f%%', $onlineShare * 100),
                    $onlineYoY !== null ? sprintf('Growth online YoY %.1f%%', $onlineYoY * 100) : 'Growth online belum stabil',
                ],
                action: 'Pilih 10–20 SKU offline terkuat yang cocok dikirim, buat campaign online khusus dengan voucher first-order/minimum basket dan retargeting customer dormant.',
                kpi: 'New/returning customer sales online + revenue per campaign period',
                duration: 14,
                threshold: 'Sales online campaign naik ≥ 12% vs baseline dan minimal 20% uplift berasal dari customer baru/reaktivasi',
                stopRule: 'Stop SKU/offer bila 7 hari tidak menghasilkan uplift ≥ 5%',
            );
        }

        $offlineYoY = $this->num($offline['yoy_growth'] ?? null);
        if ($offlineYoY !== null && $offlineYoY < -0.03) {
            $risks[] = [
                'title' => 'Offline melemah YoY',
                'impact' => abs($offlineYoY) >= .10 ? 'HIGH' : 'MEDIUM',
                'text' => sprintf('Channel offline turun %.1f%% YoY dan perlu recovery berbasis customer.', abs($offlineYoY) * 100),
            ];
            $actions[] = $this->action(
                priority: abs($offlineYoY) >= .10 ? 'TINGGI' : 'SEDANG',
                channel: 'OFFLINE',
                title: 'Recovery offline berbasis account',
                hypothesis: 'Penurunan offline lebih efektif dipulihkan lewat customer yang sudah pernah membeli daripada promo massal.',
                evidence: [
                    sprintf('Growth offline YoY %.1f%%', $offlineYoY * 100),
                    sprintf('Share offline %.1f%%', ($this->num($offline['share'] ?? null) ?? 0) * 100),
                ],
                action: 'Buat daftar 20 customer offline dengan penurunan nominal terbesar; sales wajib melakukan recovery call/visit dengan offer yang relevan terhadap histori item mereka.',
                kpi: 'Recovered revenue + reactivated customers + sales per contacted account',
                duration: 21,
                threshold: 'Pulihkan ≥ 15% lost revenue dari cohort target dalam 21 hari',
                stopRule: 'Ubah segment/offer bila 10 hari contact-to-order rate < 8%',
            );
        }

        $lost = (float) ($customer['lost_sales'] ?? 0.0);
        $new = (float) ($customer['new_sales'] ?? 0.0);
        if ($lost > max(50_000_000.0, $new * 1.15)) {
            $risks[] = [
                'title' => 'Lost customer revenue lebih besar dari new revenue',
                'impact' => 'HIGH',
                'text' => sprintf('Lost revenue Rp %s vs new revenue Rp %s.', $this->money($lost), $this->money($new)),
            ];
            $actions[] = $this->action(
                priority: 'TINGGI',
                channel: 'OMNICHANNEL',
                title: 'Reaktivasi customer hilang',
                hypothesis: 'Cohort customer yang hilang memiliki probabilitas pembelian lebih tinggi daripada cold acquisition karena sudah punya histori transaksi.',
                evidence: [
                    'Lost customer revenue Rp '.$this->money($lost),
                    'New customer revenue Rp '.$this->money($new),
                ],
                action: 'Pisahkan lost customer berdasarkan online/offline dan nilai historis; tawarkan reactivation voucher/price support hanya pada cohort bernilai tinggi dengan expiry 14 hari.',
                kpi: 'Reactivation rate + recovered revenue + average reactivated order',
                duration: 14,
                threshold: 'Reactivation rate ≥ 10% atau recovered revenue ≥ 12% dari lost revenue cohort',
                stopRule: 'Stop offer bila margin/otorisasi harga tidak aman atau reactivation rate < 4% setelah 7 hari',
            );
        }

        $decliningProducts = (float) ($product['declining_sales'] ?? 0.0);
        $growingProducts = (float) ($product['growing_sales'] ?? 0.0);
        if ($decliningProducts > $growingProducts) {
            $risks[] = [
                'title' => 'Produk menurun mendominasi growth mix',
                'impact' => 'MEDIUM',
                'text' => sprintf('Sales dari produk declining Rp %s lebih besar dari growing Rp %s.', $this->money($decliningProducts), $this->money($growingProducts)),
            ];
            $actions[] = $this->action(
                priority: 'SEDANG',
                channel: 'OMNICHANNEL',
                title: 'Bundle growing item + declining item',
                hypothesis: 'Produk declining yang masih punya demand historis dapat diuji sebagai add-on/bundle ke produk growing tanpa mengorbankan harga item utama.',
                evidence: [
                    'Declining product sales Rp '.$this->money($decliningProducts),
                    'Growing product sales Rp '.$this->money($growingProducts),
                ],
                action: 'Pilih pairing berdasarkan customer overlap: item growing sebagai anchor, item declining sebagai add-on terbatas. Pisahkan hasil online dan offline.',
                kpi: 'Bundle attach rate + incremental revenue + AOV',
                duration: 14,
                threshold: 'Attach rate ≥ 8% dan incremental revenue cohort ≥ 7% vs baseline',
                stopRule: 'Stop bundle bila anchor item sales turun > 5% atau attach rate < 3% setelah 7 hari',
            );
        }

        $forecastVsCurrent = $this->num($forecast['m1_vs_current'] ?? null);
        if ($forecastVsCurrent !== null && $forecastVsCurrent < -0.08) {
            $risks[] = [
                'title' => 'Forecast bulan depan melemah',
                'impact' => abs($forecastVsCurrent) >= .15 ? 'HIGH' : 'MEDIUM',
                'text' => sprintf('Forecast M+1 %.1f%% di bawah actual bulan terpilih.', abs($forecastVsCurrent) * 100),
            ];
            $actions[] = $this->action(
                priority: 'TINGGI',
                channel: 'OMNICHANNEL',
                title: 'Pre-emptive pipeline push sebelum bulan turun',
                hypothesis: 'Risiko forecast dapat dikurangi bila gap dipecah menjadi target per channel/customer sebelum bulan dimulai.',
                evidence: [
                    sprintf('Forecast M+1 %.1f%% vs current', $forecastVsCurrent * 100),
                    sprintf('Forecast confidence %.1f%%', ($this->num($forecast['confidence'] ?? null) ?? 0) * 100),
                ],
                action: 'Pecah gap forecast menjadi target recovery online/offline dan assign top accounts/SKU ke owner sebelum minggu pertama.',
                kpi: 'Booked/realized revenue vs weekly recovery target',
                duration: 28,
                threshold: 'Week-1 pace ≥ 23% dari target bulanan dan week-2 cumulative ≥ 48%',
                stopRule: 'Jika week-1 pace < 18%, eskalasi offer/channel mix dan fokus ke top revenue-at-risk accounts',
            );
        }

        usort($actions, static fn(array $a, array $b): int => self::priorityRank($b['priority']) <=> self::priorityRank($a['priority']));

        return [
            'health_score' => max(0, min(100, $health)),
            'health_label' => $health >= 80 ? 'STRONG' : ($health >= 65 ? 'MODERATE' : ($health >= 50 ? 'WATCH' : 'AT RISK')),
            'scores' => [
                'growth' => $growthScore,
                'channel' => $channelScore,
                'customer' => $customerScore,
                'product' => $productScore,
                'forecast' => $forecastScore,
            ],
            'actions' => array_slice($actions, 0, 8),
            'risks' => array_slice($risks, 0, 6),
            'opportunities' => array_slice($opportunities, 0, 6),
        ];
    }

    /** @return array<string,mixed> */
    private function action(string $priority, string $channel, string $title, string $hypothesis, array $evidence, string $action, string $kpi, int $duration, string $threshold, string $stopRule): array
    {
        return [
            'priority' => $priority,
            'channel' => $channel,
            'title' => $title,
            'hypothesis' => $hypothesis,
            'evidence' => $evidence,
            'action' => $action,
            'test' => [
                'kpi' => $kpi,
                'duration_days' => $duration,
                'success_threshold' => $threshold,
                'stop_rule' => $stopRule,
            ],
        ];
    }

    private function scoreGrowth(mixed $yoy, mixed $momentum): int
    {
        $score = 65.0;
        foreach ([[$this->num($yoy), 120.0], [$this->num($momentum), 80.0]] as [$value, $weight]) {
            if ($value !== null) $score += max(-25.0, min(25.0, $value * $weight));
        }
        return (int) round(max(0, min(100, $score)));
    }

    private function scoreChannels(array $online, array $offline): int
    {
        $weighted = 0.0; $weight = 0.0;
        foreach ([$online, $offline] as $row) {
            $share = max(0.0, $this->num($row['share'] ?? null) ?? 0.0);
            $growth = $this->num($row['yoy_growth'] ?? null);
            if ($share > 0 && $growth !== null) {
                $weighted += $share * max(0.0, min(100.0, 65.0 + $growth * 120.0));
                $weight += $share;
            }
        }
        return $weight > 0 ? (int) round($weighted / $weight) : 60;
    }

    private function scoreCustomer(array $c, float $current): int
    {
        if ($current <= 0) return 50;
        $lost = (float) ($c['lost_sales'] ?? 0); $new = (float) ($c['new_sales'] ?? 0);
        $declining = (float) ($c['declining_sales'] ?? 0); $top5 = (float) ($c['top5_share'] ?? 0);
        $score = 75 + (($new - $lost) / $current * 80) - ($declining / $current * 20) - max(0, $top5 - .45) * 40;
        return (int) round(max(0, min(100, $score)));
    }

    private function scoreProduct(array $p, float $current): int
    {
        if ($current <= 0) return 55;
        $growing = (float) ($p['growing_sales'] ?? 0); $declining = (float) ($p['declining_sales'] ?? 0); $new = (float) ($p['new_demand_sales'] ?? 0);
        $score = 65 + (($growing + $new - $declining) / $current * 45);
        return (int) round(max(0, min(100, $score)));
    }

    private function scoreForecast(array $f): int
    {
        $confidence = $this->num($f['confidence'] ?? null) ?? .5;
        $vsCurrent = $this->num($f['m1_vs_current'] ?? null) ?? 0.0;
        $score = ($confidence * 85) + 15 + min(0.0, $vsCurrent) * 70;
        return (int) round(max(0, min(100, $score)));
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private static function priorityRank(string $priority): int
    {
        return match ($priority) { 'TINGGI' => 3, 'SEDANG' => 2, default => 1 };
    }
}
