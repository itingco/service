<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2937; }
        .title { font-size: 18px; font-weight: 700; color: #17365d; }
        .sub { color: #667085; font-size: 8px; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .kpi { border: 1px solid #d0d5dd; padding: 7px; }
        .kpi b { display: block; font-size: 12px; margin-top: 3px; }
        .section { font-size: 11px; font-weight: 700; margin: 12px 0 5px; color: #17365d; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #17365d; color: #fff; padding: 4px; text-align: left; }
        .table td { border-bottom: 1px solid #e5e7eb; padding: 4px; vertical-align: top; }
        .right { text-align: right; }
        .good { color: #15803d; }
        .bad { color: #dc2626; }
        .muted { color: #667085; }
        .box { border: 1px solid #d0d5dd; padding: 7px; margin-bottom: 7px; }
        .tag { font-size: 7px; border: 1px solid #d0d5dd; padding: 2px 4px; }
        .page-break { page-break-before: always; }
        .small { font-size: 7.5px; }
    </style>
</head>
<body>
@php
    $percent = fn ($v) => $presenter->percent($v);
    $money = fn ($v) => $presenter->money($v);
    $number = fn ($v) => $presenter->number($v);
    $strategy = $report['strategy'] ?? [];
    $forecast = $report['forecast'] ?? [];
    $online = $report['channels']['online'] ?? [];
    $offline = $report['channels']['offline'] ?? [];
    $customerSummary = $report['customers']['summary'] ?? [];
    $productSummary = $report['products']['summary'] ?? [];
@endphp

<table width="100%">
    <tr>
        <td>
            <div class="title">Business Analytics Review</div>
            <div class="sub">Filter: {{ $filter->label() }} | Generated {{ now()->format('d-m-Y H:i') }}</div>
        </td>
        <td class="right">
            <b>{{ strtoupper($filter->endMonth) }}</b><br>
            <span class="muted">{{ strtoupper($filter->groupBy) }}</span>
        </td>
    </tr>
</table>

<table class="grid">
    <tr>
        <td class="kpi">Sales {{ $filter->endMonth }}<b>{{ $money($report['current_sales'] ?? 0) }}</b></td>
        <td class="kpi">MoM<b class="{{ ($report['mom_growth'] ?? 0) >= 0 ? 'good' : 'bad' }}">{{ $percent($report['mom_growth'] ?? null) }}</b></td>
        <td class="kpi">YoY<b class="{{ ($report['yoy_growth'] ?? 0) >= 0 ? 'good' : 'bad' }}">{{ $percent($report['yoy_growth'] ?? null) }}</b></td>
        <td class="kpi">Momentum 3M<b>{{ $percent($report['momentum_3m'] ?? null) }}</b></td>
        <td class="kpi">Health Score<b>{{ $strategy['health_score'] ?? '-' }}/100</b></td>
        <td class="kpi">Forecast M+1<b>{{ $money($forecast[0]['forecast'] ?? null) }}</b></td>
        <td class="kpi">Forecast Accuracy<b>{{ $percent($report['forecast_accuracy']['accuracy_score'] ?? null) }}</b></td>
        <td class="kpi">Online Share<b>{{ $percent($online['share'] ?? null) }}</b></td>
    </tr>
</table>

<div class="section">Trend Penjualan dan Forecast</div>
{!! $chartSvg !!}

<table class="grid">
    <tr>
        <td width="50%" valign="top">
            <div class="section">Forecast 3 Bulan</div>
            <table class="table">
                <thead>
                <tr><th>Period</th><th class="right">Forecast</th><th class="right">Confidence</th><th class="right">Target</th><th>Status</th></tr>
                </thead>
                <tbody>
                @foreach ($forecast as $row)
                    <tr>
                        <td>{{ $row['period'] ?? '-' }}</td>
                        <td class="right">{{ $money($row['forecast'] ?? null) }}</td>
                        <td class="right">{{ $percent($row['confidence'] ?? null) }}</td>
                        <td class="right">{{ $money($row['official_target'] ?? null) }}</td>
                        <td>{{ $row['status'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </td>
        <td width="50%" valign="top">
            <div class="section">Annual Trend</div>
            <table class="table">
                <thead>
                <tr><th>Tahun</th><th class="right">Sales</th><th class="right">Growth</th></tr>
                </thead>
                <tbody>
                @foreach (($report['yearly'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['year'] ?? '-' }}</td>
                        <td class="right">{{ $money($row['sales'] ?? 0) }}</td>
                        <td class="right">{{ $percent($row['growth'] ?? null) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="section">Channel Intelligence</div>
<table class="table">
    <thead>
    <tr><th>Channel</th><th class="right">Sales 3M</th><th class="right">Share</th><th class="right">MoM</th><th class="right">YoY</th><th class="right">Momentum 3M</th></tr>
    </thead>
    <tbody>
    @foreach (['ONLINE' => $online, 'OFFLINE' => $offline] as $name => $row)
        <tr>
            <td><b>{{ $name }}</b></td>
            <td class="right">{{ $money($row['sales_3m'] ?? 0) }}</td>
            <td class="right">{{ $percent($row['share'] ?? null) }}</td>
            <td class="right">{{ $percent($row['mom_growth'] ?? null) }}</td>
            <td class="right">{{ $percent($row['yoy_growth'] ?? null) }}</td>
            <td class="right">{{ $percent($row['prior_3m_growth'] ?? null) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section">Growth / Decline Drivers</div>
<table class="table">
    <thead>
    <tr><th>Lini</th><th class="right">Sales Bulan</th><th class="right">YoY Delta</th><th class="right">MoM Delta</th><th class="right">YoY</th></tr>
    </thead>
    <tbody>
    @foreach (array_slice($report['line_drivers'] ?? [], 0, 12) as $row)
        <tr>
            <td>{{ $row['label'] ?? $row['key'] ?? '-' }}</td>
            <td class="right">{{ $money($row['sales'] ?? 0) }}</td>
            <td class="right {{ ($row['yoy_delta'] ?? 0) >= 0 ? 'good' : 'bad' }}">{{ $money($row['yoy_delta'] ?? 0) }}</td>
            <td class="right {{ ($row['mom_delta'] ?? 0) >= 0 ? 'good' : 'bad' }}">{{ $money($row['mom_delta'] ?? 0) }}</td>
            <td class="right">{{ $percent($row['yoy_growth'] ?? null) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section">Strategy Action Lab</div>
@forelse (($strategy['actions'] ?? []) as $action)
    <div class="box">
        <b>{{ $action['title'] ?? 'Action' }}</b>
        <span class="tag">{{ $action['priority'] ?? '-' }}</span>
        <span class="tag">{{ $action['channel'] ?? '-' }}</span>
        <div class="small">{{ $action['action'] ?? $action['recommendation'] ?? $action['text'] ?? '' }}</div>
        @if (!empty($action['test']))
            <div class="small">
                <b>Test:</b> {{ is_array($action['test']) ? json_encode($action['test'], JSON_UNESCAPED_UNICODE) : $action['test'] }}
            </div>
        @endif
    </div>
@empty
    <div class="muted">Belum ada action rule-based.</div>
@endforelse

<div class="page-break"></div>
<div class="section">Customer / Product Signals</div>
<table class="grid">
    <tr>
        <td width="50%" valign="top">
            <b>Customer Movement</b>
            <table class="table">
                <thead><tr><th>Status</th><th class="right">Count</th><th class="right">Sales / Impact</th></tr></thead>
                <tbody>
                @foreach (['new' => 'NEW', 'reactivated' => 'REACTIVATED', 'growing' => 'GROWING', 'stable' => 'STABLE', 'declining' => 'DECLINING', 'lost' => 'LOST'] as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="right">{{ $number($customerSummary[$key.'_count'] ?? 0) }}</td>
                        <td class="right">{{ $money($customerSummary[$key.'_sales'] ?? 0) }}</td>
                    </tr>
                @endforeach
                <tr><td>TOP 5 CONCENTRATION</td><td colspan="2" class="right">{{ $percent($customerSummary['top5_share'] ?? null) }}</td></tr>
                </tbody>
            </table>
        </td>
        <td width="50%" valign="top">
            <b>Product Movement</b>
            <table class="table">
                <thead><tr><th>Status</th><th class="right">Count</th><th class="right">Sales / Impact</th></tr></thead>
                <tbody>
                @foreach (['new' => 'NEW', 'reactivated' => 'REACTIVATED', 'growing' => 'GROWING', 'stable' => 'STABLE', 'declining' => 'DECLINING', 'lost' => 'LOST'] as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="right">{{ $number($productSummary[$key.'_count'] ?? 0) }}</td>
                        <td class="right">{{ $money($productSummary[$key.'_sales'] ?? 0) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="section">Breakdown {{ strtoupper($filter->groupBy) }}</div>
<table class="table">
    <thead>
    <tr><th>#</th><th>Key</th><th>Label</th><th class="right">Sales 3M</th><th class="right">Contribution</th><th class="right">YoY</th><th class="right">Qty</th></tr>
    </thead>
    <tbody>
    @foreach (array_slice($report['breakdown'] ?? [], 0, 40) as $index => $row)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $row['key'] ?? '-' }}</td>
            <td>{{ $row['label'] ?? '-' }}</td>
            <td class="right">{{ $money($row['sales'] ?? 0) }}</td>
            <td class="right">{{ $percent($row['contribution'] ?? null) }}</td>
            <td class="right">{{ $percent($row['yoy_growth'] ?? null) }}</td>
            <td class="right">{{ $number($row['quantity'] ?? 0) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section">Risk &amp; Opportunity Radar</div>
<table class="grid">
    <tr>
        <td width="50%" valign="top">
            <b>Risks</b>
            @foreach (($strategy['risks'] ?? []) as $risk)
                <div class="box">
                    <b>{{ $risk['title'] ?? 'Risk' }}</b>
                    <div>{{ $risk['text'] ?? '' }}</div>
                </div>
            @endforeach
        </td>
        <td width="50%" valign="top">
            <b>Opportunities</b>
            @foreach (($strategy['opportunities'] ?? []) as $opportunity)
                <div class="box">
                    <b>{{ $opportunity['title'] ?? 'Opportunity' }}</b>
                    <div>{{ $opportunity['text'] ?? '' }}</div>
                </div>
            @endforeach
        </td>
    </tr>
</table>
</body>
</html>
