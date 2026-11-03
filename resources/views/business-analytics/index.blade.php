@extends('layouts.app')

@section('title', 'Business Analytics')

@php
    $number = static fn (float|int|null $value): string => $value === null ? '—' : number_format((float) $value, 0, ',', '.');
    $percent = static fn (?float $value): string => $value === null ? '—' : number_format($value * 100, 1, ',', '.').'%';
    $statusClass = static fn (string $status): string => match ($status) {
        'ABOVE TARGET' => 'success',
        'ON TRACK' => 'warning',
        'AT RISK' => 'danger',
        default => 'secondary',
    };
    $baseQuery = array_filter([
        'database' => $filter->database,
        'end_month' => $filter->endMonth,
        'group_by' => $filter->groupBy,
        'source' => $filter->source,
        'line' => $filter->line,
        'customer_code' => $filter->customerCode,
        'salesperson' => $filter->salesperson,
    ], static fn ($value) => $value !== null && $value !== '');
    $forecastFirst = $report['forecast'][0] ?? null;
    [$targetLevel, $targetSource, $targetKey] = $report['target_scope'];
    $online = $report['channels']['online'] ?? [];
    $offline = $report['channels']['offline'] ?? [];
    $customerSummary = $report['customers']['summary'] ?? [];
    $productSummary = $report['products']['summary'] ?? [];
    $strategy = $report['strategy'] ?? ['health_score'=>0,'health_label'=>'N/A','scores'=>[],'actions'=>[],'risks'=>[],'opportunities'=>[]];
    $customerRiskRows = array_slice(array_values(array_filter($report['customers']['rows'] ?? [], static fn ($row) => in_array($row['status'] ?? '', ['LOST','DECLINING'], true))), 0, 5);
    $productSignalRows = array_slice(array_values(array_filter($report['products']['rows'] ?? [], static fn ($row) => in_array($row['status'] ?? '', ['GROWING','NEW','DECLINING','LOST'], true))), 0, 5);
@endphp

@push('styles')
<style>
    .ba-hero { background:linear-gradient(135deg,#101828,#17365d 58%,#244f86); color:#fff; border-radius:16px; padding:1.25rem 1.35rem; box-shadow:0 14px 32px rgba(16,24,40,.16); }
    .ba-hero h1 { font-size:1.45rem; font-weight:900; margin:0; }
    .ba-hero p { margin:.35rem 0 0; color:#d0d5dd; font-size:.78rem; }
    .ba-card { height:100%; background:#fff; border:1px solid #d0d5dd; border-radius:12px; padding:1rem; box-shadow:0 6px 18px rgba(16,24,40,.055); }
    .ba-card-label { color:#667085; font-size:.68rem; font-weight:900; letter-spacing:.055em; text-transform:uppercase; }
    .ba-card-value { color:#101828; font-size:1.28rem; font-weight:900; margin:.28rem 0; font-variant-numeric:tabular-nums; }
    .ba-card-note { color:#667085; font-size:.7rem; }
    .ba-panel { background:#fff; border:1px solid #d0d5dd; border-radius:12px; padding:1rem; box-shadow:0 6px 18px rgba(16,24,40,.055); }
    .ba-title { color:#101828; font-weight:900; font-size:.96rem; margin:0; }
    .ba-subtitle { color:#667085; font-size:.7rem; margin-top:.18rem; }
    .ba-forecast-card { border-top:4px solid #4472c4; }
    .ba-forecast-card.status-success { border-top-color:#15803d; }
    .ba-forecast-card.status-warning { border-top-color:#f59e0b; }
    .ba-forecast-card.status-danger { border-top-color:#dc3545; }
    .ba-range { background:#f8fafc; border-radius:8px; padding:.5rem .6rem; font-size:.7rem; color:#475467; }
    .ba-insight { border-left:4px solid #4472c4; background:#fff; border-radius:9px; padding:.75rem .85rem; border-top:1px solid #eaecf0; border-right:1px solid #eaecf0; border-bottom:1px solid #eaecf0; height:100%; }
    .ba-insight.success { border-left-color:#15803d; }
    .ba-insight.warning { border-left-color:#f59e0b; }
    .ba-insight.danger { border-left-color:#dc3545; }
    .ba-insight-title { font-size:.76rem; font-weight:900; color:#101828; }
    .ba-insight-text { font-size:.7rem; color:#667085; line-height:1.4; margin-top:.25rem; }
    .ba-table-wrap { overflow:auto; max-height:62vh; border:1px solid #eaecf0; border-radius:9px; }
    .ba-table { font-size:.72rem; margin:0; font-variant-numeric:tabular-nums; min-width:920px; }
    .ba-table thead th { position:sticky; top:0; z-index:2; background:#101828; color:#fff; white-space:nowrap; }
    .ba-table td { vertical-align:middle; }
    .ba-drill { font-weight:800; text-decoration:none; }
    .ba-breadcrumb { display:flex; flex-wrap:wrap; gap:.35rem; }
    .ba-breadcrumb a,.ba-breadcrumb span { border-radius:999px; padding:.3rem .58rem; font-size:.67rem; font-weight:800; text-decoration:none; border:1px solid #d0d5dd; background:#fff; color:#344054; }
    .ba-breadcrumb span { background:#fff2cc; border-color:#e9c548; color:#7a5500; }
    .ba-target-input { min-width:130px; text-align:right; font-variant-numeric:tabular-nums; }
    .ba-sync-note { background:#eef4ff; border:1px solid #c7d7fe; color:#3538cd; border-radius:8px; padding:.55rem .7rem; font-size:.7rem; }
    #businessAnalyticsTrendChart { max-height:390px; }

    .ba-health { border-top:4px solid #6941c6; }
    .ba-health-score { font-size:1.75rem; font-weight:950; line-height:1; }
    .ba-channel-card { position:relative; overflow:hidden; }
    .ba-channel-card::after { content:''; position:absolute; inset:0 auto 0 0; width:4px; background:#4472c4; }
    .ba-channel-card.online::after { background:#7f56d9; }
    .ba-channel-card.offline::after { background:#f79009; }
    .ba-mini-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.55rem; margin-top:.7rem; }
    .ba-mini { background:#f8fafc; border:1px solid #eaecf0; border-radius:8px; padding:.55rem; }
    .ba-mini b { display:block; font-size:.8rem; color:#101828; }
    .ba-mini span { font-size:.62rem; color:#667085; }
    .ba-section-label { font-size:.64rem; font-weight:900; letter-spacing:.08em; color:#667085; text-transform:uppercase; }
    .ba-driver-row { display:grid; grid-template-columns:minmax(140px,1fr) 88px 90px 90px; gap:.5rem; align-items:center; padding:.45rem 0; border-bottom:1px solid #eaecf0; font-size:.7rem; }
    .ba-driver-row:last-child { border-bottom:0; }
    .ba-delta-up { color:#15803d; font-weight:900; }
    .ba-delta-down { color:#dc2626; font-weight:900; }
    .ba-status-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; }
    .ba-status-box { border:1px solid #eaecf0; border-radius:9px; padding:.6rem; background:#fff; }
    .ba-status-box strong { display:block; font-size:1rem; }
    .ba-status-box span { font-size:.63rem; color:#667085; }
    .ba-action { border:1px solid #d0d5dd; border-left:5px solid #4472c4; background:#fff; border-radius:10px; padding:.85rem; height:100%; }
    .ba-action.priority-tinggi { border-left-color:#dc2626; }
    .ba-action.priority-sedang { border-left-color:#f59e0b; }
    .ba-action-head { display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; margin-bottom:.4rem; }
    .ba-action-title { font-size:.82rem; font-weight:900; color:#101828; }
    .ba-evidence { margin:.45rem 0; padding:.5rem .65rem; border-radius:8px; background:#f8fafc; font-size:.66rem; color:#475467; }
    .ba-evidence ul { margin:0; padding-left:1rem; }
    .ba-test { display:grid; grid-template-columns:1.15fr .65fr 1.35fr; gap:.45rem; margin-top:.55rem; }
    .ba-test > div { background:#eef4ff; border:1px solid #c7d7fe; border-radius:8px; padding:.5rem; font-size:.63rem; color:#344054; }
    .ba-test b { display:block; color:#3538cd; margin-bottom:.15rem; }
    .ba-risk { border-left:4px solid #dc2626; background:#fff7f7; border-radius:8px; padding:.65rem .75rem; margin-bottom:.5rem; }
    .ba-opportunity { border-left:4px solid #15803d; background:#f3fff6; border-radius:8px; padding:.65rem .75rem; margin-bottom:.5rem; }
    .ba-signal-title { font-size:.72rem; font-weight:900; }
    .ba-signal-text { font-size:.66rem; color:#667085; margin-top:.15rem; }
    .ba-season-grid { display:grid; grid-template-columns:repeat(12,minmax(42px,1fr)); gap:.3rem; }
    .ba-season { text-align:center; border:1px solid #eaecf0; border-radius:7px; padding:.42rem .2rem; font-size:.61rem; }
    .ba-season.high { background:#dcfce7; color:#166534; }
    .ba-season.low { background:#fee2e2; color:#991b1b; }
    @media(max-width:991px){ .ba-test{grid-template-columns:1fr;} .ba-season-grid{grid-template-columns:repeat(6,1fr);} }

</style>
@endpush

@section('content')
<div class="ba-hero mb-3 d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
    <div>
        <h1><i class="bi bi-bar-chart-line me-2"></i>Business Analytics</h1>
        <p>Trend 36 bulan · forecast 3 bulan · target risk · drill-down company → lini → customer → salesperson → item.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-light">{{ $filter->label() }}</span>
        <a class="btn btn-sm btn-success" href="{{ route('business-analytics.export.csv', $baseQuery) }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
        <a class="btn btn-sm btn-danger" href="{{ route('business-analytics.export.pdf', $baseQuery) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        <form method="POST" action="{{ route('business-analytics.sync.start') }}" class="d-inline" onsubmit="return confirm('Jalankan sinkronisasi 36 bulan? Proses berjalan di background dan bisa memakan waktu cukup lama.');">@csrf
            <button class="btn btn-sm btn-warning fw-bold" @disabled(($syncStatus['status'] ?? '') === 'RUNNING')><i class="bi bi-arrow-repeat me-1"></i>{{ (($syncStatus['status'] ?? '') === 'RUNNING') ? 'Sync Berjalan' : 'Refresh Data 36 Bulan' }}</button>
        </form>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->has('sync'))<div class="alert alert-danger">{{ $errors->first('sync') }}</div>@endif

<section class="filter-panel p-3 p-lg-4 mb-3">
    <form method="GET" action="{{ route('business-analytics.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-3 col-xl-2">
            <label class="form-label fw-bold">Database</label>
            <select name="database" class="form-select">
                <option value="all" @selected($filter->database==='all')>INGCO & SMI</option>
                <option value="ingco" @selected($filter->database==='ingco')>INGCO</option>
                <option value="smi" @selected($filter->database==='smi')>SMI</option>
            </select>
        </div>
        <div class="col-12 col-md-3 col-xl-2">
            <label class="form-label fw-bold">Sampai Bulan</label>
            <input type="month" name="end_month" value="{{ $filter->endMonth }}" class="form-control" required>
        </div>
        <div class="col-12 col-md-3 col-xl-2">
            <label class="form-label fw-bold">Kelompokkan</label>
            <select name="group_by" class="form-select">
                @foreach (['company'=>'Company','line'=>'Lini Penjualan','customer'=>'Customer','salesperson'=>'Salesperson','item'=>'Item'] as $value=>$label)
                    <option value="{{ $value }}" @selected($filter->groupBy===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="source" value="{{ $filter->source }}">
        <input type="hidden" name="line" value="{{ $filter->line }}">
        <input type="hidden" name="customer_code" value="{{ $filter->customerCode }}">
        <input type="hidden" name="salesperson" value="{{ $filter->salesperson }}">
        <div class="col-12 col-md-3 col-xl-2 d-grid"><button class="btn btn-report">Analisis</button></div>
        <div class="col-12 col-xl-4">
            <div class="ba-sync-note" data-ba-sync-box data-status-url="{{ route('business-analytics.sync.status') }}">
                <div><strong>Data mart terakhir:</strong> {{ $report['latest_period'] ?? 'belum ada' }}</div>
                @if($syncStatus)
                    <div class="mt-1"><strong>Sync 36 bulan:</strong> <span data-sync-label>{{ $syncStatus['status'] }}</span> · <span data-sync-count>{{ $syncStatus['processed'] ?? 0 }}/{{ $syncStatus['total_steps'] ?? 72 }}</span> · <span data-sync-percent>{{ $syncStatus['percent'] ?? 0 }}%</span></div>
                    <div class="progress mt-1" style="height:6px"><div class="progress-bar" data-sync-bar style="width:{{ $syncStatus['percent'] ?? 0 }}%"></div></div>
                    <div class="mt-1 text-secondary" data-sync-detail>Success {{ $syncStatus['success'] ?? 0 }} · Failed {{ $syncStatus['failed'] ?? 0 }}</div>
                @else
                    <div class="mt-1">Belum ada web sync 36 bulan. Klik <strong>Refresh Data 36 Bulan</strong> bila perlu refresh penuh.</div>
                @endif
            </div>
        </div>
    </form>
</section>

<div class="ba-breadcrumb mb-3">
    <a href="{{ route('business-analytics.index', ['database'=>$filter->database,'end_month'=>$filter->endMonth,'group_by'=>$filter->database==='all'?'company':'line']) }}">TOTAL</a>
    @if ($filter->source)<span>{{ $filter->source }}</span>@endif
    @if ($filter->line)<span>{{ $filter->line }}</span>@endif
    @if ($filter->customerCode)<span>CUSTOMER {{ $filter->customerCode }}</span>@endif
    @if ($filter->salesperson)<span>{{ $filter->salesperson }}</span>@endif
</div>

@if ($report['trend'] === [])
    <div class="alert alert-warning">
        <div class="fw-bold">Data Business Analytics belum tersedia.</div>
        <div class="small mt-1">Sesudah migration, jalankan <code>php artisan analytics:sync --months=36</code>. Dashboard existing tetap dapat digunakan seperti biasa.</div>
    </div>
@else
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="ba-card"><div class="ba-card-label">Sales {{ $filter->endMonth }}</div><div class="ba-card-value">Rp {{ $number($report['current_sales']) }}</div><div class="ba-card-note">Actual bulan terpilih</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card"><div class="ba-card-label">Growth MoM</div><div class="ba-card-value {{ ($report['mom_growth'] ?? 0)>=0?'text-success':'text-danger' }}">{{ $percent($report['mom_growth']) }}</div><div class="ba-card-note">vs bulan sebelumnya</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card"><div class="ba-card-label">Growth YoY</div><div class="ba-card-value {{ ($report['yoy_growth'] ?? 0)>=0?'text-success':'text-danger' }}">{{ $percent($report['yoy_growth']) }}</div><div class="ba-card-note">vs bulan sama tahun lalu</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card"><div class="ba-card-label">Momentum 3 Bulan</div><div class="ba-card-value {{ ($report['momentum_3m'] ?? 0)>=0?'text-success':'text-danger' }}">{{ $percent($report['momentum_3m']) }}</div><div class="ba-card-note">3M terakhir vs 3M sebelumnya</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card ba-channel-card online"><div class="ba-card-label">Online Share</div><div class="ba-card-value">{{ $percent($online['share'] ?? null) }}</div><div class="ba-card-note">Online YoY {{ $percent($online['yoy_growth'] ?? null) }} · MoM {{ $percent($online['mom_growth'] ?? null) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card ba-channel-card offline"><div class="ba-card-label">Offline Share</div><div class="ba-card-value">{{ $percent($offline['share'] ?? null) }}</div><div class="ba-card-note">Offline YoY {{ $percent($offline['yoy_growth'] ?? null) }} · MoM {{ $percent($offline['mom_growth'] ?? null) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card"><div class="ba-card-label">Forecast M+1</div><div class="ba-card-value">Rp {{ $number($forecastFirst['forecast'] ?? null) }}</div><div class="ba-card-note">{{ $forecastFirst['status'] ?? '—' }} · confidence {{ isset($forecastFirst['confidence']) ? $percent($forecastFirst['confidence']) : '—' }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="ba-card ba-health"><div class="ba-card-label">Business Health</div><div class="ba-health-score">{{ $strategy['health_score'] }}/100</div><div class="ba-card-note">{{ $strategy['health_label'] }} · Forecast accuracy {{ isset($report['forecast_accuracy']['accuracy_score']) ? $percent($report['forecast_accuracy']['accuracy_score']) : '—' }}</div></div></div>
</div>


<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <section class="ba-panel h-100">
            <div class="ba-section-label">Channel Intelligence</div>
            <h2 class="ba-title mb-2">Online vs Offline</h2>
            <div class="row g-2">
                @foreach (['online'=>$online,'offline'=>$offline] as $channelKey=>$channel)
                <div class="col-12 col-md-6">
                    <div class="ba-card ba-channel-card {{ $channelKey }}">
                        <div class="d-flex justify-content-between"><strong>{{ strtoupper($channelKey) }}</strong><span class="badge text-bg-light">Share {{ $percent($channel['share'] ?? null) }}</span></div>
                        <div class="ba-card-value">Rp {{ $number($channel['sales'] ?? null) }}</div>
                        <div class="ba-mini-grid">
                            <div class="ba-mini"><b class="{{ ($channel['mom_growth'] ?? 0)>=0?'text-success':'text-danger' }}">{{ $percent($channel['mom_growth'] ?? null) }}</b><span>MoM</span></div>
                            <div class="ba-mini"><b class="{{ ($channel['yoy_growth'] ?? 0)>=0?'text-success':'text-danger' }}">{{ $percent($channel['yoy_growth'] ?? null) }}</b><span>YoY</span></div>
                            <div class="ba-mini"><b>{{ $percent($channel['prior_3m_growth'] ?? null) }}</b><span>Momentum 3M</span></div>
                            <div class="ba-mini"><b>Rp {{ $number($channel['sales_3m'] ?? null) }}</b><span>Sales 3M</span></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="ba-panel h-100">
            <div class="ba-section-label">Growth Decomposition</div>
            <h2 class="ba-title mb-2">Lini Pendorong & Penahan Sales</h2>
            @forelse (array_slice($report['line_drivers'],0,8) as $driver)
                <div class="ba-driver-row">
                    <strong>{{ $driver['label'] }}</strong>
                    <span class="badge {{ $driver['channel']==='ONLINE'?'text-bg-primary':'text-bg-light' }}">{{ $driver['channel'] }}</span>
                    <span class="text-end {{ $driver['yoy_delta']>=0?'ba-delta-up':'ba-delta-down' }}">{{ $driver['yoy_delta']>=0?'+':'' }}Rp {{ $number($driver['yoy_delta']) }}</span>
                    <span class="text-end {{ ($driver['yoy_growth']??0)>=0?'text-success':'text-danger' }}">{{ $percent($driver['yoy_growth']) }}</span>
                </div>
            @empty
                <div class="text-secondary small">Belum ada driver yang dapat dihitung.</div>
            @endforelse
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <section class="ba-panel h-100">
            <div class="ba-section-label">Customer Intelligence</div>
            <h2 class="ba-title">Customer Movement 3M</h2>
            <div class="ba-status-grid mt-2">
                @foreach ([
                    ['NEW','new_count','new_sales','success'],['REACTIVATED','reactivated_count','reactivated_sales','primary'],['GROWING','growing_count','growing_sales','success'],
                    ['STABLE','stable_count','stable_sales','secondary'],['DECLINING','declining_count','declining_sales','warning'],['LOST','lost_count','lost_sales','danger']
                ] as [$label,$countKey,$salesKey,$tone])
                    <div class="ba-status-box"><span class="badge text-bg-{{ $tone }}">{{ $label }}</span><strong>{{ $customerSummary[$countKey] ?? 0 }}</strong><span>Impact Rp {{ $number($customerSummary[$salesKey] ?? 0) }}</span></div>
                @endforeach
            </div>
            <div class="ba-card-note mt-2">Top-5 customer concentration: <strong>{{ $percent($customerSummary['top5_share'] ?? null) }}</strong>. Lost revenue adalah baseline customer yang tidak lagi muncul pada 3M terakhir.</div>
            <div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Customer at Risk</th><th>Status</th><th class="text-end">Impact</th></tr></thead><tbody>
                @forelse($customerRiskRows as $row)
                    <tr><td><strong>{{ $row['label'] }}</strong></td><td><span class="badge {{ $row['status']==='LOST'?'text-bg-danger':'text-bg-warning' }}">{{ $row['status'] }}</span></td><td class="text-end">Rp {{ $number(abs($row['delta_vs_base'])) }}</td></tr>
                @empty<tr><td colspan="3" class="text-secondary">Tidak ada customer risk kuat.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="ba-panel h-100">
            <div class="ba-section-label">Product Intelligence</div>
            <h2 class="ba-title">Product Demand Movement 3M</h2>
            <div class="ba-status-grid mt-2">
                @foreach ([
                    ['NEW DEMAND','new_count','new_sales','success'],['REACTIVATED','reactivated_count','reactivated_sales','primary'],['GROWING','growing_count','growing_sales','success'],
                    ['STABLE','stable_count','stable_sales','secondary'],['DECLINING','declining_count','declining_sales','warning'],['LOST','lost_count','lost_sales','danger']
                ] as [$label,$countKey,$salesKey,$tone])
                    <div class="ba-status-box"><span class="badge text-bg-{{ $tone }}">{{ $label }}</span><strong>{{ $productSummary[$countKey] ?? 0 }}</strong><span>Impact Rp {{ $number($productSummary[$salesKey] ?? 0) }}</span></div>
                @endforeach
            </div>
            <div class="ba-card-note mt-2">Gunakan growing/new demand sebagai kandidat promo; declining/lost sebagai kandidat bundle, reactivation, atau evaluasi assortment.</div>
            <div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Product Signal</th><th>Status</th><th class="text-end">Impact</th></tr></thead><tbody>
                @forelse($productSignalRows as $row)
                    <tr><td><strong>{{ $row['key'] }}</strong><div class="small text-secondary">{{ $row['label'] }}</div></td><td><span class="badge {{ in_array($row['status'],['GROWING','NEW'],true)?'text-bg-success':($row['status']==='LOST'?'text-bg-danger':'text-bg-warning') }}">{{ $row['status'] }}</span></td><td class="text-end">Rp {{ $number($row['impact']) }}</td></tr>
                @empty<tr><td colspan="3" class="text-secondary">Tidak ada product signal kuat.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</div>

<section class="ba-panel mb-3">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-2 mb-3">
        <div><div class="ba-section-label">Strategy Action Lab</div><h2 class="ba-title">Rekomendasi Tindakan Cepat & Terukur</h2><div class="ba-subtitle">Setiap rekomendasi adalah hipotesis berbasis data. Jalankan sebagai test terbatas, ukur KPI, lalu scale/stop berdasarkan hasil.</div></div>
        <span class="badge text-bg-dark align-self-xl-start">{{ count($strategy['actions']) }} actions</span>
    </div>
    <div class="row g-3">
        @forelse ($strategy['actions'] as $action)
            <div class="col-12 col-xl-6">
                <div class="ba-action priority-{{ strtolower($action['priority']) }}">
                    <div class="ba-action-head"><span class="badge text-bg-{{ $action['priority']==='TINGGI'?'danger':'warning' }}">{{ $action['priority'] }}</span><span class="badge text-bg-primary">{{ $action['channel'] }}</span><span class="ba-action-title">{{ $action['title'] }}</span></div>
                    <div class="small"><strong>Logika:</strong> {{ $action['hypothesis'] }}</div>
                    <div class="ba-evidence"><strong>Fakta pendukung</strong><ul>@foreach ($action['evidence'] as $evidence)<li>{{ $evidence }}</li>@endforeach</ul></div>
                    <div class="small"><strong>Tindakan:</strong> {{ $action['action'] }}</div>
                    <div class="ba-test">
                        <div><b>KPI Uji</b>{{ $action['test']['kpi'] }}</div>
                        <div><b>Durasi</b>{{ $action['test']['duration_days'] }} hari</div>
                        <div><b>Success / Stop Rule</b>{{ $action['test']['success_threshold'] }}<br><span class="text-danger">Stop:</span> {{ $action['test']['stop_rule'] }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-secondary">Belum ada action yang memenuhi rule berdasarkan data saat ini.</div>
        @endforelse
    </div>
</section>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <section class="ba-panel h-100"><div class="ba-section-label">Risk Radar</div><h2 class="ba-title mb-2">Risiko Penjualan</h2>
            @forelse ($strategy['risks'] as $risk)<div class="ba-risk"><div class="d-flex justify-content-between"><span class="ba-signal-title">{{ $risk['title'] }}</span><span class="badge text-bg-danger">{{ $risk['impact'] }}</span></div><div class="ba-signal-text">{{ $risk['text'] }}</div></div>@empty<div class="text-secondary small">Tidak ada risiko rule-based yang signifikan.</div>@endforelse
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="ba-panel h-100"><div class="ba-section-label">Opportunity Radar</div><h2 class="ba-title mb-2">Peluang yang Layak Di-scale</h2>
            @forelse ($strategy['opportunities'] as $opp)<div class="ba-opportunity"><div class="d-flex justify-content-between"><span class="ba-signal-title">{{ $opp['title'] }}</span><span class="badge text-bg-success">{{ $opp['impact'] }}</span></div><div class="ba-signal-text">{{ $opp['text'] }}</div></div>@empty<div class="text-secondary small">Belum ada opportunity yang cukup kuat menurut rule saat ini.</div>@endforelse
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6"><section class="ba-panel h-100"><div class="ba-section-label">Online Promo Candidates</div><h2 class="ba-title mb-2">SKU Online untuk Test Promo</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Item</th><th class="text-end">Sales 3M</th><th class="text-end">YoY</th></tr></thead><tbody>@forelse(array_slice($report['online_candidates'],0,8) as $row)<tr><td><strong>{{ $row['key'] }}</strong><div class="small text-secondary">{{ $row['label'] }}</div></td><td class="text-end">{{ $number($row['sales']) }}</td><td class="text-end {{ ($row['yoy_growth']??0)>=0?'text-success':'text-danger' }}">{{ $row['new_demand']?'NEW':$percent($row['yoy_growth']) }}</td></tr>@empty<tr><td colspan="3" class="text-secondary">Belum ada kandidat online.</td></tr>@endforelse</tbody></table></div></section></div>
    <div class="col-12 col-xl-6"><section class="ba-panel h-100"><div class="ba-section-label">Offline Promo Candidates</div><h2 class="ba-title mb-2">SKU Offline untuk Recovery / Bundle</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Item</th><th class="text-end">Sales 3M</th><th class="text-end">YoY</th></tr></thead><tbody>@forelse(array_slice($report['offline_candidates'],0,8) as $row)<tr><td><strong>{{ $row['key'] }}</strong><div class="small text-secondary">{{ $row['label'] }}</div></td><td class="text-end">{{ $number($row['sales']) }}</td><td class="text-end {{ ($row['yoy_growth']??0)>=0?'text-success':'text-danger' }}">{{ $row['new_demand']?'NEW':$percent($row['yoy_growth']) }}</td></tr>@empty<tr><td colspan="3" class="text-secondary">Belum ada kandidat offline.</td></tr>@endforelse</tbody></table></div></section></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <section class="ba-panel h-100">
            <h2 class="ba-title">Trend Penjualan & Forecast</h2>
            <div class="ba-subtitle mb-3">Actual historis sampai {{ $filter->endMonth }} dan proyeksi 3 bulan ke depan.</div>
            <canvas id="businessAnalyticsTrendChart"></canvas>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="ba-panel h-100">
            <h2 class="ba-title">Annual Trend</h2>
            <div class="ba-subtitle mb-2">Perbandingan YTD sampai bulan yang sama pada setiap tahun agar tidak membandingkan tahun berjalan dengan full-year.</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Tahun</th><th class="text-end">Sales</th><th class="text-end">Growth</th></tr></thead>
                    <tbody>@foreach ($report['yearly'] as $year)<tr><td class="fw-bold">{{ $year['year'] }}</td><td class="text-end">{{ $number($year['sales']) }}</td><td class="text-end {{ ($year['growth']??0)>=0?'text-success':'text-danger' }}">{{ $percent($year['growth']) }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </section>
    </div>
</div>


<section class="ba-panel mb-3">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-xl-4"><div class="ba-section-label">Forecast Reliability</div><h2 class="ba-title">Backtest 6 Periode</h2><div class="ba-subtitle">Forecast diuji ulang pada bulan historis yang sudah memiliki actual.</div></div>
        <div class="col-4 col-xl-2"><div class="ba-mini"><b>{{ $percent($report['forecast_accuracy']['accuracy_score'] ?? null) }}</b><span>Accuracy Score</span></div></div>
        <div class="col-4 col-xl-2"><div class="ba-mini"><b>{{ $percent($report['forecast_accuracy']['wape'] ?? null) }}</b><span>WAPE Error</span></div></div>
        <div class="col-4 col-xl-2"><div class="ba-mini"><b>{{ $percent($report['forecast_accuracy']['mape'] ?? null) }}</b><span>MAPE</span></div></div>
        <div class="col-12 col-xl-2"><div class="ba-mini"><b>{{ $report['forecast_accuracy']['tested_periods'] ?? 0 }}</b><span>Tested Months</span></div></div>
    </div>
</section>

<div class="row g-3 mb-3">
    @foreach ($report['forecast'] as $fc)
        <div class="col-12 col-md-4">
            <div class="ba-card ba-forecast-card status-{{ $statusClass($fc['status']) }}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div><div class="ba-card-label">Forecast {{ $fc['period'] }}</div><div class="ba-card-value">Rp {{ $number($fc['forecast']) }}</div></div>
                    <span class="badge text-bg-{{ $statusClass($fc['status']) }}">{{ $fc['status'] }}</span>
                </div>
                <div class="ba-range mb-2">Range: Rp {{ $number($fc['lower']) }} — Rp {{ $number($fc['upper']) }}<br>Confidence: {{ $percent($fc['confidence']) }}</div>
                <div class="small"><strong>Official target:</strong> Rp {{ $number($fc['official_target']) }}</div>
                <div class="small"><strong>Recommended:</strong> Rp {{ $number($fc['recommended_target']) }}</div>
                <div class="ba-card-note mt-1">{{ $fc['method'] }}</div>
            </div>
        </div>
    @endforeach
</div>

@if ($report['forecast'] !== [] && ($dashboardMenuAccess['kpi_targets'] ?? false))
<section class="ba-panel mb-3">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-2 align-items-xl-center mb-3">
        <div><h2 class="ba-title">Official Target — {{ $targetLevel }}</h2><div class="ba-subtitle">Scope: {{ $targetSource }} / {{ $targetKey }}. Recommended target tetap dihitung sistem dan tidak menimpa target resmi.</div></div>
    </div>
    <form method="POST" action="{{ route('business-analytics.targets.save') }}">
        @csrf
        <input type="hidden" name="scope_level" value="{{ $targetLevel }}">
        <input type="hidden" name="source" value="{{ $targetSource }}">
        <input type="hidden" name="dimension_key" value="{{ $targetKey }}">
        <div class="table-responsive"><table class="table table-sm align-middle mb-2"><thead><tr><th>Periode</th><th>Forecast</th><th>Recommended</th><th>Official Target</th></tr></thead><tbody>
        @foreach ($report['forecast'] as $fc)
            <tr><td class="fw-bold">{{ $fc['period'] }}</td><td>Rp {{ $number($fc['forecast']) }}</td><td>Rp {{ $number($fc['recommended_target']) }}</td><td><input class="form-control form-control-sm ba-target-input" type="number" min="0" step="1000" name="targets[{{ $fc['period'] }}]" value="{{ $fc['official_target'] }}" placeholder="Belum diatur"></td></tr>
        @endforeach
        </tbody></table></div>
        <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Simpan Official Target</button>
    </form>
</section>
@endif

<div class="row g-3 mb-3">
    @foreach ($report['insights'] as $insight)
        <div class="col-12 col-md-6 col-xl"><div class="ba-insight {{ $insight['type'] }}"><div class="ba-insight-title">{{ $insight['title'] }}</div><div class="ba-insight-text">{{ $insight['text'] }}</div></div></div>
    @endforeach
</div>


<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8"><section class="ba-panel h-100"><div class="ba-section-label">Seasonality</div><h2 class="ba-title mb-2">Pola Bulan Kuat / Lemah</h2><div class="ba-season-grid">@foreach($report['seasonality'] as $season)<div class="ba-season {{ strtolower($season['signal']) }}"><strong>{{ $season['label'] }}</strong><br>{{ $season['index']===null?'—':number_format($season['index'],2) }}x<br><small>{{ $season['signal'] }}</small></div>@endforeach</div><div class="ba-card-note mt-2">Index &gt;1 berarti bulan historis di atas rata-rata; gunakan untuk membedakan penurunan struktural vs pola musiman.</div></section></div>
    <div class="col-12 col-xl-4"><section class="ba-panel h-100"><div class="ba-section-label">Anomaly Detection</div><h2 class="ba-title mb-2">Spike / Drop Tidak Normal</h2>@forelse($report['anomalies'] as $a)<div class="d-flex justify-content-between border-bottom py-2 small"><span><strong>{{ $a['period'] }}</strong> <span class="badge {{ $a['type']==='SPIKE'?'text-bg-success':'text-bg-danger' }}">{{ $a['type'] }}</span></span><span class="text-end">Rp {{ $number($a['sales']) }}<br><small>{{ $percent($a['deviation']) }} vs mean</small></span></div>@empty<div class="text-secondary small">Tidak ada anomaly kuat.</div>@endforelse</section></div>
</div>

<section class="ba-panel">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-2 mb-3">
        <div><h2 class="ba-title">Breakdown: {{ strtoupper($filter->groupBy) }}</h2><div class="ba-subtitle">Angka menggunakan akumulasi 3 bulan terakhir. Klik nama untuk drill-down ke level berikutnya.</div></div>
        <span class="badge text-bg-dark align-self-xl-start">Top {{ count($report['breakdown']) }}</span>
    </div>
    <div class="ba-table-wrap"><table class="table table-hover ba-table"><thead><tr><th>#</th><th>{{ ucfirst($filter->groupBy) }}</th><th class="text-end">Sales 3M</th><th class="text-end">Contribution</th><th class="text-end">YoY Growth</th><th class="text-end">Qty</th><th>Next</th></tr></thead><tbody>
    @forelse ($report['breakdown'] as $row)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td class="fw-bold">
                @if ($filter->nextLevel() !== null)
                    <a class="ba-drill" href="{{ route('business-analytics.index', $filter->drillQuery($baseQuery, (string) $row['key'])) }}">{{ $row['label'] }}</a>
                @else
                    {{ $row['label'] }}
                @endif
            </td>
            <td class="text-end">{{ $number($row['sales']) }}</td>
            <td class="text-end">{{ $percent($row['contribution']) }}</td>
            <td class="text-end {{ ($row['yoy_growth'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ $percent($row['yoy_growth']) }}</td>
            <td class="text-end">{{ $number($row['quantity']) }}</td>
            <td>
                @if ($filter->nextLevel() !== null)
                    <span class="badge text-bg-light">{{ strtoupper((string) $filter->nextLevel()) }} →</span>
                @else
                    <span class="text-secondary">Detail akhir</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada breakdown untuk filter ini.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endif
@endsection

@push('scripts')
@if ($report['trend'] !== [])
@php
    $actualLabels = array_keys($report['trend']);
    $forecastLabels = array_column($report['forecast'], 'period');
    $labels = array_values(array_unique([...$actualLabels, ...$forecastLabels]));
    $actualData = [];
    $forecastData = [];
    $lastActualPeriod = end($actualLabels);
    foreach ($labels as $label) {
        $actualData[] = $report['trend'][$label] ?? null;
        if ($label === $lastActualPeriod) {
            $forecastData[] = $report['trend'][$label] ?? null;
            continue;
        }
        $fc = collect($report['forecast'])->firstWhere('period', $label);
        $forecastData[] = $fc['forecast'] ?? null;
    }
@endphp
<script>
window.businessAnalyticsChartData = @json(['labels'=>$labels,'actual'=>$actualData,'forecast'=>$forecastData]);
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/business-analytics.js') }}"></script>
@endif
@endpush

@push('scripts')
<script>
(function(){
 const box=document.querySelector('[data-ba-sync-box]'); if(!box) return; const url=box.dataset.statusUrl;
 const tick=async()=>{try{const r=await fetch(url,{headers:{'Accept':'application/json'}});if(!r.ok)return;const j=await r.json();const s=j.data;if(!s)return;
 const q=(sel)=>box.querySelector(sel); if(q('[data-sync-label]'))q('[data-sync-label]').textContent=s.status; if(q('[data-sync-count]'))q('[data-sync-count]').textContent=`${s.processed||0}/${s.total_steps||72}`; if(q('[data-sync-percent]'))q('[data-sync-percent]').textContent=`${s.percent||0}%`; if(q('[data-sync-bar]'))q('[data-sync-bar]').style.width=`${s.percent||0}%`; if(q('[data-sync-detail]'))q('[data-sync-detail]').textContent=`Success ${s.success||0} · Failed ${s.failed||0}`; if(s.status==='RUNNING')setTimeout(tick,5000); else if(['COMPLETED','COMPLETED_WITH_ERRORS','FAILED','STALE'].includes(s.status)) setTimeout(()=>location.reload(),1200);
 }catch(e){}}; if(box.querySelector('[data-sync-label]')?.textContent.trim()==='RUNNING')setTimeout(tick,2500);
})();
</script>
@endpush
