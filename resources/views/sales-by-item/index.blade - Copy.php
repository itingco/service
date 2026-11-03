@extends('layouts.app')

@section('title', 'Sales By Item')

@section('content')
<div class="card filter-card mb-4">
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
                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
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

<div class="row g-3 mb-4">
    @php
        $kpis = [
            ['Total Sales', 'Rp '.number_format($report->totalSales, 0, ',', '.'), '#4472c4'],
            ['Total Quantity', number_format($report->totalQuantity, 2, ',', '.'), '#70ad47'],
            ['Jumlah Item', number_format($report->totalItems, 0, ',', '.'), '#ed7d31'],
            ['Rata-rata / Item', 'Rp '.number_format($report->averageSalesPerItem, 0, ',', '.'), '#8064a2'],
        ];
    @endphp
    @foreach ($kpis as [$label, $value, $color])
        <div class="col-6 col-xl-3">
            <div class="card kpi-card">
                <div class="d-flex h-100">
                    <div class="kpi-accent" style="background: {{ $color }}"></div>
                    <div class="card-body p-3 p-lg-4">
                        <div class="kpi-label">{{ $label }}</div>
                        <div class="kpi-value">{{ $value }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header bg-white border-0 p-3 p-lg-4 pb-2">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <h1 class="h5 fw-bold mb-1">Sales By Item</h1>
                <div class="text-secondary small">
                    {{ $filter->databaseLabel() }} · Diurutkan berdasarkan total penjualan terbesar
                </div>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <input id="itemSearch" type="search" class="form-control" placeholder="Cari kode atau nama item..." aria-label="Cari item">
                <a class="btn btn-outline-success text-nowrap" href="{{ route('sales-by-item.export.excel', ['database' => $filter->database, 'end_date' => $filter->endDate->format('Y-m-d')]) }}">Export Excel</a>
                <a class="btn btn-outline-danger text-nowrap" href="{{ route('sales-by-item.export.pdf', ['database' => $filter->database, 'end_date' => $filter->endDate->format('Y-m-d')]) }}">Export PDF</a>
            </div>
        </div>
    </div>

    @if (count($report->rows) > 0)
        <div class="table-shell">
            <table class="table table-hover mb-0" id="salesTable">
                <thead>
                <tr>
                    <th class="text-center">No</th>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th class="text-end">Total Qty</th>
                    <th class="text-end">Total Sales</th>
                    <th class="text-end">Average Price</th>
                    <th class="text-end">Contribution</th>
                    <th class="text-center">Source</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($report->rows as $index => $row)
                    <tr data-search="{{ strtolower($row->itemCode.' '.$row->itemName.' '.$row->source) }}">
                        <td class="text-center text-secondary">{{ $index + 1 }}</td>
                        <td class="item-code">{{ $row->itemCode }}</td>
                        <td>{{ $row->itemName }}</td>
                        <td class="text-end">{{ number_format($row->totalQuantity, 2, ',', '.') }}</td>
                        <td class="text-end fw-semibold">Rp {{ number_format($row->totalSales, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($row->averagePrice, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($row->contribution, 2, ',', '.') }}%</td>
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
            </table>
        </div>
        <div class="card-footer bg-white border-0 text-secondary small px-4 py-3">
            Menampilkan <span id="visibleCount">{{ count($report->rows) }}</span> dari {{ count($report->rows) }} item.
        </div>
    @else
        <div class="card-body text-center py-5">
            <div class="empty-icon">∅</div>
            <h2 class="h6 fw-bold">Tidak ada data penjualan</h2>
            <p class="text-secondary mb-0">Ubah database atau tanggal akhir, kemudian tampilkan laporan kembali.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('itemSearch');
    const table = document.getElementById('salesTable');
    const count = document.getElementById('visibleCount');
    if (!input || !table) return;

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;
        table.querySelectorAll('tbody tr').forEach((row) => {
            const matches = row.dataset.search.includes(query);
            row.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        if (count) count.textContent = visible;
    });
})();
</script>
@endpush
