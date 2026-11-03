@extends('layouts.app')

@section('title', 'Sales Insights & R&D Dashboard')

@php
    $number = static function (float $value): string {
        if (abs($value) < 0.5) return '-';
        $formatted = number_format(abs($value), 0, ',', '.');
        return $value < 0 ? '('.$formatted.')' : $formatted;
    };
    $percent = static fn (?float $value): string => $value === null
        ? '—'
        : number_format($value * 100, 2, ',', '.').'%';
    $plainPercent = static fn (?float $value): string => $value === null
        ? '—'
        : number_format($value, 2, ',', '.').'%';
    $query = [
        'database' => $filter->database,
        'end_date' => $filter->selectedDate->format('Y-m-d'),
        'item_status' => $filter->itemStatus,
    ];
@endphp

@push('styles')
<style>
    .insights-hero { background:linear-gradient(135deg,#101828,#344054); color:#fff; border-radius:14px; padding:1.2rem 1.35rem; box-shadow:0 12px 28px rgba(16,24,40,.15); }
    .insights-hero h1 { font-size:1.35rem; font-weight:900; margin:0; }
    .insights-hero p { color:#d0d5dd; margin:.35rem 0 0; font-size:.8rem; }
    .management-insights { display:block; }
    .insight-section-head { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:.85rem; }
    .insight-kpi-card { height:100%; background:#fff; border:1px solid #d0d5dd; border-top:4px solid #4472c4; border-radius:10px; padding:.9rem; box-shadow:0 5px 14px rgba(16,24,40,.05); }
    .insight-kpi-card.is-success { border-top-color:#15803d; }
    .insight-kpi-card.is-warning { border-top-color:#f59e0b; }
    .insight-kpi-card.is-danger { border-top-color:#dc3545; }
    .insight-kpi-label { color:#667085; font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
    .insight-kpi-value { color:#101828; font-size:1.05rem; font-weight:900; margin:.3rem 0 .2rem; font-variant-numeric:tabular-nums; }
    .insight-kpi-note { color:#667085; font-size:.7rem; line-height:1.25; }
    .insight-panel { background:#fff; border:1px solid #d0d5dd; border-radius:10px; padding:1rem; box-shadow:0 5px 14px rgba(16,24,40,.05); }
    .insight-panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:.85rem; }
    .insight-title { color:#101828; font-size:.92rem; font-weight:900; margin:0; }
    .insight-subtitle { color:#667085; font-size:.72rem; margin-top:.15rem; }
    .insight-table-wrap { border:1px solid #eaecf0; border-radius:8px; }
    .insight-equal-scroll { height:430px; max-height:430px; overflow-y:auto; scrollbar-gutter:stable; }
    .insight-action-scroll { padding-right:.35rem; }
    .insight-action-scroll .action-item:last-child { margin-bottom:0; }
    .insight-table { font-size:.72rem; font-variant-numeric:tabular-nums; }
    .insight-table thead th { position:sticky; top:0; z-index:2; background:#f2f4f7; color:#344054; white-space:nowrap; }
    .insight-table td { white-space:nowrap; }
    .pace-badge,.signal-badge { display:inline-flex; justify-content:center; border-radius:999px; padding:.22rem .52rem; font-size:.62rem; font-weight:900; border:1px solid transparent; white-space:nowrap; }
    .pace-badge { min-width:92px; }
    .pace-success,.signal-scale-up { background:#dcfce7; color:#166534; border-color:#86efac; }
    .pace-warning,.signal-validasi-pasar,.signal-permintaan-baru { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
    .pace-danger,.signal-evaluasi,.signal-permintaan-hilang { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
    .pace-secondary,.signal-stabil { background:#f2f4f7; color:#475467; border-color:#d0d5dd; }
    .signal-risiko-konsentrasi { background:#e0e7ff; color:#3730a3; border-color:#a5b4fc; }
    .signal-fase-keluar { background:#f3e8ff; color:#6b21a8; border-color:#d8b4fe; }
    .action-item { border:1px solid #eaecf0; border-left:4px solid #dc3545; border-radius:8px; padding:.75rem; margin-bottom:.65rem; }
    .action-priority { border-radius:999px; padding:.16rem .48rem; font-size:.6rem; font-weight:900; }
    .priority-tinggi { background:#fee2e2; color:#991b1b; }
    .priority-sedang { background:#fef3c7; color:#92400e; }
    .priority-rendah { background:#e0f2fe; color:#075985; }
    .action-issue { color:#344054; font-size:.72rem; margin:.35rem 0; }
    .action-impact { color:#667085; font-size:.68rem; }
    .action-suggestion { background:#f8fafc; color:#344054; border-radius:6px; padding:.45rem .55rem; margin-top:.45rem; font-size:.68rem; }
    .insight-empty { background:#f8fafc; color:#667085; border-radius:8px; padding:1rem; text-align:center; font-size:.75rem; }
    .insight-chart-panel { min-height:390px; overflow:hidden; }
    .rank-title { color:#667085; font-size:.68rem; font-weight:900; letter-spacing:.04em; text-transform:uppercase; margin:0 0 .45rem; }
    .rank-row,.entity-row,.mix-row { display:grid; align-items:center; gap:.5rem; border-bottom:1px solid #eaecf0; padding:.42rem 0; font-size:.72rem; }
    .rank-row { grid-template-columns:minmax(0,1fr) auto; }
    .entity-row { grid-template-columns:minmax(0,1fr) auto 54px; }
    .mix-row { grid-template-columns:minmax(0,1fr) auto 54px; }
    .rank-row:last-child,.entity-row:last-child,.mix-row:last-child { border-bottom:0; }
    .entity-label { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:700; }
    .entity-sales { white-space:nowrap; font-weight:800; font-variant-numeric:tabular-nums; }
    .entity-percent,.mix-row em { text-align:right; color:#667085; font-style:normal; font-variant-numeric:tabular-nums; }
    .concentration-value { font-size:1.25rem; font-weight:900; }
    .concentration-track { height:10px; overflow:hidden; border-radius:999px; background:#eaecf0; margin:.25rem 0 .45rem; }
    .concentration-track span { display:block; height:100%; background:linear-gradient(90deg,#4472c4,#dc3545); border-radius:inherit; }
    .rd-section { border-top:4px solid #7f56d9; }
    .rd-summary-card { height:100%; background:#fff; border:1px solid #d6bbfb; border-radius:10px; padding:.85rem; }
    .rd-summary-value { font-size:1.35rem; font-weight:900; color:#6941c6; }
    .rd-summary-label { font-size:.72rem; color:#667085; font-weight:800; }
    .recommendation { min-width:280px; white-space:normal !important; color:#344054; }
    @media (max-width:767px) { .insight-section-head { align-items:flex-start; flex-direction:column; } .insight-kpi-value { font-size:.92rem; } .insight-chart-panel { min-height:340px; } .insight-equal-scroll { height:360px; max-height:360px; } }
</style>
@endpush

@section('content')
<div class="insights-hero mb-3 d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
    <div>
        <h1>Sales Insights &amp; R&amp;D Dashboard</h1>
        <p>Management control, risiko target, demand produk, validasi pasar, channel fit, dan rekomendasi pengembangan produk.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-light" href="{{ route('sales-dashboard.index', $query) }}">Kembali ke Sales Dashboard</a>
        <a class="btn btn-sm btn-success" href="{{ route('sales-insights.export.excel', $query) }}">Export Excel Analisis</a>
    </div>
</div>

<section class="filter-panel p-3 p-lg-4 mb-3">
    <form method="GET" action="{{ route('sales-insights.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label fw-bold" for="database">Database</label><select class="form-select" id="database" name="database"><option value="all" @selected($filter->database==='all')>INGCO &amp; SMI</option><option value="ingco" @selected($filter->database==='ingco')>INGCO</option><option value="smi" @selected($filter->database==='smi')>SMI</option></select></div>
        <div class="col-12 col-md-3"><label class="form-label fw-bold" for="end_date">Tanggal Laporan</label><input class="form-control" type="date" id="end_date" name="end_date" value="{{ $filter->selectedDate->format('Y-m-d') }}" required></div>
        <div class="col-12 col-md-3"><label class="form-label fw-bold" for="item_status">Status Item</label>@include('components.item-status-multiselect', ['filter' => $filter])</div>
        <div class="col-12 col-md-2 d-grid"><button class="btn btn-report" type="submit">Analisis</button></div>
    </form>
    <div class="small text-secondary mt-2">MTD {{ $filter->currentStartDate->format('d-m-Y') }} s/d {{ $filter->currentEndDate->format('d-m-Y') }} dibanding LMTD {{ $filter->previousStartDate->format('d-m-Y') }} s/d {{ $filter->previousEndDate->format('d-m-Y') }}.</div>
</section>

@if ($error)<div class="alert alert-danger"><strong>Dashboard belum dapat dimuat.</strong><div class="small mt-1">{{ $error }}</div></div>@endif

@if (count($report->rows) > 0)
    @include('sales-insights.partials.management-insights', ['insights' => $insights])
    @include('sales-insights.partials.research-insights', ['research' => $research])
@else
    <div class="empty-state filter-panel"><div class="fw-bold mb-1">Tidak ada data yang dapat dianalisis</div><div>Periksa filter atau detail error di atas.</div></div>
@endif
@endsection

@push('scripts')
@if (count($report->rows) > 0)
<script>window.salesDashboardInsightData = {{ Illuminate\Support\Js::from($insights->chartData()) }};</script>
<script src="{{ asset('js/sales-dashboard-insights.js') }}"></script>
@endif
@endpush
