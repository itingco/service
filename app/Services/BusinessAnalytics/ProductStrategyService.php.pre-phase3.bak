<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class ProductStrategyService
{
    /** @param array<string,mixed> $facts @return array<string,mixed> */
    public function build(array $facts, array $marketOpportunities = [], array $memory = []): array
    {
        $diagnosis = (array) ($facts['diagnosis'] ?? []);
        $labels = (array) ($diagnosis['labels'] ?? []);
        $onlineShare = (float) ($facts['online_share'] ?? 0.0);
        $onlineGrowth = $facts['online_yoy_growth'] ?? null;
        $lostRevenue = (float) ($facts['lost_revenue'] ?? 0.0);
        $crossSell = (array) ($facts['cross_sell_candidates'] ?? []);
        $related = (array) ($facts['related_items'] ?? []);
        $strategies = [];

        if (in_array('CHANNEL GAP', $labels, true) || ($onlineShare < 0.20 && ($onlineGrowth ?? 0.0) > 0.0)) {
            $strategies[] = $this->strategy(
                priority: 'HIGH', channel: 'ONLINE', title: 'Feature-led online growth test',
                hypothesis: 'Jika exposure online dinaikkan dengan angle manfaat produk yang spesifik dan offer yang tidak merusak AOV, revenue/day online dapat tumbuh dari baseline.',
                audience: $marketOpportunities[0]['segment'] ?? 'Professional buyer / marketplace tool shopper',
                offer: 'Uji bundle/free accessory atau voucher minimum purchase; prioritaskan fitur pembeda produk, bukan diskon besar.',
                duration: 14,
                kpis: ['revenue/day', 'quantity/day', 'active buyers', 'online share'],
                success: ['revenue_uplift_min' => 0.15, 'quantity_uplift_min' => 0.10],
                stop: 'Hari ke-7: jika uplift revenue/day <3% atau quantity/day tidak bergerak, ubah audience/angle/offer sebelum menambah budget.',
                evidence: [
                    'Online share '.round($onlineShare * 100, 1).'%',
                    'Online YoY '.($onlineGrowth === null ? 'N/A' : round((float)$onlineGrowth * 100, 1).'%'),
                ],
            );
        }

        if ($lostRevenue > 0.0) {
            $strategies[] = $this->strategy(
                priority: 'HIGH', channel: 'OFFLINE', title: 'Lost-customer recovery sprint',
                hypothesis: 'Sebagian penurunan bisa dipulihkan lebih cepat dari customer yang sebelumnya sudah membeli dibanding mencari customer baru sepenuhnya.',
                audience: 'Customer yang membeli produk pada periode sebelumnya tetapi tidak aktif 3 bulan terakhir',
                offer: 'Sales call/visit terarah; gunakan histori pembelian untuk penawaran reactivation, project price, bundle, atau demo bila relevan.',
                duration: 21,
                kpis: ['reactivated buyers', 'recovered revenue', 'revenue/day', 'quantity/day'],
                success: ['revenue_uplift_min' => 0.12, 'quantity_uplift_min' => 0.05],
                stop: 'Hari ke-14: jika tidak ada reaktivasi atau recovered revenue <5% dari lost revenue cohort, revisi offer/segment.',
                evidence: ['Lost revenue 3M sekitar '.number_format($lostRevenue,0,',','.')],
            );
        }

        if ($crossSell !== []) {
            $top = array_slice($crossSell,0,20);
            $strategies[] = $this->strategy(
                priority: 'HIGH', channel: 'OFFLINE', title: 'Cross-sell lookalike account test',
                hypothesis: 'Customer aktif yang membeli item terkait tetapi belum membeli produk terpilih memiliki friction akuisisi lebih rendah.',
                audience: count($top).' customer cross-sell score tertinggi',
                offer: 'Berikan sales target list dengan alasan kecocokan dan item terkait yang sudah dibeli; gunakan demo/bundle yang relevan.',
                duration: 21,
                kpis: ['target accounts converted', 'revenue/day', 'quantity/day'],
                success: ['revenue_uplift_min' => 0.10, 'quantity_uplift_min' => 0.08],
                stop: 'Jika <10% target account merespons/bertransaksi setelah 14 hari, ubah cohort atau selling angle.',
                evidence: ['Ada '.count($crossSell).' kandidat cross-sell yang belum membeli item terpilih.'],
            );
        }

        if ($related !== []) {
            $strategies[] = $this->strategy(
                priority: 'MEDIUM', channel: 'OMNICHANNEL', title: 'Related-item bundle experiment',
                hypothesis: 'Produk yang sering muncul di basket customer yang sama dapat menjadi anchor/add-on untuk memperbesar penetration produk terpilih.',
                audience: 'Existing buyers dari item terkait dan customer baru dengan use-case yang sama',
                offer: 'Uji 1 anchor item + selected item atau selected item + accessory; bandingkan bundle dengan diskon standalone.',
                duration: 14,
                kpis: ['revenue/day', 'quantity/day', 'bundle take-up (manual jika belum terekam)'],
                success: ['revenue_uplift_min' => 0.10, 'quantity_uplift_min' => 0.10],
                stop: 'Stop/modify bila revenue/day tidak naik atau penjualan anchor melemah material.',
                evidence: ['Related item teratas: '.(($related[0]['item_name'] ?? $related[0]['item_code'] ?? 'N/A'))],
            );
        }

        if ($memory !== []) {
            $successful = array_values(array_filter($memory, static fn(array $m): bool => ($m['decision'] ?? '') === 'SCALE'));
            if (count($successful) >= 2) {
                $strategies[] = [
                    'priority' => 'MEDIUM', 'channel' => 'LEARNING', 'title' => 'Reuse company-proven playbook',
                    'hypothesis' => 'Strategi dengan hasil SCALE sebelumnya layak diprioritaskan sebagai starting point, lalu diuji ulang pada cohort sekarang.',
                    'target_audience' => 'Cohort yang menyerupai experiment sebelumnya',
                    'offer' => 'Gunakan mekanisme experiment terbaik sebelumnya tanpa menganggap hasil lama pasti terulang.',
                    'duration_days' => 14, 'kpis' => ['revenue/day','quantity/day'],
                    'success_threshold' => ['revenue_uplift_min'=>0.10,'quantity_uplift_min'=>0.05],
                    'stop_rule' => 'Jika hasil berbeda negatif dari experiment sebelumnya pada check-point pertama, kembali ke MODIFY.',
                    'evidence' => ['Ada '.count($successful).' experiment sebelumnya dengan decision SCALE.'],
                ];
            }
        }

        if ($strategies === []) {
            $strategies[] = $this->strategy(
                priority: 'MEDIUM', channel: 'OMNICHANNEL', title: 'Controlled demand discovery test',
                hypothesis: 'Jika masalah belum jelas, eksperimen kecil pada audience dan offer berbeda lebih aman daripada promo massal.',
                audience: '2–3 cohort customer / market segment yang paling relevan',
                offer: 'Tes angle/offer kecil tanpa diskon besar; ukur per cohort sebelum scale.', duration: 14,
                kpis: ['revenue/day','quantity/day','active buyers'], success: ['revenue_uplift_min'=>0.10,'quantity_uplift_min'=>0.05],
                stop: 'Jangan scale jika tidak ada cohort yang melewati threshold.', evidence: ['Data belum memberi satu driver dominan.'],
            );
        }

        return ['strategies' => $strategies, 'quick_actions' => array_slice($strategies,0,3)];
    }

    /** @return array<string,mixed> */
    private function strategy(string $priority,string $channel,string $title,string $hypothesis,string $audience,string $offer,int $duration,array $kpis,array $success,string $stop,array $evidence): array
    {
        return ['priority'=>$priority,'channel'=>$channel,'title'=>$title,'hypothesis'=>$hypothesis,'target_audience'=>$audience,'offer'=>$offer,'duration_days'=>$duration,'kpis'=>$kpis,'success_threshold'=>$success,'stop_rule'=>$stop,'evidence'=>$evidence];
    }
}
