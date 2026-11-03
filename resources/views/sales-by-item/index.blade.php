@extends('layouts.app')

@section('title', 'Sales By Item')

@section('content')
@php
    $rows = collect($report->rows);

    $topSalesRows = $rows
        ->sortByDesc(fn ($row) => (float) $row->totalSales)
        ->take(10)
        ->values();

    $maxTopSales = max(1, (float) ($topSalesRows->max(fn ($row) => (float) $row->totalSales) ?? 1));

    $sourceColors = [
        'INGCO' => '#f59e0b',
        'SMI' => '#2563eb',
        'INGCO & SMI' => '#7c3aed',
    ];

    $sourceTotals = [
        'INGCO' => 0,
        'SMI' => 0,
        'INGCO & SMI' => 0,
    ];

    foreach ($rows as $row) {
        $source = array_key_exists($row->source, $sourceTotals)
            ? $row->source
            : 'INGCO & SMI';

        $sourceTotals[$source] += (float) $row->totalSales;
    }

    $sourceGrandTotal = max(0, array_sum($sourceTotals));
    $gradientParts = [];
    $gradientStart = 0;

    foreach ($sourceTotals as $source => $value) {
        $percentage = $sourceGrandTotal > 0 ? ($value / $sourceGrandTotal) * 100 : 0;
        $gradientEnd = $gradientStart + $percentage;
        $gradientParts[] = sprintf(
            '%s %.4f%% %.4f%%',
            $sourceColors[$source],
            $gradientStart,
            $gradientEnd
        );
        $gradientStart = $gradientEnd;
    }

    $donutBackground = $sourceGrandTotal > 0
        ? 'conic-gradient('.implode(', ', $gradientParts).')'
        : 'conic-gradient(#e5e7eb 0 100%)';

    $averagePrice = $report->totalQuantity > 0
        ? $report->totalSales / $report->totalQuantity
        : 0;
@endphp

<style>
    :root {
        --dashboard-yellow: #ffc000;
        --dashboard-yellow-soft: #fff4c2;
        --dashboard-red: #cc0000;
        --dashboard-green: #179447;
        --dashboard-blue: #2563eb;
        --dashboard-navy: #15324b;
        --dashboard-border: #202020;
        --dashboard-muted: #64748b;
        --dashboard-surface: #ffffff;
        --dashboard-bg: #f4f6f8;
    }

    .sales-dashboard {
        color: #172033;
    }

    .sales-dashboard .card {
        border: 1px solid #dfe4ea;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .055);
    }

    .dashboard-title {
        font-weight: 800;
        letter-spacing: -.02em;
        color: var(--dashboard-navy);
    }

    .dashboard-subtitle {
        color: var(--dashboard-muted);
        font-size: .9rem;
    }

    .filter-panel {
        overflow: hidden;
        border-top: 5px solid var(--dashboard-yellow) !important;
    }

    .filter-panel .form-label {
        color: #334155;
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .filter-panel .form-control,
    .filter-panel .form-select {
        min-height: 44px;
        border-color: #cfd7df;
    }

    .filter-panel .btn-primary {
        min-height: 44px;
        background: var(--dashboard-navy);
        border-color: var(--dashboard-navy);
        font-weight: 700;
    }

    .period-chip {
        display: inline-flex;
        align-items: center;
        min-height: 42px;
        padding: .55rem .9rem;
        border: 1px solid #e1b000;
        border-radius: 9px;
        background: var(--dashboard-yellow-soft);
        color: #674c00;
        font-size: .86rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border: 1px solid var(--dashboard-border);
        background: var(--dashboard-surface);
    }

    .summary-cell {
        min-width: 0;
        border-right: 1px solid var(--dashboard-border);
    }

    .summary-cell:last-child {
        border-right: 0;
    }

    .summary-label {
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .45rem .7rem;
        border-bottom: 1px solid var(--dashboard-border);
        background: var(--dashboard-yellow);
        color: #111827;
        font-size: .76rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
        line-height: 1.2;
    }

    .summary-value {
        min-height: 72px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .8rem;
        font-size: clamp(1rem, 1.9vw, 1.55rem);
        font-weight: 850;
        color: #111827;
        text-align: center;
        overflow-wrap: anywhere;
    }

    .chart-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(300px, .75fr);
        gap: 1rem;
    }

    .chart-card {
        min-height: 390px;
    }

    .chart-card .card-header {
        background: transparent;
        border-bottom: 1px solid #edf0f3;
    }

    .chart-title {
        margin: 0;
        color: var(--dashboard-navy);
        font-size: 1rem;
        font-weight: 800;
    }

    .chart-caption {
        margin-top: .2rem;
        color: var(--dashboard-muted);
        font-size: .78rem;
    }

    .bar-chart {
        display: flex;
        flex-direction: column;
        gap: .85rem;
    }

    .bar-row {
        display: grid;
        grid-template-columns: minmax(88px, 126px) minmax(120px, 1fr) minmax(108px, auto);
        align-items: center;
        gap: .8rem;
    }

    .bar-label {
        overflow: hidden;
        color: #334155;
        font-size: .8rem;
        font-weight: 750;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .bar-track {
        height: 16px;
        overflow: hidden;
        border-radius: 999px;
        background: #e9edf2;
    }

    .bar-fill {
        min-width: 3px;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #f3b600, #ff7a00);
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .04);
    }

    .bar-value {
        color: #172033;
        font-size: .78rem;
        font-weight: 800;
        text-align: right;
        white-space: nowrap;
    }

    .donut-layout {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.35rem;
    }

    .donut-chart {
        position: relative;
        width: min(220px, 64vw);
        aspect-ratio: 1;
        border-radius: 50%;
        background: {{ $donutBackground }};
        box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
    }

    .donut-chart::after {
        position: absolute;
        inset: 24%;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff;
        color: var(--dashboard-navy);
        content: '100%';
        font-size: 1.05rem;
        font-weight: 850;
        box-shadow: 0 0 0 1px rgba(15, 23, 42, .05);
    }

    .source-legend {
        width: 100%;
        display: grid;
        gap: .65rem;
    }

    .source-legend-row {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: .65rem;
        padding: .65rem .75rem;
        border: 1px solid #edf0f3;
        border-radius: 10px;
        background: #fbfcfd;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .legend-label {
        color: #334155;
        font-size: .82rem;
        font-weight: 700;
    }

    .legend-value {
        color: #172033;
        font-size: .82rem;
        font-weight: 850;
        text-align: right;
    }

    .report-card {
        overflow: hidden;
    }

    .report-toolbar {
        border-bottom: 1px solid #e5e7eb;
        background: #fff;
    }

    .report-toolbar .form-control {
        min-width: 230px;
    }

    .table-shell {
        width: 100%;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }

    .sales-report-table {
        min-width: 1120px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .81rem;
    }

    .sales-report-table th,
    .sales-report-table td {
        border-right: 1px solid var(--dashboard-border) !important;
        border-bottom: 1px solid var(--dashboard-border) !important;
        vertical-align: middle;
    }

    .sales-report-table th:first-child,
    .sales-report-table td:first-child {
        border-left: 1px solid var(--dashboard-border) !important;
    }

    .sales-report-table thead tr:first-child th {
        border-top: 1px solid var(--dashboard-border) !important;
    }

    .sales-report-table thead .group-header th {
        position: sticky;
        top: 0;
        z-index: 4;
        padding: .6rem .55rem;
        background: var(--dashboard-yellow);
        color: #171717;
        font-size: .73rem;
        font-weight: 850;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .sales-report-table thead .column-header th {
        position: sticky;
        top: 35px;
        z-index: 3;
        padding: .55rem .5rem;
        background: #ffe699;
        color: #171717;
        font-size: .72rem;
        font-weight: 850;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sales-report-table tbody tr:nth-child(even) td {
        background: #fbfcfd;
    }

    .sales-report-table tbody tr:hover td {
        background: #fff8d9;
    }

    .sales-report-table td {
        padding: .52rem .55rem;
    }

    .sales-report-table .item-code {
        color: #0f3f67;
        font-weight: 850;
        white-space: nowrap;
    }

    .sales-report-table .item-name {
        min-width: 280px;
        font-weight: 650;
    }

    .contribution-cell {
        min-width: 130px;
        padding: .25rem .4rem;
        border-radius: 6px;
        background: var(--dashboard-red);
        color: #fff;
        font-weight: 850;
        text-align: center;
    }

    .source-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 82px;
        padding: .32rem .55rem;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 850;
        letter-spacing: .02em;
        white-space: nowrap;
    }

    .source-ingco {
        background: #fff0c7;
        color: #8b5b00;
    }

    .source-smi {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .source-all {
        background: #ede9fe;
        color: #6d28d9;
    }

    .sales-report-table tfoot td {
        padding: .65rem .55rem;
        background: var(--dashboard-yellow) !important;
        color: #111827;
        font-weight: 900;
    }

    .empty-icon {
        color: #94a3b8;
        font-size: 3rem;
        line-height: 1;
    }

    @media (max-width: 1199.98px) {
        .chart-grid {
            grid-template-columns: 1fr;
        }

        .chart-card {
            min-height: auto;
        }
    }

    @media (max-width: 767.98px) {
        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .summary-cell:nth-child(2) {
            border-right: 0;
        }

        .summary-cell:nth-child(-n + 2) {
            border-bottom: 1px solid var(--dashboard-border);
        }

        .summary-value {
            min-height: 62px;
            font-size: 1.05rem;
        }

        .bar-row {
            grid-template-columns: minmax(72px, 90px) minmax(100px, 1fr);
        }

        .bar-value {
            grid-column: 2;
            margin-top: -.55rem;
            font-size: .72rem;
            text-align: left;
        }

        .report-toolbar .form-control {
            min-width: 100%;
        }
    }
</style>

<div class="sales-dashboard">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-2 mb-3">
        <div>
            <h1 class="dashboard-title h3 mb-1">Dashboard Sales By Item</h1>
            <div class="dashboard-subtitle">
                {{ $filter->databaseLabel() }} · {{ $filter->startDate->format('d M Y') }} sampai {{ $filter->endDate->format('d M Y') }}
            </div>
        </div>
        <div class="dashboard-subtitle">
            Data diurutkan berdasarkan nilai penjualan terbesar
        </div>
    </div>

    <div class="card filter-panel mb-4">
        <div class="card-body p-3 p-lg-4">
            <form method="GET" action="{{ route('sales-by-item.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="database" class="form-label fw-semibold">Database</label>
                    <select class="form-select" id="database" name="database">
                        <option value="all" @selected($filter->database === 'all')>Semua Database</option>
                        <option value="ingco" @selected($filter->database === 'ingco')>INGCO — AS_INGCO</option>
                        <option value="smi" @selected($filter->database === 'smi')>SMI — AS_SMI</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-xl-3">
                    <label for="end_date" class="form-label fw-semibold">Tanggal Akhir</label>
                    <input
                        type="date"
                        class="form-control @error('end_date') is-invalid @enderror"
                        id="end_date"
                        name="end_date"
                        value="{{ $filter->endDate->format('Y-m-d') }}"
                        required
                    >
                    @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4 col-xl-3 d-grid">
                    <button class="btn btn-primary" type="submit">Tampilkan Laporan</button>
                </div>

                <div class="col-12 col-xl-3 text-xl-end">
                    <span class="period-chip">
                        {{ $filter->startDate->format('d M Y') }} — {{ $filter->endDate->format('d M Y') }}
                    </span>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <strong>Filter belum valid.</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($error)
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-2" role="alert">
            <div class="fw-bold">!</div>
            <div>
                <div class="fw-bold">Laporan belum dapat dimuat</div>
                <div class="small mt-1">{{ $error }}</div>
            </div>
        </div>
    @endif

    <div class="summary-grid mb-4" aria-label="Ringkasan penjualan">
        <div class="summary-cell">
            <div class="summary-label">Total Sales</div>
            <div class="summary-value">Rp {{ number_format($report->totalSales, 0, ',', '.') }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Total Quantity</div>
            <div class="summary-value">{{ number_format($report->totalQuantity, 2, ',', '.') }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Jumlah Item</div>
            <div class="summary-value">{{ number_format($report->totalItems, 0, ',', '.') }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Rata-rata Harga / Qty</div>
            <div class="summary-value">Rp {{ number_format($averagePrice, 0, ',', '.') }}</div>
        </div>
    </div>

    @if (count($report->rows) > 0)
        <div class="chart-grid mb-4">
            <section class="card chart-card">
                <div class="card-header px-3 px-lg-4 py-3">
                    <h2 class="chart-title">Top 10 Item Berdasarkan Penjualan</h2>
                    <div class="chart-caption">Perbandingan nilai penjualan item tertinggi pada periode terpilih.</div>
                </div>
                <div class="card-body p-3 p-lg-4">
                    <div class="bar-chart" role="img" aria-label="Grafik top 10 item berdasarkan total penjualan">
                        @foreach ($topSalesRows as $row)
                            @php
                                $barWidth = max(0, min(100, ((float) $row->totalSales / $maxTopSales) * 100));
                            @endphp
                            <div class="bar-row" title="{{ $row->itemCode }} — {{ $row->itemName }}">
                                <div class="bar-label">{{ $row->itemCode }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ number_format($barWidth, 4, '.', '') }}%"></div>
                                </div>
                                <div class="bar-value">Rp {{ number_format($row->totalSales, 0, ',', '.') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card chart-card">
                <div class="card-header px-3 px-lg-4 py-3">
                    <h2 class="chart-title">Distribusi Sumber Penjualan</h2>
                    <div class="chart-caption">Kontribusi nilai penjualan berdasarkan sumber database.</div>
                </div>
                <div class="card-body p-3 p-lg-4">
                    <div class="donut-layout">
                        <div class="donut-chart" role="img" aria-label="Grafik distribusi sumber penjualan"></div>
                        <div class="source-legend">
                            @foreach ($sourceTotals as $source => $value)
                                @php
                                    $sourcePercentage = $sourceGrandTotal > 0
                                        ? ($value / $sourceGrandTotal) * 100
                                        : 0;
                                @endphp
                                <div class="source-legend-row">
                                    <span class="legend-dot" style="background: {{ $sourceColors[$source] }}"></span>
                                    <span class="legend-label">{{ $source }}</span>
                                    <span class="legend-value">
                                        {{ number_format($sourcePercentage, 1, ',', '.') }}%
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>
    @endif

    <div class="card report-card">
        <div class="report-toolbar p-3 p-lg-4">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
                <div>
                    <h2 class="h5 fw-bold mb-1">Detail Sales By Item</h2>
                    <div class="text-secondary small">
                        {{ $filter->databaseLabel() }} · Total {{ count($report->rows) }} item
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <input
                        id="itemSearch"
                        type="search"
                        class="form-control"
                        placeholder="Cari kode atau nama item..."
                        aria-label="Cari item"
                    >
                    <a
                        class="btn btn-outline-success text-nowrap"
                        href="{{ route('sales-by-item.export.excel', ['database' => $filter->database, 'end_date' => $filter->endDate->format('Y-m-d')]) }}"
                    >
                        Export Excel
                    </a>
                    <a
                        class="btn btn-outline-danger text-nowrap"
                        href="{{ route('sales-by-item.export.pdf', ['database' => $filter->database, 'end_date' => $filter->endDate->format('Y-m-d')]) }}"
                    >
                        Export PDF
                    </a>
                </div>
            </div>
        </div>

        @if (count($report->rows) > 0)
            <div class="table-shell">
                <table class="table mb-0 sales-report-table" id="salesTable">
                    <thead>
                        <tr class="group-header">
                            <th colspan="3">Item</th>
                            <th colspan="4">Sales Performance</th>
                            <th rowspan="2">Source</th>
                        </tr>
                        <tr class="column-header">
                            <th class="text-center">No</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th class="text-end">Total Qty</th>
                            <th class="text-end">Total Sales</th>
                            <th class="text-end">Average Price</th>
                            <th class="text-center">Contribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->rows as $index => $row)
                            <tr data-search="{{ strtolower($row->itemCode.' '.$row->itemName.' '.$row->source) }}">
                                <td class="text-center text-secondary">{{ $index + 1 }}</td>
                                <td class="item-code">{{ $row->itemCode }}</td>
                                <td class="item-name">{{ $row->itemName }}</td>
                                <td class="text-end">{{ number_format($row->totalQuantity, 2, ',', '.') }}</td>
                                <td class="text-end fw-bold">Rp {{ number_format($row->totalSales, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->averagePrice, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <div class="contribution-cell">
                                        {{ number_format($row->contribution, 2, ',', '.') }}%
                                    </div>
                                </td>
                                <td class="text-center">
                                    @php
                                        $sourceClass = $row->source === 'INGCO & SMI'
                                            ? 'source-all'
                                            : ($row->source === 'INGCO' ? 'source-ingco' : 'source-smi');
                                    @endphp
                                    <span class="source-badge {{ $sourceClass }}">{{ $row->source }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-center">TOTAL</td>
                            <td class="text-end">{{ number_format($report->totalQuantity, 2, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($report->totalSales, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($averagePrice, 0, ',', '.') }}</td>
                            <td class="text-center">100,00%</td>
                            <td class="text-center">{{ $filter->databaseLabel() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-footer bg-white border-0 text-secondary small px-4 py-3">
                Menampilkan <span id="visibleCount">{{ count($report->rows) }}</span> dari {{ count($report->rows) }} item.
            </div>
        @else
            <div class="card-body text-center py-5">
                <div class="empty-icon">∅</div>
                <h2 class="h6 fw-bold mt-3">Tidak ada data penjualan</h2>
                <p class="text-secondary mb-0">
                    Ubah database atau tanggal akhir, kemudian tampilkan laporan kembali.
                </p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('itemSearch');
    const table = document.getElementById('salesTable');
    const count = document.getElementById('visibleCount');

    if (!input || !table) {
        return;
    }

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;

        table.querySelectorAll('tbody tr').forEach((row) => {
            const matches = row.dataset.search.includes(query);
            row.classList.toggle('d-none', !matches);

            if (matches) {
                visible += 1;
            }
        });

        if (count) {
            count.textContent = visible;
        }
    });
})();
</script>
@endpush
