@extends('layouts.app')

@section('title', 'Review Penjualan 3 Bulan')

@php
    $number = static function (float $value): string {
        if (abs($value) < 0.5) return '-';
        $formatted = number_format(abs($value), 0, ',', '.');
        return $value < 0 ? '('.$formatted.')' : $formatted;
    };
    $percent = static fn (?float $value): string => $value === null
        ? '—'
        : number_format($value * 100, 2, ',', '.').'%';
    $growthClass = static fn (?float $value): string => $value === null
        ? 'growth-empty'
        : ($value >= 0 ? 'growth-up' : 'growth-down');
    $growthIcon = static fn (?float $value): string => $value === null
        ? ''
        : ($value > 0 ? '↑' : ($value < 0 ? '↓' : '→'));
    $compactCurrency = static function (float $value): string {
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';
        if ($abs >= 1000000000) return $sign.'Rp '.number_format($abs / 1000000000, 2, ',', '.').' M';
        if ($abs >= 1000000) return $sign.'Rp '.number_format($abs / 1000000, 1, ',', '.').' Jt';
        if ($abs >= 1000) return $sign.'Rp '.number_format($abs / 1000, 0, ',', '.').' Rb';
        return $sign.'Rp '.number_format($abs, 0, ',', '.');
    };
    $trendClass = static fn (string $trend): string => match ($trend) {
        'naik' => 'trend-up',
        'turun' => 'trend-down',
        default => 'trend-flat',
    };
    $trendIcon = static fn (string $trend): string => match ($trend) {
        'naik' => '↗',
        'turun' => '↘',
        default => '→',
    };
    $trendLabel = static fn (string $trend): string => ucfirst($trend);
    $exportParams = [
        'database' => $filter->database,
        'end_date' => $filter->selectedDate->format('Y-m-d'),
        'item_status' => $filter->itemStatus,
    ];
    $maxMonthTotal = max(
        1.0,
        abs($report->totalRow->firstMonthSales),
        abs($report->totalRow->secondMonthSales),
        abs($report->totalRow->currentMonthSales),
    );
    $barHeight = static fn (float $value): int => max(24, (int) round((abs($value) / $maxMonthTotal) * 160));
    $topRows = collect($report->rows)
        ->sortByDesc(static fn ($row) => $row->contributionPercentage ?? 0)
        ->take(5)
        ->values();
    $topContribution = max(0.000001, (float) ($topRows->first()?->contributionPercentage ?? 0.000001));
@endphp

@push('styles')
<style>
    .review-heading h1 { font-size:1.45rem; font-weight:900; margin:0 0 .25rem; }
    .review-subtitle { color:#667085; font-size:.82rem; }
    .review-card { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.05); padding:1rem; height:100%; }
    .review-card-label { color:#667085; font-size:.68rem; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
    .review-card-value { font-size:1.45rem; line-height:1.15; font-weight:900; margin:.45rem 0 .2rem; color:#101828; }
    .review-card-note { color:#667085; font-size:.72rem; }
    .review-chart { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.05); padding:1rem; height:100%; }
    .review-chart-title { font-size:.92rem; font-weight:900; margin-bottom:.9rem; }
    .review-bars { height:205px; border-left:1px solid #d5dae2; border-bottom:1px solid #d5dae2; display:flex; align-items:flex-end; gap:2rem; padding:1.4rem 1.4rem 0; }
    .review-bar-item { flex:1; min-width:0; text-align:center; }
    .review-bar { position:relative; min-height:24px; border-radius:8px 8px 0 0; background:#4472c4; }
    .review-bar.current { background:#ffc000; }
    .review-bar strong { position:absolute; top:-1.25rem; left:-.5rem; right:-.5rem; font-size:.7rem; white-space:nowrap; }
    .review-bar-label { margin-top:.45rem; font-size:.72rem; font-weight:900; }
    .review-rank-list { display:grid; gap:.65rem; }
    .review-rank-row { display:grid; grid-template-columns:26px minmax(0,1fr) 62px 65px; gap:.5rem; align-items:center; font-size:.72rem; }
    .review-rank-no { width:24px; height:24px; border-radius:7px; background:#f2f4f7; display:grid; place-items:center; font-weight:900; }
    .review-progress { height:7px; background:#eef2f6; border-radius:999px; overflow:hidden; margin-top:.25rem; }
    .review-progress > span { display:block; height:100%; background:#4472c4; }
    .review-table-panel { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.05); overflow:hidden; }
    .review-table-heading { padding:1rem; border-bottom:1px solid #d0d5dd; }
    .review-table-wrap { overflow:auto; max-height:64vh; }
    .review-table { border-collapse:collapse; min-width:1320px; width:100%; font-size:12px; font-variant-numeric:tabular-nums; }
    .review-table th,.review-table td { border:1px solid #1f2937; padding:6px 7px; white-space:nowrap; }
    .review-table thead th { position:sticky; top:0; z-index:5; background:#ffc000; color:#111; font-family:Georgia,'Times New Roman',serif; font-weight:900; text-align:center; vertical-align:middle; }
    .review-table thead tr:nth-child(2) th { top:34px; }
    .review-table .group-head { background:#ffd45e; }
    .review-table .line-head,.review-table .line-cell { position:sticky; left:0; z-index:4; font-weight:900; }
    .review-table .line-head { background:#ffc000; z-index:8; }
    .review-table .line-cell { background:#fff; }
    .review-table tbody tr:hover td { background:#fff8dc; }
    .review-table tbody tr:hover .line-cell { background:#fff8dc; }
    .review-table .number { text-align:right; }
    .review-table .center { text-align:center; }
    .review-table .total-row td { background:#f2f4f7 !important; font-weight:900; }
    .review-table .total-row .line-cell { background:#f2f4f7 !important; }
    .growth-up { color:#15803d; font-weight:900; }
    .growth-down { color:#dc3545; font-weight:900; }
    .growth-empty { color:#667085; font-weight:700; }
    .contribution-pill { display:inline-flex; min-width:62px; justify-content:center; border-radius:999px; padding:.2rem .45rem; background:#eef4ff; color:#3538cd; border:1px solid #c7d7fe; font-weight:900; }
    .trend-pill { display:inline-flex; min-width:76px; justify-content:center; border-radius:999px; padding:.2rem .5rem; font-weight:900; }
    .trend-up { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .trend-down { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    .trend-flat { background:#f2f4f7; color:#344054; border:1px solid #d0d5dd; }
    .review-note { color:#667085; font-size:.72rem; line-height:1.5; }
    @media (max-width: 767px) {
        .review-bars { gap:.7rem; padding-inline:.6rem; }
        .review-card-value { font-size:1.15rem; }
    }
</style>
@endpush

@section('content')
<div class="review-heading d-flex flex-column flex-xl-row justify-content-between gap-2 align-items-xl-start mb-3">
    <div>
        <h1>Review Penjualan 3 Bulan</h1>
        <div class="review-subtitle">Melihat tren dan perubahan omzet setiap lini secara ringkas selama 3 bulan terakhir.</div>
    </div>
    <span class="status-pill align-self-start">{{ $filter->itemStatusLabel() }}</span>
</div>

<section class="filter-panel p-3 p-lg-4 mb-3">
    <form method="GET" action="{{ route('sales-review-3-months.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label fw-bold" for="database">Database</label>
            <select class="form-select" id="database" name="database">
                <option value="all" @selected($filter->database === 'all')>INGCO &amp; SMI</option>
                <option value="ingco" @selected($filter->database === 'ingco')>INGCO — AS_INGCO</option>
                <option value="smi" @selected($filter->database === 'smi')>SMI — AS_SMI</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label fw-bold" for="end_date">Tanggal Akhir Review</label>
            <input class="form-control @error('end_date') is-invalid @enderror" type="date" id="end_date" name="end_date" value="{{ $filter->selectedDate->format('Y-m-d') }}" required>
            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label fw-bold" for="item_status">Filter Status Item</label>
            @include('components.item-status-multiselect', ['filter' => $filter])
        </div>
        <div class="col-12 col-md-6 col-xl-2 d-grid">
            <button class="btn btn-report" type="submit">Tampilkan Review</button>
        </div>
        <div class="col-12 col-xl-3">
            <div class="period-box">
                <div><strong>Periode:</strong> {{ $filter->periodStartDate->format('d-m-Y') }} s/d {{ $filter->selectedDate->format('d-m-Y') }}</div>
                <div>{{ $filter->firstMonthLabel() }} · {{ $filter->secondMonthLabel() }} · {{ $filter->currentMonthLabel() }}</div>
            </div>
        </div>
    </form>
</section>

@if ($errors->any())
    <div class="alert alert-danger"><strong>Filter belum valid.</strong><ul class="mb-0 mt-1">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif

@if ($error)
    <div class="alert alert-danger"><div class="fw-bold">Review belum dapat dimuat</div><div class="small mt-1">{{ $error }}</div></div>
@endif

@if (count($report->rows) > 0)
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="small text-secondary">
            <strong>{{ $filter->databaseLabel() }}</strong> · {{ $filter->comparisonNote() }}
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-success" href="{{ route('sales-review-3-months.export.excel', $exportParams) }}">Export Excel</a>
            <a class="btn btn-sm btn-outline-danger" href="{{ route('sales-review-3-months.export.pdf', $exportParams) }}">Export PDF</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="review-card">
                <div class="review-card-label">Total Sales 3 Bulan</div>
                <div class="review-card-value">{{ $compactCurrency($report->totalRow->totalSales) }}</div>
                <div class="review-card-note">Gabungan seluruh lini</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="review-card">
                <div class="review-card-label">Rata-rata / Bulan</div>
                <div class="review-card-value">{{ $compactCurrency($report->totalRow->averageMonthlySales) }}</div>
                <div class="review-card-note">Average omzet 3 periode</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="review-card">
                <div class="review-card-label">Growth Bulan Terakhir</div>
                <div class="review-card-value {{ $growthClass($report->totalRow->currentMonthGrowth) }}">{{ $growthIcon($report->totalRow->currentMonthGrowth) }} {{ $percent($report->totalRow->currentMonthGrowth) }}</div>
                <div class="review-card-note">{{ $filter->currentMonthLabel() }} vs periode pembanding</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="review-card">
                <div class="review-card-label">Kontributor Terbesar</div>
                <div class="review-card-value" style="font-size:1.05rem">{{ $report->topContributor?->line ?? '—' }}</div>
                <div class="review-card-note">{{ $percent($report->topContributor?->contributionPercentage) }} dari omzet 3 bulan</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="review-chart">
                <div class="review-chart-title">Trend Total Sales — 3 Bulan</div>
                <div class="review-bars">
                    <div class="review-bar-item">
                        <div class="review-bar" style="height:{{ $barHeight($report->totalRow->firstMonthSales) }}px"><strong>{{ $compactCurrency($report->totalRow->firstMonthSales) }}</strong></div>
                        <div class="review-bar-label">{{ $filter->firstMonthLabel() }}</div>
                    </div>
                    <div class="review-bar-item">
                        <div class="review-bar" style="height:{{ $barHeight($report->totalRow->secondMonthSales) }}px"><strong>{{ $compactCurrency($report->totalRow->secondMonthSales) }}</strong></div>
                        <div class="review-bar-label">{{ $filter->secondMonthLabel() }}</div>
                    </div>
                    <div class="review-bar-item">
                        <div class="review-bar current" style="height:{{ $barHeight($report->totalRow->currentMonthSales) }}px"><strong>{{ $compactCurrency($report->totalRow->currentMonthSales) }}</strong></div>
                        <div class="review-bar-label">{{ $filter->currentMonthLabel() }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="review-chart">
                <div class="review-chart-title">Top Kontribusi Lini — 3 Bulan</div>
                <div class="review-rank-list">
                    @forelse ($topRows as $index => $row)
                        @php($width = max(3, min(100, (int) round((($row->contributionPercentage ?? 0) / $topContribution) * 100))))
                        <div class="review-rank-row">
                            <div class="review-rank-no">{{ $index + 1 }}</div>
                            <div class="text-truncate">
                                <strong title="{{ $row->line }}">{{ $row->line }}</strong>
                                <div class="review-progress"><span style="width:{{ $width }}%"></span></div>
                            </div>
                            <strong>{{ $percent($row->contributionPercentage) }}</strong>
                            <span class="{{ $growthClass($row->currentMonthGrowth) }}">{{ $growthIcon($row->currentMonthGrowth) }} {{ $percent($row->currentMonthGrowth) }}</span>
                        </div>
                    @empty
                        <div class="small text-secondary">Belum ada data.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <section class="review-table-panel">
        <div class="review-table-heading d-flex flex-column flex-xl-row justify-content-between gap-2 align-items-xl-center">
            <div>
                <h2 class="h6 fw-bold mb-1">Review Performa per Lini</h2>
                <div class="small text-secondary">Fokus pada sales, growth, kontribusi, dan arah tren tanpa target.</div>
            </div>
            <div class="small text-secondary">Growth 3 Bulan memakai tanggal pembanding yang sama saat bulan terakhir masih berjalan.</div>
        </div>
        <div class="review-table-wrap">
            <table class="review-table" aria-label="Review penjualan tiga bulan per lini">
                <thead>
                <tr>
                    <th class="line-head" rowspan="2">LINI PENJUALAN</th>
                    <th class="group-head" colspan="2">{{ strtoupper($filter->firstMonthLabel()) }}</th>
                    <th class="group-head" colspan="2">{{ strtoupper($filter->secondMonthLabel()) }}</th>
                    <th class="group-head" colspan="2">{{ strtoupper($filter->currentMonthLabel()) }}</th>
                    <th class="group-head" colspan="5">REVIEW 3 BULAN</th>
                </tr>
                <tr>
                    <th>Sales</th><th>Growth</th>
                    <th>Sales</th><th>Growth</th>
                    <th>Sales</th><th>Growth</th>
                    <th>Total Sales</th><th>Avg / Bulan</th><th>Growth 3 Bulan</th><th>Contribution</th><th>Trend</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($report->rows as $row)
                    <tr>
                        <td class="line-cell">{{ $row->line }}</td>
                        <td class="number">{{ $number($row->firstMonthSales) }}</td>
                        <td class="center growth-empty">—</td>
                        <td class="number">{{ $number($row->secondMonthSales) }}</td>
                        <td class="center {{ $growthClass($row->secondMonthGrowth) }}">{{ $growthIcon($row->secondMonthGrowth) }} {{ $percent($row->secondMonthGrowth) }}</td>
                        <td class="number">{{ $number($row->currentMonthSales) }}</td>
                        <td class="center {{ $growthClass($row->currentMonthGrowth) }}">{{ $growthIcon($row->currentMonthGrowth) }} {{ $percent($row->currentMonthGrowth) }}</td>
                        <td class="number">{{ $number($row->totalSales) }}</td>
                        <td class="number">{{ $number($row->averageMonthlySales) }}</td>
                        <td class="center {{ $growthClass($row->threeMonthGrowth) }}">{{ $growthIcon($row->threeMonthGrowth) }} {{ $percent($row->threeMonthGrowth) }}</td>
                        <td class="center"><span class="contribution-pill">{{ $percent($row->contributionPercentage) }}</span></td>
                        <td class="center"><span class="trend-pill {{ $trendClass($row->trend) }}">{{ $trendIcon($row->trend) }} {{ $trendLabel($row->trend) }}</span></td>
                    </tr>
                @endforeach
                @php($row = $report->totalRow)
                <tr class="total-row">
                    <td class="line-cell">TOTAL</td>
                    <td class="number">{{ $number($row->firstMonthSales) }}</td>
                    <td class="center">—</td>
                    <td class="number">{{ $number($row->secondMonthSales) }}</td>
                    <td class="center {{ $growthClass($row->secondMonthGrowth) }}">{{ $growthIcon($row->secondMonthGrowth) }} {{ $percent($row->secondMonthGrowth) }}</td>
                    <td class="number">{{ $number($row->currentMonthSales) }}</td>
                    <td class="center {{ $growthClass($row->currentMonthGrowth) }}">{{ $growthIcon($row->currentMonthGrowth) }} {{ $percent($row->currentMonthGrowth) }}</td>
                    <td class="number">{{ $number($row->totalSales) }}</td>
                    <td class="number">{{ $number($row->averageMonthlySales) }}</td>
                    <td class="center {{ $growthClass($row->threeMonthGrowth) }}">{{ $growthIcon($row->threeMonthGrowth) }} {{ $percent($row->threeMonthGrowth) }}</td>
                    <td class="center">{{ $percent($row->contributionPercentage) }}</td>
                    <td class="center"><span class="trend-pill {{ $trendClass($row->trend) }}">{{ $trendIcon($row->trend) }} {{ $trendLabel($row->trend) }}</span></td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="review-note mt-2">
        <strong>Catatan:</strong> {{ $filter->comparisonNote() }} Total Sales dan Average tetap menggunakan periode nyata yang terlihat pada tabel: dua bulan sebelumnya penuh dan bulan terakhir sampai tanggal akhir review.
    </div>
@else
    <div class="empty-state filter-panel">
        <div class="fw-bold mb-1">Tidak ada data yang dapat ditampilkan</div>
        <div>Periksa filter atau detail error di atas.</div>
    </div>
@endif
@endsection
