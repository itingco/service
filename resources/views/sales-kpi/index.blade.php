@extends('layouts.app')

@section('title', 'Sales KPI Dashboard')

@php
    $money = static fn (?float $value): string => $value === null ? 'N/A' : 'Rp '.number_format($value, 0, ',', '.');
    $number = static fn (?float $value, int $decimals = 2): string => $value === null ? 'N/A' : number_format($value, $decimals, ',', '.');
    $percent = static fn (?float $value): string => $value === null ? 'N/A' : number_format($value * 100, 2, ',', '.').'%';
    $statusClass = static fn (string $status): string => match ($status) {
        'Baik', 'Tercapai' => 'kpi-status-good',
        'Perlu Perhatian', 'Belum Tercapai' => 'kpi-status-warning',
        'Kurang' => 'kpi-status-bad',
        default => 'kpi-status-na',
    };
    $scoreStatus = static fn (?float $value, float $maximum): string => match (true) {
        $value === null => 'Tidak dapat dihitung',
        $value >= $maximum - 0.0001 => 'Tercapai',
        default => 'Belum Tercapai',
    };
    $overallStatus = match (true) {
        $report->summary['overall'] === null => 'Tidak dapat dihitung',
        $report->summary['overall'] >= 80 => 'Baik',
        $report->summary['overall'] >= 60 => 'Perlu Perhatian',
        default => 'Kurang',
    };
    $query = $filter->query();
    $dashboardRole = (string) session(config('dashboard_security.session_user_role_key'));
    $canExport = in_array($dashboardRole, ['admin', 'direksi', 'sales', 'rnd'], true);
    $isSecurityAdmin = $dashboardRole === 'admin';
    $tableConfig = ['endpoint' => route('sales-kpi.details'), 'exportEndpoint' => route('sales-kpi.details.export.excel'), 'query' => $query, 'combined' => true];
@endphp

@push('styles')
<style>
    .kpi-hero { position:relative; overflow:hidden; border-radius:18px; background:linear-gradient(120deg,#101828,#344054); color:#fff; padding:1.3rem 1.5rem; box-shadow:0 16px 35px rgba(16,24,40,.18); }
    .kpi-hero::after { content:''; position:absolute; width:240px; height:240px; right:-90px; top:-120px; border-radius:50%; background:rgba(255,192,0,.20); }
    .kpi-hero>* { position:relative; z-index:1; }
    .kpi-eyebrow { color:#f8d34f; font-weight:800; letter-spacing:.08em; font-size:.72rem; text-transform:uppercase; }
    .kpi-filter-card,.kpi-section { border:1px solid #d0d5dd; border-radius:16px; background:#fff; box-shadow:0 10px 28px rgba(16,24,40,.07); }
    .kpi-card { position:relative; overflow:hidden; height:100%; border:1px solid #e4e7ec; border-radius:16px; background:#fff; box-shadow:0 8px 24px rgba(16,24,40,.06); padding:1rem 1.05rem; }
    .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; background:var(--yellow); }
    .kpi-card-label { color:#667085; font-size:.76rem; font-weight:800; text-transform:uppercase; letter-spacing:.035em; }
    .kpi-card-value { color:#101828; font-size:1.65rem; font-weight:900; line-height:1.1; margin-top:.5rem; }
    .kpi-card-max { color:#98a2b3; font-size:.78rem; font-weight:700; }
    .kpi-status-badge { display:inline-flex; padding:.28rem .58rem; border:1px solid; border-radius:999px; font-size:.7rem; font-weight:800; margin-top:.65rem; }
    .kpi-note { border:1px dashed #e0b000; background:#fffaeb; color:#7a5600; border-radius:12px; padding:.75rem 1rem; font-size:.82rem; font-weight:700; }
    .kpi-tabs { border-bottom:0; gap:.45rem; flex-wrap:nowrap; overflow-x:auto; padding-bottom:.2rem; }
    .kpi-tabs .nav-link { white-space:nowrap; border:1px solid #d0d5dd; border-radius:999px; background:#fff; color:#344054; font-weight:800; padding:.55rem .95rem; }
    .kpi-tabs .nav-link.active { background:#101828; border-color:#101828; color:#fff; }
    .kpi-section { padding:1rem; }
    .kpi-section-title { font-size:1rem; font-weight:900; color:#101828; margin:0; }
    .kpi-section-subtitle { font-size:.78rem; color:#667085; margin-top:.15rem; }
    .kpi-chart-wrap { position:relative; min-height:280px; overflow-x:auto; }
    .kpi-chart-wrap canvas { height:320px; display:block; }
    .kpi-chart-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.5fr); gap:1rem; }
    .kpi-pie-wrap canvas { margin:0 auto; }
    @media (max-width: 991px) { .kpi-chart-grid { grid-template-columns:1fr; } }
    .kpi-table-wrap { overflow:auto; border:1px solid #d0d5dd; border-radius:12px; max-height:64vh; }
    .kpi-table { min-width:1180px; margin:0; font-size:.76rem; font-variant-numeric:tabular-nums; }
    .kpi-table th,.kpi-table td { white-space:nowrap; vertical-align:middle; }
    .kpi-table thead th { position:sticky; top:0; z-index:3; background:#101828; color:#fff; border-color:#344054; }
    .kpi-table th[data-sort-key] { cursor:pointer; user-select:none; }
    .kpi-table th[data-sort-key]::after { content:' ↕'; color:#98a2b3; }
    .kpi-table .sticky-line { position:sticky; left:0; z-index:2; background:#fff; font-weight:800; }
    .kpi-table thead .sticky-line { z-index:5; background:#101828; }
    .kpi-table tbody tr:hover td,.kpi-table tbody tr:hover .sticky-line { background:#fffaeb; }
    .target-missing { display:inline-flex; border:1px solid #f5c451; border-radius:999px; padding:.2rem .5rem; color:#92400e; background:#fef3c7; font-size:.68rem; font-weight:800; }
    .detail-filter { background:#f8fafc; border:1px solid #e4e7ec; border-radius:14px; padding:1rem; }
    .tab-pane { padding-top:1rem; }
    .kpi-table-pagination { display:flex; gap:.5rem; align-items:center; justify-content:flex-end; margin-top:.75rem; }
    .kpi-detail-error { white-space:pre-line; }
</style>
@endpush

@section('content')
<div class="kpi-hero mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
        <div>
            <div class="kpi-eyebrow">Management Performance</div>
            <h1 class="h3 fw-black mb-1">Sales KPI Dashboard</h1>
            <div class="text-white-50">Data selalu merupakan gabungan <strong class="text-white">AS_INGCO + AS_SMI</strong> berdasarkan LiniPenjualan.</div>
        </div>
        <div class="text-lg-end small">
            <div class="fw-bold">AS_INGCO + AS_SMI</div>
            <div class="text-white-50">{{ $report->period->currentStart->format('d-m-Y') }} s.d. {{ $report->period->currentEnd->format('d-m-Y') }}</div>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('sales-kpi.index') }}" class="kpi-filter-card p-3 mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-md-3 col-xl-2">
            <label for="end_date" class="form-label fw-bold">Tanggal akhir</label>
            <input id="end_date" type="date" name="end_date" value="{{ $filter->selectedDate->format('Y-m-d') }}" class="form-control" required>
        </div>
        <div class="col-md-5 col-xl-6">
            <div class="period-box h-100">
                <strong>Periode KPI:</strong> {{ $report->period->currentStart->format('d-m-Y') }}–{{ $report->period->currentEnd->format('d-m-Y') }}<br>
                <strong>Growth sebanding:</strong> {{ $report->period->growthCurrentStart->format('d-m-Y') }}–{{ $report->period->growthCurrentEnd->format('d-m-Y') }}
                vs {{ $report->period->growthPreviousStart->format('d-m-Y') }}–{{ $report->period->growthPreviousEnd->format('d-m-Y') }}
            </div>
        </div>
        <div class="col-md-4 col-xl-4 d-flex flex-wrap gap-2 justify-content-md-end">
            <button type="submit" class="btn btn-report">Tampilkan</button>
            @if ($canExport)
                <a class="btn btn-success" data-kpi-export-link href="{{ route('sales-kpi.export.excel', $query) }}">Excel</a>
                <a class="btn btn-danger" data-kpi-export-link href="{{ route('sales-kpi.export.pdf', $query) }}">PDF</a>
            @endif
            @if ($isSecurityAdmin)
                <a class="btn btn-outline-dark" href="{{ route('sales-kpi.targets.index') }}">Pengaturan Target</a>
            @endif
        </div>
    </div>

    <div class="detail-filter mt-3">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
            <div>
                <div class="fw-bold">Filter LiniPenjualan dan Detail</div>
                <div class="small text-secondary"><strong>LiniPenjualan</strong> membatasi seluruh dashboard, skor KPI, grafik, dan ekspor. Filter lainnya hanya membatasi transaksi/detail item.</div>
            </div>
            <a href="{{ route('sales-kpi.index', ['end_date' => $filter->selectedDate->format('Y-m-d'), 'line' => $filter->line ?? '']) }}" class="btn btn-sm btn-outline-secondary align-self-md-start">Reset filter detail</a>
        </div>
        <div class="row g-2">
            <div class="col-md-3 col-xl-2">
                <label for="line" class="form-label">LiniPenjualan</label>
                <select id="line" name="line" class="form-select form-select-sm">
                    <option value="">Semua lini</option>
                    @foreach ($report->availableLines as $lineKey => $lineLabel)
                        <option value="{{ $lineLabel }}" @selected($filter->lineKey() === $lineKey)>{{ $lineLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label for="discontinue" class="form-label">Discontinued</label><select id="discontinue" name="discontinue" class="form-select form-select-sm"><option value="all" @selected($filter->discontinue === 'all')>Semua</option><option value="yes" @selected($filter->discontinue === 'yes')>Ya</option><option value="no" @selected($filter->discontinue === 'no')>Tidak</option></select></div>
            <div class="col-md-2"><label for="clearance" class="form-label">Item Reject</label><select id="clearance" name="clearance" class="form-select form-select-sm"><option value="all" @selected($filter->clearance === 'all')>Semua</option><option value="yes" @selected($filter->clearance === 'yes')>Item Reject</option><option value="no" @selected($filter->clearance === 'no')>Bukan Item Reject</option></select></div>
            <div class="col-md-2"><label for="grade" class="form-label">Grade</label><select id="grade" name="grade" class="form-select form-select-sm">@foreach (['ALL'=>'Semua','A'=>'A','B'=>'B','C'=>'C','D'=>'D','E'=>'E','N'=>'N','BLANK'=>'Kosong'] as $value=>$label)<option value="{{ $value }}" @selected($filter->grade === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3 col-xl-4"><label for="search" class="form-label">Kode/nama item</label><input id="search" name="search" value="{{ $filter->search }}" class="form-control form-control-sm" placeholder="Cari transaksi untuk detail dan ekspor"></div>
        </div>
    </div>
</form>

@if ($error)<div class="alert alert-danger kpi-detail-error">{{ $error }}</div>@endif

<div class="row g-3 mb-3">
@foreach ([['Skor KPI Keseluruhan',$report->summary['overall'],90,$overallStatus],['Target Omzet',$report->summary['target'],40,$scoreStatus($report->summary['target'],40)],['Growth',$report->summary['growth'],10,$scoreStatus($report->summary['growth'],10)],['Discontinued/Item Reject',$report->summary['risk'],20,$scoreStatus($report->summary['risk'],20)],['Grade A/B',$report->summary['grade'],20,$scoreStatus($report->summary['grade'],20)]] as [$label,$value,$maximum,$status])
<div class="col-md-6 col-xl"><div class="kpi-card"><div class="kpi-card-label">{{ $label }}</div><div class="kpi-card-value">{{ $number($value) }}</div><div class="kpi-card-max">maksimum {{ $maximum }} poin</div><span class="kpi-status-badge {{ $statusClass($status) }}">{{ $status }}</span></div></div>
@endforeach
</div>
<div class="kpi-note mb-3">Maksimum skor sistem: 90 poin. Sisa 10% monitoring harian berada di luar sistem.</div>

@if ($report->isEmpty())
<div class="kpi-section empty-state">Tidak ada data penjualan pada periode yang dipilih.</div>
@else
<ul class="nav nav-tabs kpi-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#kpi-summary" type="button">Ringkasan KPI</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpi-target" type="button">Target Omzet</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpi-growth" type="button">Growth vs Last Month</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpi-risk" type="button">Discontinued &amp; Item Reject</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpi-grade" type="button">Grade A &amp; Grade B</button></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="kpi-summary">
        <div class="kpi-chart-grid mb-3">
            <section class="kpi-section kpi-pie-wrap">
                <h2 class="kpi-section-title">Kontribusi Omzet per LiniPenjualan</h2>
                <div class="kpi-section-subtitle mb-2">Proporsi omzet aktual setiap lini terhadap total omzet gabungan.</div>
                <div class="kpi-chart-wrap"><canvas id="kpiContributionPieChart" aria-label="Pie chart kontribusi omzet"></canvas></div>
            </section>
            <section class="kpi-section">
                <h2 class="kpi-section-title">Skor KPI per LiniPenjualan</h2>
                <div class="kpi-section-subtitle mb-2">Bar vertikal menampilkan skor akhir setiap lini pada skala 0–90, dengan batas status 60 dan 80.</div>
                <div class="kpi-chart-wrap"><canvas id="kpiSummaryBarChart" aria-label="Bar chart achievement dan skor KPI"></canvas></div>
            </section>
        </div>
        <div class="kpi-section mb-3" data-kpi-table-block>
            <div class="mb-3"><h2 class="kpi-section-title">Ringkasan KPI per LiniPenjualan</h2><div class="kpi-section-subtitle">Target adalah satu nilai gabungan; omzet sumber tetap ditampilkan untuk penelusuran.</div></div>
            @include('sales-kpi.partials.recap-controls')
            <div class="kpi-table-wrap"><table class="table table-sm table-bordered kpi-table" data-kpi-recap-table><thead><tr><th class="sticky-line" data-sort-key="line" data-sort-type="text">LiniPenjualan</th><th data-sort-key="target" data-sort-type="numeric">Target Gabungan</th><th data-sort-key="omzet_ingco" data-sort-type="numeric">Omzet AS_INGCO</th><th data-sort-key="omzet_smi" data-sort-type="numeric">Omzet AS_SMI</th><th data-sort-key="omzet" data-sort-type="numeric">Omzet Gabungan</th><th data-sort-key="target_score" data-sort-type="numeric">Skor Target</th><th data-sort-key="growth_score" data-sort-type="numeric">Skor Growth</th><th data-sort-key="risk_score" data-sort-type="numeric">Skor Disc./Item Reject</th><th data-sort-key="grade_score" data-sort-type="numeric">Skor Grade A/B</th><th data-sort-key="score" data-sort-type="numeric">Skor Akhir</th><th data-sort-key="status" data-sort-type="text">Status</th></tr></thead><tbody>
            @foreach ($report->rows as $row) @php($ingco=$row->source('AS_INGCO')) @php($smi=$row->source('AS_SMI'))
            <tr data-row><td class="sticky-line">{{ $row->line }} @if (!$row->targetConfigured)<div class="mt-1"><span class="target-missing">Target belum diatur untuk LiniPenjualan: {{ $row->line }}</span></div>@endif</td><td data-sort-value="{{ $row->target ?? -1 }}">{{ $money($row->target) }}</td><td data-sort-value="{{ $ingco->currentOmzet }}">{{ $money($ingco->currentOmzet) }}</td><td data-sort-value="{{ $smi->currentOmzet }}">{{ $money($smi->currentOmzet) }}</td><td data-sort-value="{{ $row->currentOmzet }}">{{ $money($row->currentOmzet) }}</td><td data-sort-value="{{ $row->targetScore ?? -1 }}">{{ $number($row->targetScore) }}/40</td><td data-sort-value="{{ $row->growthScore ?? -1 }}">{{ $number($row->growthScore) }}/10</td><td data-sort-value="{{ $row->riskScore ?? -1 }}">{{ $number($row->riskScore) }}/20</td><td data-sort-value="{{ $row->gradeScore() ?? -1 }}">{{ $number($row->gradeScore()) }}/20</td><td data-sort-value="{{ $row->normalizedScore ?? -1 }}"><strong>{{ $number($row->normalizedScore) }}/90</strong></td><td><span class="kpi-status-badge {{ $statusClass($row->status) }} mt-0">{{ $row->status }}</span></td></tr>
            @endforeach
            </tbody></table></div>@include('sales-kpi.partials.recap-pagination')
        </div>
        <div class="kpi-section" data-kpi-detail-table data-detail-type="all"><h2 class="kpi-section-title">Detail Semua Transaksi</h2><div class="kpi-section-subtitle mb-3">Data dimuat per halaman dari server; ekspor mengambil seluruh hasil filter.</div>@include('sales-kpi.partials.detail-table', ['showDatabase'=>true, 'detailType'=>'all'])</div>
    </div>

    <div class="tab-pane fade" id="kpi-target">
        <div class="kpi-section mb-3"><h2 class="kpi-section-title">Target vs Omzet Aktual</h2><div class="kpi-chart-wrap"><canvas id="targetKpiChart"></canvas></div></div>
        <div class="kpi-section" data-kpi-table-block>@include('sales-kpi.partials.recap-controls')<div class="kpi-table-wrap"><table class="table table-sm table-bordered kpi-table" data-kpi-recap-table><thead><tr><th class="sticky-line" data-sort-key="line" data-sort-type="text">LiniPenjualan</th><th data-sort-key="target" data-sort-type="numeric">Target Gabungan</th><th data-sort-key="omzet_ingco" data-sort-type="numeric">Omzet AS_INGCO</th><th data-sort-key="omzet_smi" data-sort-type="numeric">Omzet AS_SMI</th><th data-sort-key="omzet" data-sort-type="numeric">Omzet Gabungan</th><th data-sort-key="threshold" data-sort-type="numeric">Batas 70%</th><th data-sort-key="achievement" data-sort-type="numeric">Achievement</th><th data-sort-key="gap70" data-sort-type="numeric">Gap ke 70%</th><th data-sort-key="gap100" data-sort-type="numeric">Gap ke 100%</th><th data-sort-key="score" data-sort-type="numeric">Skor /40</th></tr></thead><tbody>
        @foreach ($report->rows as $row) @php($ingco=$row->source('AS_INGCO')) @php($smi=$row->source('AS_SMI'))<tr data-row><td class="sticky-line">{{ $row->line }}</td><td data-sort-value="{{ $row->target ?? -1 }}">{{ $money($row->target) }}</td><td data-sort-value="{{ $ingco->currentOmzet }}">{{ $money($ingco->currentOmzet) }}</td><td data-sort-value="{{ $smi->currentOmzet }}">{{ $money($smi->currentOmzet) }}</td><td data-sort-value="{{ $row->currentOmzet }}">{{ $money($row->currentOmzet) }}</td><td data-sort-value="{{ $row->targetThreshold() ?? -1 }}">{{ $money($row->targetThreshold()) }}</td><td data-sort-value="{{ $row->targetAchievement() ?? -1 }}">{{ $percent($row->targetAchievement()) }}</td><td data-sort-value="{{ $row->gapToThreshold() ?? -1 }}">{{ $money($row->gapToThreshold()) }}</td><td data-sort-value="{{ $row->gapToTarget() ?? -1 }}">{{ $money($row->gapToTarget()) }}</td><td data-sort-value="{{ $row->targetScore ?? -1 }}">{{ $number($row->targetScore) }}</td></tr>@endforeach
        </tbody></table></div>@include('sales-kpi.partials.recap-pagination')</div>
    </div>

    <div class="tab-pane fade" id="kpi-growth">
        <div class="kpi-section mb-3"><h2 class="kpi-section-title">Growth Periode Sebanding</h2><div class="kpi-chart-wrap"><canvas id="growthKpiChart"></canvas></div></div>
        <div class="kpi-section" data-kpi-table-block>@include('sales-kpi.partials.recap-controls')<div class="kpi-table-wrap"><table class="table table-sm table-bordered kpi-table" data-kpi-recap-table><thead><tr><th class="sticky-line" data-sort-key="line" data-sort-type="text">LiniPenjualan</th><th>Sekarang AS_INGCO</th><th>Sekarang AS_SMI</th><th>Sekarang Gabungan</th><th>Bulan Lalu AS_INGCO</th><th>Bulan Lalu AS_SMI</th><th>Bulan Lalu Gabungan</th><th>Selisih</th><th>Growth</th><th>Target Growth</th><th>Skor /10</th></tr></thead><tbody>
        @foreach ($report->rows as $row) @php($ingco=$row->source('AS_INGCO')) @php($smi=$row->source('AS_SMI'))<tr data-row><td class="sticky-line">{{ $row->line }}</td><td>{{ $money($ingco->growthCurrentOmzet) }}</td><td>{{ $money($smi->growthCurrentOmzet) }}</td><td>{{ $money($row->growthCurrentOmzet) }}</td><td>{{ $money($ingco->previousOmzet) }}</td><td>{{ $money($smi->previousOmzet) }}</td><td>{{ $money($row->previousOmzet) }}</td><td>{{ $money($row->growthDifference()) }}</td><td>{{ $percent($row->growthRate) }}</td><td>10%</td><td>{{ $number($row->growthScore) }}</td></tr>@endforeach
        </tbody></table></div>@include('sales-kpi.partials.recap-pagination')</div>
    </div>

    <div class="tab-pane fade" id="kpi-risk">
        <div class="kpi-section mb-3"><h2 class="kpi-section-title">Discontinued &amp; Item Reject</h2><div class="kpi-chart-wrap"><canvas id="riskKpiChart"></canvas></div></div>
        <div class="kpi-section mb-3" data-kpi-table-block>@include('sales-kpi.partials.recap-controls')<div class="kpi-table-wrap"><table class="table table-sm table-bordered kpi-table" data-kpi-recap-table><thead><tr><th class="sticky-line">LiniPenjualan</th><th>Discontinued AS_INGCO</th><th>Discontinued AS_SMI</th><th>Item Reject AS_INGCO</th><th>Item Reject AS_SMI</th><th>Total Realisasi KPI</th><th>Target 9%</th><th>Achievement</th><th>Skor /20</th></tr></thead><tbody>
        @foreach ($report->rows as $row) @php($ingco=$row->source('AS_INGCO')) @php($smi=$row->source('AS_SMI'))<tr data-row><td class="sticky-line">{{ $row->line }}</td><td>{{ $money($ingco->discontinuedOmzet) }}</td><td>{{ $money($smi->discontinuedOmzet) }}</td><td>{{ $money($ingco->clearanceOmzet) }}</td><td>{{ $money($smi->clearanceOmzet) }}</td><td>{{ $money($row->riskRealization) }}</td><td>{{ $money($row->riskTarget) }}</td><td>{{ $percent($row->riskAchievement()) }}</td><td>{{ $number($row->riskScore) }}</td></tr>@endforeach
        </tbody></table></div>@include('sales-kpi.partials.recap-pagination')</div>
        <div class="kpi-section" data-kpi-detail-table data-detail-type="risk"><h2 class="kpi-section-title">Detail Item Discontinued/Item Reject</h2>@include('sales-kpi.partials.detail-table', ['showDatabase'=>true, 'detailType'=>'risk'])</div>
    </div>

    <div class="tab-pane fade" id="kpi-grade">
        <div class="kpi-section mb-3"><h2 class="kpi-section-title">Kontribusi Grade A &amp; B</h2><div class="kpi-chart-wrap"><canvas id="gradeKpiChart"></canvas></div></div>
        <div class="kpi-section mb-3" data-kpi-table-block>@include('sales-kpi.partials.recap-controls')<div class="kpi-table-wrap"><table class="table table-sm table-bordered kpi-table" data-kpi-recap-table><thead><tr><th class="sticky-line">LiniPenjualan</th><th>Total Omzet</th><th>Grade A AS_INGCO</th><th>Grade A AS_SMI</th><th>Grade A Gabungan</th><th>Kontribusi A</th><th>Skor A /12</th><th>Grade B AS_INGCO</th><th>Grade B AS_SMI</th><th>Grade B Gabungan</th><th>Kontribusi B</th><th>Skor B /8</th><th>Total /20</th></tr></thead><tbody>
        @foreach ($report->rows as $row) @php($ingco=$row->source('AS_INGCO')) @php($smi=$row->source('AS_SMI'))<tr data-row><td class="sticky-line">{{ $row->line }}</td><td>{{ $money($row->currentOmzet) }}</td><td>{{ $money($ingco->gradeAOmzet) }}</td><td>{{ $money($smi->gradeAOmzet) }}</td><td>{{ $money($row->gradeAOmzet) }}</td><td>{{ $percent($row->gradeAContribution) }}</td><td>{{ $number($row->gradeAScore) }}</td><td>{{ $money($ingco->gradeBOmzet) }}</td><td>{{ $money($smi->gradeBOmzet) }}</td><td>{{ $money($row->gradeBOmzet) }}</td><td>{{ $percent($row->gradeBContribution) }}</td><td>{{ $number($row->gradeBScore) }}</td><td>{{ $number($row->gradeScore()) }}</td></tr>@endforeach
        </tbody></table></div>@include('sales-kpi.partials.recap-pagination')</div>
        <div class="kpi-section" data-kpi-detail-table data-detail-type="grade"><h2 class="kpi-section-title">Detail Item Grade A/B</h2>@include('sales-kpi.partials.detail-table', ['showDatabase'=>true, 'detailType'=>'grade'])</div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>window.salesKpiData={{ Illuminate\Support\Js::from($report->chartData()) }};window.salesKpiTableConfig={{ Illuminate\Support\Js::from($tableConfig) }};</script>
<script src="{{ asset('js/sales-kpi-charts.js') }}"></script>
<script src="{{ asset('js/sales-kpi-datatable.js') }}"></script>
@endpush
