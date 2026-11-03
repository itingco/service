<?php

declare(strict_types=1);

require_once __DIR__.'/../../../app/Services/BusinessAnalytics/AdvisorPlainLanguageService.php';

use App\Services\BusinessAnalytics\AdvisorPlainLanguageService;

function check(bool $ok): void { if (!$ok) { throw new RuntimeException('Assertion failed at '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line']); } }

$svc = new AdvisorPlainLanguageService();

$market = $svc->market([[
    'segment' => 'Specialized Retailer / Reseller',
    'fit_score' => 70,
    'preferred_channel' => 'ONLINE',
    'why' => 'Reseller yang fokus pada kategori produk dapat mempercepat coverage pasar.',
    'buyer_problem' => 'Reseller yang fokus pada kategori produk dapat mempercepat coverage pasar.',
    'selling_angle' => 'Tekankan manfaat yang paling relevan dengan pekerjaan segmen ini, lalu bandingkan hasilnya dengan baseline.',
    'first_test' => 'Uji outreach terarah ke 10–20 prospect/account selama 14 hari dan ukur response serta revenue/quantity aktual.',
]]);

check(($market[0]['segment_display'] ?? '') === 'Toko / Reseller Spesialis');
check(str_contains((string)$market[0]['reason_simple'], 'jangkauan'));
check(!str_contains(strtolower((string)$market[0]['reason_simple']), 'coverage'));
check(!str_contains(strtolower((string)$market[0]['offer_simple']), 'baseline'));
check(str_contains((string)$market[0]['action_simple'], '10–20'));
check(str_contains(strtolower((string)$market[0]['action_simple']), 'customer'));
check(strlen((string)$market[0]['action_simple']) <= 135);

$strategy = $svc->strategy(['strategies' => [[
    'title' => 'Feature-led online growth test',
    'channel' => 'ONLINE',
    'priority' => 'HIGH',
    'hypothesis' => 'Jika exposure online dinaikkan dengan angle manfaat produk yang spesifik dan offer yang tidak merusak AOV, revenue/day online dapat tumbuh dari baseline.',
    'target_audience' => 'Professional buyer / marketplace tool shopper',
    'offer' => 'Uji bundle/free accessory atau voucher minimum purchase; prioritaskan fitur pembeda produk, bukan diskon besar.',
    'duration_days' => 14,
    'kpis' => ['revenue/day', 'quantity/day', 'active buyers', 'online share'],
    'success_threshold' => ['revenue_uplift_min' => .15, 'quantity_uplift_min' => .10],
    'stop_rule' => 'Hari ke-7: jika uplift revenue/day <3% atau quantity/day tidak bergerak, ubah audience/angle/offer sebelum menambah budget.',
    'evidence' => ['Online share 11%'],
]]]);

$s = $strategy['strategies'][0];
check($s['title_display'] === 'Dorong Penjualan Online');
check(str_contains($s['measure_simple'], 'Penjualan/hari'));
check(str_contains($s['success_simple'], '15%'));
check(str_contains($s['success_simple'], '10%'));
check(!str_contains(strtolower($s['reason_simple']), 'exposure'));
check(!str_contains(strtolower($s['stop_simple']), 'uplift'));
check(strlen($s['reason_simple']) <= 145);

$response = $svc->response([
    'summary' => 'Gunakan controlled demand discovery sebelum scale.',
    'facts' => [['text' => 'Online share rendah', 'source' => 'INTERNAL']],
    'inferences' => [['text' => 'Hal ini mengindikasikan penetration channel masih rendah.', 'confidence' => 'MEDIUM']],
    'market_opportunities' => [],
    'recommendations' => [[
        'title' => 'Feature-led online growth test',
        'channel' => 'ONLINE',
        'why' => 'Exposure online masih rendah.',
        'action' => 'Lakukan outreach ke prospect/account.',
        'priority' => 'HIGH',
    ]],
    'experiments' => [[
        'title' => 'Feature-led online growth test',
        'hypothesis' => 'Exposure naik akan meningkatkan demand.',
        'target_audience' => 'Professional buyer',
        'channel' => 'ONLINE',
        'offer' => 'Voucher minimum purchase',
        'duration_days' => 14,
        'kpis' => ['revenue/day'],
        'success_rule' => 'Revenue uplift >= 10%',
        'stop_rule' => 'Stop jika uplift < 3%',
    ]],
    'risks' => [],
    'missing_information' => [],
    'external_sources' => [],
]);
check(!str_contains(strtolower($response['summary']), 'scale'));
check(!str_contains(strtolower($response['inferences'][0]['text']), 'penetration'));
check($response['recommendations'][0]['title'] === 'Dorong Penjualan Online');
check(!str_contains(strtolower($response['recommendations'][0]['action']), 'outreach'));
check(!str_contains(strtolower($response['experiments'][0]['success_rule']), 'uplift'));

echo "PASS: Advisor plain-language output\n";
