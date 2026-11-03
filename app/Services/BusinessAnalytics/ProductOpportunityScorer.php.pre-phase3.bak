<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class ProductOpportunityScorer
{
    /** @param array<string,mixed> $profile @return list<array<string,mixed>> */
    public function heuristicSegments(array $profile): array
    {
        $text = strtolower((string)(($profile['item_name'] ?? '').' '.($profile['specifications'] ?? '').' '.($profile['use_cases'] ?? '').' '.($profile['advantages'] ?? '')));
        $segments = [];
        $add = static function(array &$rows, string $segment, float $hint, string $why): void {
            $rows[] = [
                'segment'=>$segment,
                'fit_hint'=>$hint,
                'why'=>$why,
                'buyer_problem'=>$why,
                'selling_angle'=>'Tekankan manfaat yang paling relevan dengan pekerjaan segmen ini, lalu bandingkan hasilnya dengan baseline.',
                'first_test'=>'Uji outreach terarah ke 10–20 prospect/account selama 14 hari dan ukur response serta revenue/quantity aktual.',
                'preferred_channel'=>preg_match('/marketplace|online|seller/i',$segment) ? 'ONLINE' : 'OFFLINE',
                'source_count'=>0,
                'source_urls'=>[],
                'evidence_type'=>'HEURISTIC',
            ];
        };
        if (preg_match('/drill|impact|rotary|grinder|saw|cordless|brushless|power tool|bor|gerinda/', $text)) {
            $add($segments,'Contractor Interior',0.90,'Pekerjaan instalasi, fastening, drilling, fitting, ceiling, cabinet, dan partition sering membutuhkan power tools portable.');
            $add($segments,'MEP Contractor',0.86,'Instalasi mechanical/electrical/plumbing membutuhkan alat portable untuk pemasangan dan maintenance di lokasi proyek.');
            $add($segments,'Industrial Maintenance',0.84,'Tim maintenance membutuhkan alat yang cepat dipindahkan untuk repair dan preventive maintenance.');
            $add($segments,'Fabrication / Workshop',0.80,'Workshop dan fabrication memiliki use-case drilling, fastening, cutting, atau repair yang berulang.');
            $add($segments,'Hardware / Tool Retailer',0.78,'Retailer tools dapat menjangkau professional user dan menjadi channel repeat/reorder.');
            $add($segments,'Facility Management',0.74,'Facility management memiliki kebutuhan maintenance gedung dan pekerjaan perbaikan rutin.');
            $add($segments,'Marketplace Professional Tool Seller',0.72,'Seller khusus tools dapat memperluas reach online ke professional buyer.');
        } else {
            $add($segments,'Specialized Retailer / Reseller',0.72,'Reseller yang fokus pada kategori produk dapat mempercepat coverage pasar.');
            $add($segments,'Professional End User',0.70,'Buyer profesional yang memiliki use-case langsung biasanya lebih mudah diuji melalui value proposition berbasis manfaat.');
            $add($segments,'Project / Procurement Buyer',0.66,'Project procurement relevan jika produk digunakan berulang atau dibutuhkan dalam jumlah tertentu.');
            $add($segments,'Online Category Seller',0.64,'Seller online kategori terkait dapat digunakan untuk menguji demand digital secara terukur.');
        }
        return $segments;
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $channelFacts
     * @param list<array<string,mixed>> $externalSegments
     * @return list<array<string,mixed>>
     */
    public function rank(array $profile, array $channelFacts, array $externalSegments): array
    {
        $spec = strtolower(trim((string) (($profile['specifications'] ?? '').' '.($profile['use_cases'] ?? '').' '.($profile['advantages'] ?? ''))));
        $onlineShare = (float) ($channelFacts['online_share'] ?? 0.0);
        $onlineGrowth = (float) ($channelFacts['online_yoy_growth'] ?? 0.0);
        $rows = [];

        foreach ($externalSegments as $segment) {
            $name = trim((string) ($segment['segment'] ?? ''));
            if ($name === '') {
                continue;
            }
            $hint = max(0.0, min(1.0, (float) ($segment['fit_hint'] ?? 0.5)));
            $sources = max(0, (int) ($segment['source_count'] ?? 0));
            $why = trim((string) ($segment['why'] ?? ''));
            $buyerProblem = trim((string) ($segment['buyer_problem'] ?? $why));
            $sellingAngle = trim((string) ($segment['selling_angle'] ?? ''));
            $firstTest = trim((string) ($segment['first_test'] ?? ''));
            $preferredChannel = strtoupper(trim((string) ($segment['preferred_channel'] ?? '')));

            $score = 45.0 + ($hint * 35.0) + min(10.0, $sources * 3.0);
            $nameLower = strtolower($name);
            $evidence = [];
            if ($why !== '') {
                $evidence[] = ['type' => (($segment['evidence_type'] ?? '') === 'HEURISTIC' ? 'HEURISTIC' : 'EXTERNAL'), 'text' => $why];
            }
            if ($spec !== '') {
                $evidence[] = ['type' => 'PRODUCT', 'text' => substr((string) ($profile['specifications'] ?? $profile['use_cases'] ?? ''), 0, 180)];
            }

            if ($onlineShare < 0.20 && $onlineGrowth > 0.05 && preg_match('/market|retail|seller|consumer|diy|e-?commerce|online/', $nameLower)) {
                $score += 8.0;
                $evidence[] = ['type' => 'INTERNAL', 'text' => 'Online share rendah tetapi online growth positif.'];
            }
            if (preg_match('/brushless|heavy duty|industrial|professional|20v|cordless/', $spec) && preg_match('/contractor|maintenance|workshop|industrial|fabrication|mep|facility/', $nameLower)) {
                $score += 7.0;
                $evidence[] = ['type' => 'PRODUCT', 'text' => 'Spesifikasi mengindikasikan positioning professional/heavy-duty.'];
            }

            if ($evidence === []) {
                continue;
            }

            $score = (int) round(max(0.0, min(100.0, $score)));
            $rows[] = [
                'segment' => $name,
                'fit_score' => $score,
                'why' => $why !== '' ? $why : 'Kesesuaian berasal dari kombinasi karakteristik produk dan pola channel.',
                'evidence' => $evidence,
                'confidence' => $sources >= 2 ? 'HIGH' : ($sources === 1 ? 'MEDIUM' : 'LOW'),
                'preferred_channel' => in_array($preferredChannel, ['ONLINE','OFFLINE','OMNICHANNEL'], true)
                    ? $preferredChannel
                    : (preg_match('/market|seller|consumer|diy|online/', $nameLower) ? 'ONLINE' : 'OFFLINE'),
                'buyer_problem' => $buyerProblem !== '' ? $buyerProblem : $why,
                'selling_angle' => $sellingAngle !== '' ? $sellingAngle : 'Tekankan manfaat produk yang langsung menjawab use-case segmen ini.',
                'first_test' => $firstTest !== '' ? $firstTest : 'Uji 10–20 account/prospect selama 14 hari dan ukur response, revenue/day, serta quantity/day.',
                'source_urls' => array_values(array_filter((array) ($segment['source_urls'] ?? []), static fn (mixed $url): bool => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false)),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['fit_score'] <=> $a['fit_score']);
        return array_slice($rows, 0, 12);
    }
}
