@extends('layouts.app')

@section('title', 'Stock Strategy Review')

@push('styles')
<style>
    .review-card{background:#fff;border:1px solid #d0d5dd;border-radius:12px;box-shadow:0 5px 16px rgba(16,24,40,.05)}.review-kpi{padding:1rem;height:100%}.review-kpi .label{font-size:.72rem;color:#667085;font-weight:800;text-transform:uppercase}.review-kpi .value{font-size:1.2rem;font-weight:900;color:#101828;margin-top:.25rem}.strategy-table{font-size:.76rem;font-variant-numeric:tabular-nums}.strategy-table th{background:#101828;color:#fff;white-space:nowrap;position:sticky;top:0}.strategy-table td{vertical-align:middle}.focus-row td{background:#fff2cc!important;color:#6b4e00;font-weight:900;border-top:2px solid #d6a400}.non-focus{color:#667085}.stock-old{color:#b42318;font-weight:900}.warehouse-title{padding:.8rem 1rem;border-bottom:1px solid #eaecf0;background:#f8fafc}
</style>
@endpush

@section('content')
@php
    $money = static fn($value) => 'Rp '.number_format((float)$value,0,',','.');
    $qty = static fn($value) => number_format((float)$value,2,',','.');
@endphp

<div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end mb-3">
    <div><div class="small fw-bold text-warning">PRODUCT FOCUS → UMUR STOK → SISA STOK</div><h1 class="h4 fw-bold mb-1">Stock Strategy Review</h1><div class="small text-secondary">Prioritas item mengikuti master Product Focus. Di dalam focus yang sama, stok tertua dan nilai sisa stok terbesar muncul lebih dulu.</div></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('stock-aging.index',$filter->query()) }}">Kembali ke Stock Aging</a>
        @if ($dashboardMenuAccess['product_focus'] ?? false)
            <a class="btn btn-sm btn-dark" href="{{ route('product-strategy.product-focus.index') }}">Kelola Product Focus</a>
        @endif
    </div>
</div>

@if($error)<div class="alert alert-danger">{{ $error }}</div>@endif

<form class="review-card p-3 mb-3" method="GET" action="{{ route('stock-aging.strategy-review') }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label fw-bold">Tanggal</label><input class="form-control" type="date" name="date" value="{{ $filter->selectedDate->format('Y-m-d') }}"></div>
        <div class="col-md-2"><label class="form-label fw-bold">Database</label><select class="form-select" name="database"><option value="all" @selected($filter->database==='all')>Gabungan</option><option value="ingco" @selected($filter->database==='ingco')>AS_INGCO</option><option value="smi" @selected($filter->database==='smi')>AS_SMI</option></select></div>
        <div class="col-md-2"><label class="form-label fw-bold">Status Item</label><select class="form-select" name="item_status"><option value="all" @selected($filter->itemStatus==='all')>Semua</option><option value="normal" @selected($filter->itemStatus==='normal')>Normal</option><option value="discontinued" @selected($filter->itemStatus==='discontinued')>Discontinued</option><option value="clearance" @selected($filter->itemStatus==='clearance')>Item Reject</option><option value="both" @selected($filter->itemStatus==='both')>Discontinued + Item Reject</option></select></div>
        <div class="col-md-3">
            <label class="form-label fw-bold">Warehouse <span class="badge text-bg-primary">Multi Select</span></label>
            <x-warehouse-multi-select
                id="strategyWarehousePicker"
                :options="$warehouseOptions"
                :selected="$warehouseKeys"
            />
        </div>
        <div class="col-md-2"><label class="form-label fw-bold">Product Focus</label><select class="form-select" name="focus"><option value="">Semua Focus</option>@foreach($focusOptions as $option)<option value="{{ $option }}" @selected($focus===$option)>{{ $option }}</option>@endforeach<option value="NON FOCUS" @selected($focus==='NON FOCUS')>NON FOCUS</option></select></div>
        <div class="col-md-1 d-grid"><button class="btn btn-report">View</button></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="review-card review-kpi"><div class="label">Item / Warehouse Rows</div><div class="value">{{ number_format($strategyReview['totals']['item_rows'],0,',','.') }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="review-card review-kpi"><div class="label">Warehouse</div><div class="value">{{ number_format($strategyReview['totals']['warehouses'],0,',','.') }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="review-card review-kpi"><div class="label">Qty Sisa</div><div class="value">{{ $qty($strategyReview['totals']['qty']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="review-card review-kpi"><div class="label">Nilai Sisa Stok</div><div class="value">{{ $money($strategyReview['totals']['sisa_stock']) }}</div></div></div>
</div>

@if($strategyReview['warehouses']===[])
    <div class="review-card p-5 text-center text-secondary">Tidak ada data untuk filter ini.</div>
@else
<div class="d-grid gap-3">
@foreach($strategyReview['warehouses'] as $warehouse)
    <section class="review-card overflow-hidden">
        <div class="warehouse-title d-flex flex-column flex-lg-row justify-content-between gap-2"><div><strong>{{ $warehouse['database'] ?: 'AS_'.$warehouse['source'] }} · {{ $warehouse['warehouse_name'] ?: $warehouse['warehouse_code'] }}</strong><div class="small text-secondary">Warehouse ID {{ $warehouse['warehouse_id'] }} @if($warehouse['warehouse_code'])· {{ $warehouse['warehouse_code'] }}@endif</div></div><div class="text-lg-end small"><strong>{{ $money($warehouse['sisa_stock']) }}</strong><br><span class="text-secondary">Total sisa stok</span></div></div>
        <div class="table-responsive" style="max-height:70vh">
            <table class="table table-bordered table-hover strategy-table mb-0">
                <thead><tr><th>Product Focus</th><th>Item Code</th><th>Item Name</th><th class="text-end">Qty</th><th class="text-end">Sisa Stok</th><th class="text-end">Sales MTD</th><th>Age</th><th>Status</th></tr></thead>
                <tbody>
                @php $previousFocus=null; @endphp
                @foreach($warehouse['items'] as $item)
                    @if($previousFocus !== $item['product_focus'])
                        <tr class="focus-row"><td colspan="8">{{ $item['product_focus'] }} @if($item['product_focus']==='NON FOCUS')<span class="small fw-normal">· belum dimasukkan ke master Product Focus</span>@endif</td></tr>
                        @php $previousFocus=$item['product_focus']; @endphp
                    @endif
                    <tr>
                        <td class="{{ $item['product_focus']==='NON FOCUS'?'non-focus fw-bold':'fw-bold' }}">{{ $item['product_focus'] }}</td>
                        <td class="fw-bold">{{ $item['item_code'] }}</td>
                        <td>{{ $item['item_name'] }}</td>
                        <td class="text-end">{{ $qty($item['qty']) }}</td>
                        <td class="text-end fw-bold">{{ $money($item['sisa_stock']) }}</td>
                        <td class="text-end">{{ $money($item['sales_mtd'] ?? 0) }}</td>
                        <td class="{{ (int)$item['age']>120?'stock-old':'' }}">{{ number_format((int)$item['age'],0,',','.') }} hari</td>
                        <td>@if($item['discontinued'] ?? false)<span class="badge text-bg-danger">DISCONTINUE</span>@endif @if($item['clearance'] ?? false)<span class="badge text-bg-warning">ITEM REJECT</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endforeach
</div>
@endif
@endsection
