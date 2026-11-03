@extends('layouts.app')

@section('title', 'Analisa Customer')

@push('styles')
<style>
    .customer-filter,.customer-panel,.customer-card{background:#fff;border:1px solid #d0d5dd;border-radius:12px;box-shadow:0 6px 18px rgba(16,24,40,.06)}
    .customer-card{padding:1rem;height:100%}.customer-card-label{font-size:.72rem;color:#667085;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.customer-card-value{font-size:1.45rem;font-weight:900;color:#101828;margin-top:.25rem}.customer-card-note{font-size:.72rem;color:#667085;margin-top:.2rem}
    .customer-table{font-size:.79rem;min-width:1320px}.customer-table th{white-space:nowrap;background:#101828;color:#fff;vertical-align:middle}.customer-table td{vertical-align:middle}.customer-table .money{text-align:right;font-variant-numeric:tabular-nums;font-weight:800}.customer-table .muted{color:#667085}.customer-name{font-weight:900;color:#101828}.customer-code{font-size:.7rem;color:#667085}.customer-status{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.3rem .6rem;font-size:.68rem;font-weight:900}.customer-status.active{background:#dcfce7;color:#166534;border:1px solid #86efac}.customer-status.inactive{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
    .history-chip{display:inline-block;margin:.1rem .15rem .1rem 0;padding:.2rem .45rem;background:#f2f4f7;border:1px solid #d0d5dd;border-radius:6px;font-size:.68rem;color:#344054;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sales-lock{background:#fff8dd;border:1px solid #f2d46b;border-radius:9px;padding:.55rem .75rem;font-size:.76rem}.summary-icon{font-size:1.25rem;color:#f0ad00}.pagination-note{font-size:.78rem;color:#667085}
    @media(max-width:767px){.customer-card-value{font-size:1.15rem}.customer-filter{padding:.85rem!important}}
</style>
@endpush

@section('content')
@php
    $money = static fn($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $qty = static fn($v) => number_format((float) $v, 0, ',', '.');
    $date = static function($v) {
        if (!$v) return '—';
        try { return \Carbon\Carbon::parse($v)->format('d-m-Y'); } catch (\Throwable) { return (string) $v; }
    };
    $filtersEffective = $report['filters'] ?? $filters;
    $pagination = $report['pagination'];
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Analisa Customer</h1>
        <div class="text-secondary small">Aktivitas customer 3 bulan, Top 3 item, dan deteksi customer yang tidak lagi bertransaksi.</div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="status-pill">Periode {{ $report['period_start']->format('d-m-Y') }} s/d {{ $report['period_end']->format('d-m-Y') }}</div>
        <a class="btn btn-success fw-bold" href="{{ route('customer-analysis.export.excel', request()->except('page')) }}">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (Data Mentah)
        </a>
    </div>
</div>

@if($error)
    <div class="alert alert-danger"><strong>Data belum dapat dimuat.</strong><br>{{ $error }}</div>
@endif

@if($report['sales_locked'])
    <div class="sales-lock mb-3"><i class="bi bi-lock-fill me-1"></i> Akun Sales: data otomatis dibatasi ke <strong>Sales Person ID {{ $filtersEffective['sales_person_id'] ?? '-' }}</strong>. Filter sales tidak dapat diubah.</div>
@endif

<div class="customer-filter p-3 p-lg-4 mb-3">
    <form method="GET" action="{{ route('customer-analysis.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-2">
            <label class="form-label fw-bold">Database</label>
            <select class="form-select" name="database">
                <option value="BOTH" @selected($filters['database']==='BOTH')>INGCO & SMI</option>
                <option value="INGCO" @selected($filters['database']==='INGCO')>INGCO</option>
                <option value="SMI" @selected($filters['database']==='SMI')>SMI</option>
            </select>
        </div>
        @if(!$report['sales_locked'])
        <div class="col-md-6 col-xl-3">
            <label class="form-label fw-bold">Sales Person</label>
            <select class="form-select" name="sales_person_id">
                <option value="">Semua Sales</option>
                @foreach($salesPersonOptions as $value=>$label)
                    <option value="{{ $value }}" @selected((int)($filters['sales_person_id'] ?? 0)===(int)$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-6 col-xl-2">
            <label class="form-label fw-bold">Status</label>
            <select class="form-select" name="status">
                <option value="all" @selected($filters['status']==='all')>Semua</option>
                <option value="active" @selected($filters['status']==='active')>Aktif</option>
                <option value="inactive" @selected($filters['status']==='inactive')>Tidak Aktif</option>
            </select>
        </div>
        <div class="col-md-6 col-xl-2">
            <label class="form-label fw-bold">Tanggal Akhir Review</label>
            <input class="form-control" type="date" name="end_date" value="{{ $filters['end_date']->format('Y-m-d') }}">
        </div>
        <div class="col-md-6 col-xl-2">
            <label class="form-label fw-bold">Urutkan</label>
            <select class="form-select" name="sort">
                <option value="sales_desc" @selected($filters['sort']==='sales_desc')>Sales terbesar</option>
                <option value="sales_asc" @selected($filters['sort']==='sales_asc')>Sales terkecil</option>
                <option value="recent_desc" @selected($filters['sort']==='recent_desc')>Transaksi terbaru</option>
                <option value="name_asc" @selected($filters['sort']==='name_asc')>Nama A-Z</option>
                <option value="name_desc" @selected($filters['sort']==='name_desc')>Nama Z-A</option>
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label fw-bold">Cari Customer</label>
            <input class="form-control" name="search" value="{{ $filters['search'] }}" placeholder="Kode / nama / sales...">
        </div>
        <div class="col-md-3 col-xl-1 d-grid"><button class="btn btn-report">Filter</button></div>
        <div class="col-md-3 col-xl-1 d-grid"><a class="btn btn-outline-secondary" href="{{ route('customer-analysis.index') }}">Reset</a></div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="customer-card"><div class="d-flex justify-content-between"><div class="customer-card-label">Total Customer</div><i class="bi bi-people summary-icon"></i></div><div class="customer-card-value">{{ number_format($report['summary']['total_customers'],0,',','.') }}</div><div class="customer-card-note">Sesuai database & sales yang dipilih</div></div></div>
    <div class="col-6 col-xl-3"><div class="customer-card"><div class="d-flex justify-content-between"><div class="customer-card-label">Customer Aktif</div><i class="bi bi-person-check summary-icon"></i></div><div class="customer-card-value">{{ number_format($report['summary']['active_customers'],0,',','.') }}</div><div class="customer-card-note">Ada transaksi pada 3 bulan review</div></div></div>
    <div class="col-6 col-xl-3"><div class="customer-card"><div class="d-flex justify-content-between"><div class="customer-card-label">Tidak Aktif</div><i class="bi bi-person-x summary-icon"></i></div><div class="customer-card-value">{{ number_format($report['summary']['inactive_customers'],0,',','.') }}</div><div class="customer-card-note">Tidak ada transaksi pada 3 bulan review</div></div></div>
    <div class="col-6 col-xl-3"><div class="customer-card"><div class="d-flex justify-content-between"><div class="customer-card-label">Sales 3 Bulan</div><i class="bi bi-cash-stack summary-icon"></i></div><div class="customer-card-value" style="font-size:1.12rem">{{ $money($report['summary']['sales_3m']) }}</div><div class="customer-card-note">Akumulasi seluruh customer dalam scope</div></div></div>
</div>

<div class="customer-panel overflow-hidden">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center p-3 border-bottom">
        <div><h2 class="h6 fw-bold mb-1">Daftar Customer</h2><div class="small text-secondary">Customer tidak aktif menampilkan transaksi terakhir yang ditemukan sampai {{ $report['history_months'] }} bulan ke belakang. Export Excel berisi sheet Customer Summary dan Data Mentah 3 Bulan sesuai filter aktif.</div></div>
        <div class="pagination-note">Menampilkan {{ $pagination['from'] }}-{{ $pagination['to'] }} dari {{ $pagination['total'] }} customer hasil filter</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 customer-table">
            <thead>
            <tr>
                <th>Database</th><th>Customer</th><th>Sales Person</th><th>Status</th><th class="text-end">Sales 3 Bulan</th><th class="text-end">Qty 3 Bulan</th><th class="text-center">Invoice</th><th>Transaksi Terakhir</th><th>Top Item / Barang Terakhir</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    <td><span class="badge text-bg-dark">{{ $row['source'] }}</span></td>
                    <td><div class="customer-name">{{ $row['customer_name'] ?: 'Tanpa Nama' }}</div><div class="customer-code">{{ $row['customer_code'] ?: 'ID '.$row['customer_id'] }}</div></td>
                    <td><div class="fw-bold">{{ $row['sales_person_name'] ?: '—' }}</div><div class="customer-code">ID {{ $row['sales_person_id'] ?: '—' }}</div></td>
                    <td><span class="customer-status {{ $row['status'] }}"><i class="bi {{ $row['status']==='active' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>{{ $row['status']==='active' ? 'AKTIF' : 'TIDAK AKTIF' }}</span></td>
                    <td class="money">{{ $money($row['total_sales_3m']) }}</td>
                    <td class="money">{{ $qty($row['total_qty_3m']) }}</td>
                    <td class="text-center fw-bold">{{ number_format((int)$row['invoice_count_3m'],0,',','.') }}</td>
                    <td>
                        <div class="fw-bold">{{ $date($row['last_purchase_date']) }}</div>
                        <div class="customer-code">{{ $row['last_invoice'] ?: ($row['status']==='inactive' && !$row['history_found'] ? 'Histori tidak ditemukan' : '—') }}</div>
                    </td>
                    <td style="max-width:360px">
                        @if($row['status']==='inactive')
                            @forelse($row['last_purchase_items'] as $item)
                                <span class="history-chip" title="{{ $item['item_code'] }} - {{ $item['item_name'] }}">{{ $item['item_code'] }} · {{ $item['item_name'] }} ({{ $qty($item['quantity']) }})</span>
                            @empty
                                <span class="text-secondary small">Belum ada histori pembelian dalam batas pencarian.</span>
                            @endforelse
                        @else
                            <span class="text-secondary small">Buka detail untuk Top 3 Qty & Top 3 Sales.</span>
                        @endif
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('customer-analysis.show', ['source'=>$row['source'],'customerId'=>$row['customer_id'],'end_date'=>$report['period_end']->format('Y-m-d')]) }}">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-secondary py-5">Tidak ada customer sesuai filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($pagination['last_page'] > 1)
    <div class="d-flex justify-content-between align-items-center p-3 border-top">
        <a class="btn btn-sm btn-outline-secondary {{ $pagination['page']<=1 ? 'disabled' : '' }}" href="{{ request()->fullUrlWithQuery(['page'=>max(1,$pagination['page']-1)]) }}">← Sebelumnya</a>
        <div class="small text-secondary">Halaman {{ $pagination['page'] }} / {{ $pagination['last_page'] }}</div>
        <a class="btn btn-sm btn-outline-secondary {{ $pagination['page']>=$pagination['last_page'] ? 'disabled' : '' }}" href="{{ request()->fullUrlWithQuery(['page'=>min($pagination['last_page'],$pagination['page']+1)]) }}">Berikutnya →</a>
    </div>
    @endif
</div>
@endsection
