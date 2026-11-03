<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2937; }
        .title { font-size: 18px; font-weight: 700; color: #17365d; }
        .sub { color: #667085; }
        .section { font-size: 11px; font-weight: 700; margin: 11px 0 5px; color: #17365d; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #17365d; color: #fff; padding: 4px; text-align: left; }
        .table td { border-bottom: 1px solid #e5e7eb; padding: 4px; vertical-align: top; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .kpi, .box { border: 1px solid #d0d5dd; padding: 7px; }
        .kpi b { display: block; font-size: 11px; }
        .right { text-align: right; }
        .tag { font-size: 7px; border: 1px solid #d0d5dd; padding: 2px 4px; }
        .muted { color: #667085; }
        .page-break { page-break-before: always; }
        .small { font-size: 7.5px; }
    </style>
</head>
<body>
@php
    $money = fn ($v) => $presenter->money($v);
    $pct = fn ($v) => $presenter->percent($v);
    $num = fn ($v) => $presenter->number($v);
    $diag = $facts['diagnosis'] ?? [];
    $strategies = $strategy['strategies'] ?? [];
@endphp

<table width="100%">
    <tr>
        <td>
            <div class="title">Product &amp; Sales Strategy Advisor</div>
            <div class="sub">
                Review #{{ $review['id'] ?? '-' }} |
                {{ $review['source'] ?? 'ALL' }} |
                sampai {{ $review['review_period'] ?? '-' }} |
                Generated {{ now()->format('d-m-Y H:i') }}
            </div>
        </td>
        <td class="right">
            <b>{{ $facts['item_code'] ?? $review['item_code'] ?? '-' }}</b><br>
            {{ $facts['item_name'] ?? '-' }}
        </td>
    </tr>
</table>

<table class="grid">
    <tr>
        <td class="kpi">Sales 3M<b>{{ $money($facts['sales_3m'] ?? 0) }}</b></td>
        <td class="kpi">YoY 3M<b>{{ $pct($facts['yoy_growth'] ?? null) }}</b></td>
        <td class="kpi">Momentum 3M<b>{{ $pct($facts['momentum_3m'] ?? null) }}</b></td>
        <td class="kpi">Online Share<b>{{ $pct($facts['online_share'] ?? null) }}</b></td>
        <td class="kpi">Active Buyers<b>{{ $num($facts['active_buyers'] ?? 0) }}</b></td>
        <td class="kpi">Lost Revenue<b>{{ $money($facts['lost_revenue'] ?? 0) }}</b></td>
        <td class="kpi">Stock<b>{{ isset($profile['current_stock']) ? $num($profile['current_stock']) : '-' }}</b></td>
        <td class="kpi">Forecast Accuracy<b>{{ $pct($facts['forecast_backtest']['accuracy_score'] ?? null) }}</b></td>
    </tr>
</table>

<div class="section">Product Context</div>
<table class="table">
    <tbody>
    <tr>
        <td width="18%"><b>Spesifikasi</b></td>
        <td>{{ $profile['specifications'] ?? '-' }}</td>
        <td width="18%"><b>Kegunaan</b></td>
        <td>{{ $profile['use_cases'] ?? '-' }}</td>
    </tr>
    <tr>
        <td><b>Keunggulan</b></td>
        <td>{{ $profile['advantages'] ?? '-' }}</td>
        <td><b>Kompetitor</b></td>
        <td>{{ $profile['competitor_notes'] ?? '-' }}</td>
    </tr>
    </tbody>
</table>

<div class="section">Trend dan Forecast</div>
{!! $chartSvg !!}

<div class="section">Diagnosis</div>
@foreach (($diag['labels'] ?? []) as $label)
    <span class="tag">{{ $label }}</span>
@endforeach

<table class="grid">
    <tr>
        <td width="50%" valign="top">
            <b>FACT</b>
            @foreach (($diag['facts'] ?? []) as $fact)
                <div class="box">
                    <b>{{ $fact['metric'] ?? 'Metric' }}</b>:
                    {{ is_numeric($fact['value'] ?? null) ? number_format((float) $fact['value'], 4, ',', '.') : ($fact['value'] ?? '-') }}
                </div>
            @endforeach
        </td>
        <td width="50%" valign="top">
            <b>INFERENCE</b>
            @foreach (($diag['inferences'] ?? []) as $inference)
                <div class="box">
                    <span class="tag">{{ $inference['confidence'] ?? 'MEDIUM' }}</span>
                    {{ $inference['text'] ?? '' }}
                </div>
            @endforeach
        </td>
    </tr>
</table>

<div class="section">Peluang Pasar</div>
<table class="table">
    <thead>
    <tr>
        <th>Target</th>
        <th class="right">Skor</th>
        <th>Channel</th>
        <th>Kenapa Cocok</th>
        <th>Cara Menawarkan</th>
        <th>Aksi yang Dicoba</th>
    </tr>
    </thead>
    <tbody>
    @foreach (array_slice($market, 0, 15) as $row)
        <tr>
            <td><b>{{ $row['segment_display'] ?? $row['segment'] ?? '-' }}</b></td>
            <td class="right">{{ $row['fit_score'] ?? '-' }}/100</td>
            <td>{{ $row['preferred_channel'] ?? '-' }}</td>
            <td>{{ $row['reason_simple'] ?? $row['why'] ?? '-' }}</td>
            <td>{{ $row['offer_simple'] ?? $row['selling_angle'] ?? '-' }}</td>
            <td>{{ $row['action_simple'] ?? $row['first_test'] ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section">Customer yang Bisa Ditawarkan</div>
<table class="table">
    <thead>
    <tr>
        <th>Score</th>
        <th>Customer</th>
        <th>Salesperson</th>
        <th>Kenapa</th>
    </tr>
    </thead>
    <tbody>
    @foreach (array_slice($facts['cross_sell_candidates'] ?? [], 0, 20) as $row)
        <tr>
            <td>{{ $row['opportunity_score'] ?? '-' }}</td>
            <td>{{ $row['customer_name'] ?? $row['customer_code'] ?? '-' }}<br><span class="muted">{{ $row['customer_code'] ?? '' }}</span></td>
            <td>{{ $row['salesperson_name'] ?? '-' }}</td>
            <td>{{ $row['reason'] ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="page-break"></div>
<div class="section">Saran Tindakan</div>
@foreach ($strategies as $strategyRow)
    <div class="box">
        <b>{{ $strategyRow['title_display'] ?? $strategyRow['title'] ?? 'Saran Tindakan' }}</b>
        <span class="tag">{{ $strategyRow['priority'] ?? '-' }}</span>
        <span class="tag">{{ $strategyRow['channel'] ?? '-' }}</span>
        <div><b>Kenapa:</b> {{ $strategyRow['reason_simple'] ?? $strategyRow['hypothesis'] ?? '-' }}</div>
        <div><b>Target:</b> {{ $strategyRow['target_simple'] ?? $strategyRow['target_audience'] ?? '-' }}</div>
        <div><b>Tawarkan:</b> {{ $strategyRow['offer_simple'] ?? $strategyRow['offer'] ?? '-' }}</div>
        <div><b>Coba:</b> {{ $strategyRow['duration_days'] ?? '-' }} hari</div>
        <div><b>Ukur:</b> {{ $strategyRow['measure_simple'] ?? implode(', ', $strategyRow['kpis'] ?? []) }}</div>
        <div><b>Berhasil jika:</b> {{ $strategyRow['success_simple'] ?? '-' }}</div>
        <div><b>Ubah / hentikan jika:</b> {{ $strategyRow['stop_simple'] ?? $strategyRow['stop_rule'] ?? '-' }}</div>
    </div>
@endforeach

<div class="section">Riwayat Tes Penjualan</div>
<table class="table">
    <thead>
    <tr>
        <th>Nama Tes</th>
        <th>Channel</th>
        <th>Status</th>
        <th>Periode</th>
        <th>Keputusan</th>
    </tr>
    </thead>
    <tbody>
    @foreach (array_slice($experiments, 0, 20) as $experiment)
        <tr>
            <td>{{ $experiment['title'] ?? '-' }}</td>
            <td>{{ $experiment['channel'] ?? '-' }}</td>
            <td>{{ $experiment['status'] ?? '-' }}</td>
            <td>{{ $experiment['start_date'] ?? '-' }} - {{ $experiment['end_date'] ?? '-' }}</td>
            <td>{{ $experiment['decision'] ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if ($research && ($research['sources'] ?? []) !== [])
    <div class="section">Sumber Internet</div>
    <table class="table">
        <thead>
        <tr><th>Source</th><th>URL</th></tr>
        </thead>
        <tbody>
        @foreach ($research['sources'] as $source)
            <tr>
                <td>{{ $source['title'] ?? '-' }}</td>
                <td class="small">{{ $source['url'] ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($messages !== [])
    <div class="section">Catatan Advisor Terakhir</div>
    @foreach (array_slice($messages, -6) as $message)
        @php
            $recommendationTexts = [];
            $response = $message['response'] ?? null;
            if (is_array($response) && is_array($response['recommendations'] ?? null)) {
                foreach (array_slice($response['recommendations'], 0, 4) as $recommendation) {
                    if (is_array($recommendation)) {
                        $recommendationTexts[] = (string) ($recommendation['text'] ?? $recommendation['title'] ?? json_encode($recommendation, JSON_UNESCAPED_UNICODE));
                    } else {
                        $recommendationTexts[] = (string) $recommendation;
                    }
                }
            }
        @endphp
        <div class="box">
            <b>{{ $message['role'] ?? 'ADVISOR' }}</b>: {{ $message['message_text'] ?? '' }}
            @if ($recommendationTexts !== [])
                <div class="small"><b>Saran:</b> {{ implode(' | ', $recommendationTexts) }}</div>
            @endif
        </div>
    @endforeach
@endif
</body>
</html>
