@extends('layouts.app')

@section('title', 'Sales Report Dashboard')

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
        : ($value >= 0 ? '↑' : '↓');
    $achievementClass = static fn (?float $value): string => \App\Support\SalesAchievementColor::classFor($value);
    $dashboardRole = (string) session(config('dashboard_security.session_user_role_key'));
    $canExport = in_array($dashboardRole, ['admin', 'direksi', 'sales', 'rnd'], true);
    $exportParams = [
        'database' => $filter->database,
        'end_date' => $filter->selectedDate->format('Y-m-d'),
        'item_status' => $filter->itemStatus,
    ];
    $detailConfig = [
        'endpoint' => route('sales-dashboard.details'),
        'query' => $exportParams,
    ];
@endphp

@push('styles')
<style>
    .raw-data-card { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 8px 24px rgba(16,24,40,.06); padding:1rem; }
    .raw-table-wrap { overflow:auto; border:1px solid #d0d5dd; border-radius:10px; max-height:68vh; }
    .raw-table { min-width:2200px; margin:0; font-size:.75rem; font-variant-numeric:tabular-nums; }
    .raw-table th,.raw-table td { white-space:nowrap; vertical-align:middle; }
    .raw-table thead th { position:sticky; top:0; z-index:3; background:#101828; color:#fff; border-color:#344054; }
    .raw-data-loading { padding:2rem; text-align:center; color:#667085; font-weight:700; }
    .raw-pagination { display:flex; gap:.5rem; align-items:center; justify-content:flex-end; margin-top:.75rem; }
    .contribution-badge { display:inline-flex; min-width:64px; justify-content:center; border-radius:999px; padding:.2rem .5rem; background:#eef4ff; color:#3538cd; border:1px solid #c7d7fe; font-weight:800; }

    .report-table .achievement-cell.achievement-danger {
        background:#dc3545 !important;
        color:#fff !important;
    }
    .report-table .achievement-cell.achievement-warning {
        background:#facc15 !important;
        color:#111827 !important;
    }
    .report-table .achievement-cell.achievement-success {
        background:#15803d !important;
        color:#fff !important;
    }
    .report-table .achievement-cell.achievement-na {
        background:#e5e7eb !important;
        color:#4b5563 !important;
    }
</style>
@endpush

@section('content')
<section class="filter-panel p-3 p-lg-4 mb-3">
    <form method="GET" action="{{ route('sales-dashboard.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label fw-bold" for="database">Database</label>
            <select class="form-select" id="database" name="database">
                <option value="all" @selected($filter->database === 'all')>Semua: INGCO + WADFOW + SMI</option>
                <option value="ingco" @selected($filter->database === 'ingco')>INGCO — AS_INGCO</option>
                <option value="wadfow" @selected($filter->database === 'wadfow')>WADFOW — AS_SMI (brand WADFOW)</option>
                <option value="smi" @selected($filter->database === 'smi')>SMI — AS_SMI (non-WADFOW)</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label fw-bold" for="end_date">Tanggal Laporan</label>
            <input class="form-control @error('end_date') is-invalid @enderror" type="date" id="end_date" name="end_date" value="{{ $filter->selectedDate->format('Y-m-d') }}" required>
            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label fw-bold" for="item_status">Filter Status Item</label>
            @include('components.item-status-multiselect', ['filter' => $filter])
        </div>
        <div class="col-12 col-md-6 col-xl-2 d-grid">
            <button class="btn btn-report" type="submit">Tampilkan Laporan</button>
        </div>
        <div class="col-12 col-xl-3">
            <div class="period-box">
                <div><strong>MTD:</strong> {{ $filter->currentStartDate->format('d-m-Y') }} s/d {{ $filter->currentEndDate->format('d-m-Y') }}</div>
                <div><strong>LMTD:</strong> {{ $filter->previousStartDate->format('d-m-Y') }} s/d {{ $filter->previousEndDate->format('d-m-Y') }}</div>
            </div>
        </div>
    </form>
</section>

@if ($errors->any())
    <div class="alert alert-danger"><strong>Filter belum valid.</strong><ul class="mb-0 mt-1">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif

@if ($error)
    <div class="alert alert-danger"><div class="fw-bold">Laporan belum dapat dimuat</div><div class="small mt-1">{{ $error }}</div></div>
@endif

<div class="report-caption d-flex flex-column flex-xl-row gap-2 justify-content-between align-items-xl-center">
    <div>
        <div class="fw-bold">SALES REPORT — {{ $filter->databaseLabel() }}</div>
        <div class="small text-secondary">Tanggal {{ $filter->selectedDate->format('d-m-Y') }} · Total HK {{ $filter->totalSalesDays }} · HK To {{ $filter->elapsedDays }} · Sisa HK {{ $filter->remainingSalesDays }}</div>
        <div class="small fw-semibold mt-1">Sekarang: <span data-live-jakarta-clock>--</span></div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="status-pill">{{ $filter->itemStatusLabel() }}</span>
        @if ($canExport)
            <a class="btn btn-sm btn-primary" href="{{ route('sales-insights.index', $exportParams) }}">Dashboard Analisis Lanjutan</a>
            <a class="btn btn-sm btn-success" href="{{ route('sales-dashboard.export.excel', $exportParams) }}">Export Excel — Ringkasan &amp; Data Mentah</a>
            <a class="btn btn-sm btn-outline-danger" href="{{ route('sales-dashboard.export.pdf', $exportParams) }}">Export PDF Ringkasan</a>
        @endif
    </div>
</div>

@if (count($report->rows) > 0)
    <div class="report-table-wrap mb-4">
        <table class="report-table" aria-label="Sales report per lini">
            <thead>
            <tr>
                <th class="line-head" rowspan="2">INGCO &amp; SMI</th>
                <th rowspan="2">Monthly Sales<br>Target</th>
                <th rowspan="2">Daily Sales<br>Target / HK</th>
                <th rowspan="2">Required<br>Daily Sales</th>
                <th colspan="3">Daily Sales Ach<br>DTD Growth (%)</th>
                <th colspan="3">Monthly Sales Ach</th>
                <th rowspan="2">Avg Daily<br>Sales</th>
                <th rowspan="2">Est Ach EOM</th>
                <th rowspan="2">Est Ach<br>EOM (%)</th>
                <th rowspan="2">Sales Ach<br>VS Monthly<br>Target(%)</th>
                <th rowspan="2">Gap Sales 100%</th>
                <th rowspan="2">Contribution<br>ke Total Omzet</th>
            </tr>
            <tr>
                <th>DTD</th><th>LDTD</th><th>%Age<br>Penjualan/Day</th><th>MTD</th><th>LMTD</th><th>MTD Growth<br>(%)</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($report->rows as $row)
                <tr>
                    <td class="line-cell">{{ $row->line }}</td>
                    <td class="number">{{ $number($row->monthlyTarget) }}</td>
                    <td class="number">{{ $number($row->dailyTarget) }}</td>
                    <td class="number">{{ $number($row->requiredDailySales) }}</td>
                    <td class="number">{{ $number($row->dtd) }}</td>
                    <td class="number">{{ $number($row->ldtd) }}</td>
                    <td class="center {{ $growthClass($row->dtdGrowth) }}">{{ $growthIcon($row->dtdGrowth) }} {{ $percent($row->dtdGrowth) }}</td>
                    <td class="number">{{ $number($row->mtd) }}</td>
                    <td class="number">{{ $number($row->lmtd) }}</td>
                    <td class="center {{ $growthClass($row->mtdGrowth) }}">{{ $growthIcon($row->mtdGrowth) }} {{ $percent($row->mtdGrowth) }}</td>
                    <td class="number">{{ $number($row->averageDailySales) }}</td>
                    <td class="number">{{ $number($row->estimatedEom) }}</td>
                    <td class="center">{{ $percent($row->estimatedEomPercentage) }}</td>
                    <td class="achievement-cell {{ $achievementClass($row->achievementPercentage) }}">{{ $percent($row->achievementPercentage) }}</td>
                    <td class="number">{{ $number($row->gap) }}</td>
                    <td class="center"><span class="contribution-badge">{{ $percent($row->contributionPercentage) }}</span></td>
                </tr>
            @endforeach
            @php($row = $report->totalRow)
            <tr class="total-row">
                <td class="line-cell">TOTAL</td>
                <td class="number">{{ $number($row->monthlyTarget) }}</td><td class="number">{{ $number($row->dailyTarget) }}</td><td class="number">{{ $number($row->requiredDailySales) }}</td>
                <td class="number">{{ $number($row->dtd) }}</td><td class="number">{{ $number($row->ldtd) }}</td><td class="center">{{ $growthIcon($row->dtdGrowth) }} {{ $percent($row->dtdGrowth) }}</td>
                <td class="number">{{ $number($row->mtd) }}</td><td class="number">{{ $number($row->lmtd) }}</td><td class="center">{{ $growthIcon($row->mtdGrowth) }} {{ $percent($row->mtdGrowth) }}</td>
                <td class="number">{{ $number($row->averageDailySales) }}</td><td class="number">{{ $number($row->estimatedEom) }}</td><td class="center">{{ $percent($row->estimatedEomPercentage) }}</td>
                <td class="achievement-cell {{ $achievementClass($row->achievementPercentage) }}">{{ $percent($row->achievementPercentage) }}</td><td class="number">{{ $number($row->gap) }}</td><td class="center">{{ $percent($row->contributionPercentage) }}</td>
            </tr>
            </tbody>
        </table>
    </div>

    <section class="raw-data-card" data-sales-raw-table>
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Data Mentah Penjualan</h2>
                <div class="small text-secondary">Satu baris per item invoice dari periode MTD. Contribution menunjukkan kontribusi omzet LiniPenjualan terhadap total omzet sesuai filter utama.</div>
            </div>
            @if ($canExport)
                <a class="btn btn-sm btn-success align-self-xl-start" href="{{ route('sales-dashboard.export.excel', $exportParams) }}">Export Semua Data ke Excel</a>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
            <div><label class="form-label">Cari data</label><input type="search" class="form-control form-control-sm" data-raw-search placeholder="Invoice, customer, sales, lini, item"></div>
            <div><label class="form-label">Baris</label><select class="form-select form-select-sm" data-raw-length><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select></div>
            <div><label class="form-label">Urutkan</label><select class="form-select form-select-sm" data-raw-order><option value="invoice_date">Tanggal</option><option value="database">Database</option><option value="invoice_number">Invoice</option><option value="line">LiniPenjualan</option><option value="line_contribution">Contribution</option><option value="customer_name">Customer</option><option value="sales_person_name">Sales</option><option value="item_code">Kode item</option><option value="item_name">Nama item</option><option value="line_total">LineTotal</option></select></div>
            <div><label class="form-label">Arah</label><select class="form-select form-select-sm" data-raw-dir><option value="desc">Menurun</option><option value="asc">Menaik</option></select></div>
            <div class="small text-secondary ms-auto" data-raw-info></div>
        </div>
        <div class="alert alert-danger d-none" data-raw-error></div>
        <div class="raw-data-loading" data-raw-loading>Memuat data mentah…</div>
        <div class="raw-table-wrap d-none" data-raw-wrap>
            <table class="table table-sm table-bordered raw-table">
                <thead><tr>
                    <th>Database</th><th>InvoiceDate</th><th>InvoiceNumber</th><th>LiniPenjualan</th><th>Contribution Lini</th>
                    <th>CustomerCode</th><th>CustomerName</th><th>SalesPersonName</th><th>ItemCode</th><th>ItemName</th><th>Grade</th>
                    <th>Discontinue</th><th>StatusItem</th><th>Item Reject</th><th>Status Item Reject</th><th>Reject Discount</th>
                    <th>Quantity</th><th>Discount</th><th>Discount2</th><th>PriceLevelName</th><th>LineTotal</th><th>Remarks</th>
                </tr></thead>
                <tbody data-raw-body></tbody>
            </table>
        </div>
        <div class="raw-pagination"><button type="button" class="btn btn-sm btn-outline-secondary" data-raw-prev>Sebelumnya</button><button type="button" class="btn btn-sm btn-outline-secondary" data-raw-next>Berikutnya</button></div>
    </section>
@else
    <div class="empty-state filter-panel"><div class="fw-bold mb-1">Tidak ada data yang dapat ditampilkan</div><div>Periksa filter atau detail error di atas.</div></div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const clock = document.querySelector('[data-live-jakarta-clock]');
    if (!clock) return;

    const formatter = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    const updateClock = function () {
        const values = {};
        formatter.formatToParts(new Date()).forEach(function (part) {
            if (part.type !== 'literal') values[part.type] = part.value;
        });
        clock.textContent = `${values.day}-${values.month}-${values.year} ${values.hour}:${values.minute}:${values.second} WIB`;
    };

    updateClock();
    window.setInterval(updateClock, 1000);
});
</script>
@if (count($report->rows) > 0)
<script>window.salesDashboardTableConfig = {{ Illuminate\Support\Js::from($detailConfig) }};</script>
<script src="{{ asset('js/sales-dashboard-datatable.js') }}"></script>
@endif
@endpush
