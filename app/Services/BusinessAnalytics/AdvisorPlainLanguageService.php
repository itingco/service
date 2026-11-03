<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class AdvisorPlainLanguageService
{
    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    public function market(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['segment_display'] = $this->segment((string) ($row['segment'] ?? '-'));
            $row['reason_simple'] = $this->simple((string) ($row['why'] ?? $row['buyer_problem'] ?? '-'), 115);
            $row['offer_simple'] = $this->simple((string) ($row['selling_angle'] ?? 'Tunjukkan manfaat utama produk untuk kebutuhan mereka.'), 115);
            $row['action_simple'] = $this->simple((string) ($row['first_test'] ?? 'Hubungi 10–20 calon customer selama 14 hari. Catat respons dan order.'), 135);
            $out[] = $row;
        }
        return $out;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function strategy(array $data): array
    {
        $rows = [];
        foreach ((array) ($data['strategies'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['title_display'] = $this->strategyTitle((string) ($row['title'] ?? 'Saran Tindakan'));
            $row['reason_simple'] = $this->simple((string) ($row['hypothesis'] ?? '-'), 145);
            $row['target_simple'] = $this->simple((string) ($row['target_audience'] ?? '-'), 110);
            $row['offer_simple'] = $this->simple((string) ($row['offer'] ?? '-'), 135);
            $row['measure_simple'] = implode(', ', array_map(fn (mixed $kpi): string => $this->kpi((string) $kpi), (array) ($row['kpis'] ?? [])));
            $row['success_simple'] = $this->success((array) ($row['success_threshold'] ?? []));
            $row['stop_simple'] = $this->simple((string) ($row['stop_rule'] ?? '-'), 140);
            $rows[] = $row;
        }
        $data['strategies'] = $rows;
        $data['quick_actions'] = array_slice($rows, 0, 3);
        return $data;
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    public function response(array $response): array
    {
        $response['summary'] = $this->simple((string) ($response['summary'] ?? ''), 180);

        foreach (['facts', 'risks'] as $key) {
            $response[$key] = array_values(array_map(function (mixed $row): mixed {
                if (!is_array($row)) return $row;
                $row['text'] = $this->simple((string) ($row['text'] ?? ''), 150);
                return $row;
            }, (array) ($response[$key] ?? [])));
        }

        $response['inferences'] = array_values(array_map(function (mixed $row): mixed {
            if (!is_array($row)) return $row;
            $row['text'] = $this->simple((string) ($row['text'] ?? ''), 150);
            return $row;
        }, (array) ($response['inferences'] ?? [])));

        $response['market_opportunities'] = $this->market((array) ($response['market_opportunities'] ?? []));

        $response['recommendations'] = array_values(array_map(function (mixed $row): mixed {
            if (!is_array($row)) return $row;
            $row['title'] = $this->strategyTitle((string) ($row['title'] ?? 'Saran Tindakan'));
            $row['why'] = $this->simple((string) ($row['why'] ?? ''), 120);
            $row['action'] = $this->simple((string) ($row['action'] ?? ''), 135);
            return $row;
        }, (array) ($response['recommendations'] ?? [])));

        $response['experiments'] = array_values(array_map(function (mixed $row): mixed {
            if (!is_array($row)) return $row;
            $row['title'] = $this->strategyTitle((string) ($row['title'] ?? 'Tes Penjualan'));
            $row['hypothesis'] = $this->simple((string) ($row['hypothesis'] ?? ''), 130);
            $row['target_audience'] = $this->simple((string) ($row['target_audience'] ?? ''), 100);
            $row['offer'] = $this->simple((string) ($row['offer'] ?? ''), 120);
            $row['kpis'] = array_values(array_map(fn (mixed $kpi): string => $this->kpi((string) $kpi), (array) ($row['kpis'] ?? [])));
            $row['success_rule'] = $this->simple((string) ($row['success_rule'] ?? ''), 120);
            $row['stop_rule'] = $this->simple((string) ($row['stop_rule'] ?? ''), 120);
            return $row;
        }, (array) ($response['experiments'] ?? [])));

        return $response;
    }

    public function simple(string $text, int $max = 140): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') return '-';

        $exact = [
            'Tekankan manfaat yang paling relevan dengan pekerjaan segmen ini, lalu bandingkan hasilnya dengan baseline.' => 'Tunjukkan manfaat utama produk untuk pekerjaan mereka.',
            'Uji outreach terarah ke 10–20 prospect/account selama 14 hari dan ukur response serta revenue/quantity aktual.' => 'Hubungi 10–20 calon customer selama 14 hari. Catat respons dan order.',
        ];
        if (isset($exact[$text])) return $exact[$text];

        $replace = [
            'coverage pasar' => 'jangkauan pasar',
            'coverage' => 'jangkauan',
            'market penetration' => 'jangkauan pasar',
            'penetration channel' => 'jangkauan channel',
            'penetration' => 'jangkauan',
            'exposure online' => 'promosi online',
            'exposure' => 'promosi',
            'selling angle' => 'cara menawarkan',
            'angle' => 'cara menawarkan',
            'value proposition' => 'manfaat produk',
            'outreach terarah' => 'hubungi langsung',
            'outreach' => 'hubungi',
            'prospect/account' => 'calon customer',
            'prospect' => 'calon customer',
            'account' => 'customer',
            'response' => 'respons',
            'baseline' => 'hasil sebelum test',
            'revenue uplift' => 'penjualan naik',
            'quantity uplift' => 'qty naik',
            'revenue/day' => 'penjualan per hari',
            'quantity/day' => 'qty per hari',
            'revenue' => 'penjualan',
            'uplift' => 'kenaikan',
            'cohort' => 'kelompok customer',
            'friction akuisisi' => 'hambatan penjualan',
            'demand discovery' => 'tes pasar',
            'controlled demand discovery' => 'tes pasar kecil',
            'scale' => 'perbesar',
            'modify' => 'ubah',
            'stop' => 'hentikan',
            'use-case' => 'kebutuhan',
            'professional buyer' => 'pembeli profesional',
            'marketplace tool shopper' => 'pembeli tools online',
            'free accessory' => 'bonus aksesoris',
            'minimum purchase' => 'minimum belanja',
            'AOV' => 'nilai belanja rata-rata',
        ];
        $text = str_ireplace(array_keys($replace), array_values($replace), $text);

        $text = preg_replace('/\bJika\s+promosi online dinaikkan dengan cara menawarkan manfaat produk yang spesifik dan offer yang tidak merusak nilai belanja rata-rata,\s*/iu', 'Jika promosi online lebih tepat, ', $text) ?? $text;
        $text = str_ireplace('offer', 'penawaran', $text);
        $text = str_ireplace('audience', 'target', $text);

        if ($this->length($text) <= $max) return $text;

        $slice = $this->substr($text, 0, $max);
        $lastPunctuation = max(
            (int) ($this->strrpos($slice, '.') ?: 0),
            (int) ($this->strrpos($slice, ';') ?: 0)
        );
        if ($lastPunctuation > (int) ($max * 0.55)) {
            return rtrim($this->substr($slice, 0, $lastPunctuation + 1));
        }
        $lastSpace = (int) ($this->strrpos($slice, ' ') ?: 0);
        if ($lastSpace > 0) $slice = $this->substr($slice, 0, $lastSpace);
        return rtrim($slice, " ,;:-").'.';
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function substr(string $value, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
        }
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }

    private function strrpos(string $haystack, string $needle): int|false
    {
        return function_exists('mb_strrpos') ? mb_strrpos($haystack, $needle) : strrpos($haystack, $needle);
    }

    private function segment(string $segment): string
    {
        return [
            'Specialized Retailer / Reseller' => 'Toko / Reseller Spesialis',
            'Professional End User' => 'Pemakai Profesional',
            'Project / Procurement Buyer' => 'Project / Procurement',
            'Online Category Seller' => 'Seller Online',
            'Contractor Interior' => 'Kontraktor Interior',
            'MEP Contractor' => 'Kontraktor MEP',
            'Industrial Maintenance' => 'Maintenance Industri',
            'Fabrication / Workshop' => 'Workshop / Fabrikasi',
            'Hardware / Tool Retailer' => 'Toko Perkakas / Reseller',
            'Facility Management' => 'Pengelola Gedung / Facility Management',
            'Marketplace Professional Tool Seller' => 'Seller Online Produk Profesional',
        ][$segment] ?? $segment;
    }

    private function strategyTitle(string $title): string
    {
        return [
            'Feature-led online growth test' => 'Dorong Penjualan Online',
            'Lost-customer recovery sprint' => 'Aktifkan Kembali Customer Lama',
            'Cross-sell lookalike account test' => 'Tawarkan ke Customer yang Potensial',
            'Related-item bundle experiment' => 'Coba Bundle Item Terkait',
            'Reuse company-proven playbook' => 'Ulangi Strategi yang Pernah Berhasil',
            'Controlled demand discovery test' => 'Tes Pasar Kecil',
        ][$title] ?? $this->simple($title, 70);
    }

    private function kpi(string $kpi): string
    {
        return [
            'revenue/day' => 'Penjualan/hari',
            'quantity/day' => 'Qty/hari',
            'active buyers' => 'Customer aktif',
            'online share' => 'Porsi online',
            'reactivated buyers' => 'Customer aktif kembali',
            'recovered revenue' => 'Penjualan yang kembali',
            'target accounts converted' => 'Customer yang order',
            'bundle take-up (manual jika belum terekam)' => 'Bundle terjual',
        ][$kpi] ?? $this->simple($kpi, 45);
    }

    /** @param array<string,mixed> $threshold */
    private function success(array $threshold): string
    {
        $parts = [];
        if (isset($threshold['revenue_uplift_min'])) {
            $parts[] = 'Penjualan naik ≥'.round((float) $threshold['revenue_uplift_min'] * 100).'%';
        }
        if (isset($threshold['quantity_uplift_min'])) {
            $parts[] = 'Qty naik ≥'.round((float) $threshold['quantity_uplift_min'] * 100).'%';
        }
        return $parts === [] ? 'Bandingkan hasil dengan sebelum test.' : implode(' dan ', $parts);
    }
}
