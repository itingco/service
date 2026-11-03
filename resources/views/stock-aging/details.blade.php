@extends('layouts.app')

@section('title', 'Detail Stock Aging')

@push('styles')
<style>
    .detail-card { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.05); }
    .detail-table { font-size:.82rem; font-variant-numeric:tabular-nums; }
    .detail-table th { white-space:nowrap; background:#ffc000; color:#111; }
    .detail-table td { vertical-align:middle; }
    .detail-table .number { text-align:right; white-space:nowrap; }
</style>
@endpush

@section('content')
@php
    $money = static fn($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $qty = static fn($value) => number_format((float) $value, 2, ',', '.');
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Detail {{ $bucketLabel }}</h1>
        <div class="text-secondary">{{ $source }} · {{ $warehouseName }}@if($warehouseCode) · {{ $warehouseCode }}@endif · WarehouseID {{ $warehouseId }}</div>
        <div class="small text-secondary mt-1">Per {{ $filter->selectedDate->format('d-m-Y') }} · Database: {{ $filter->databaseLabel() }} · Status: {{ $filter->itemStatusLabel() }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="{{ route('stock-aging.details.export.excel', [...$filter->query(), 'source'=>$source, 'warehouse_id'=>$warehouseId, 'bucket'=>$bucket]) }}"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</a>
        <a class="btn btn-outline-secondary" href="{{ route('stock-aging.index', $filter->query()) }}">← Kembali ke Stock &amp; Aging</a>
    </div>
</div>

<div class="detail-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-sm table-hover detail-table mb-0">
            <thead><tr><th>Item Code</th><th>Item Name</th><th class="text-end">Age</th><th class="text-end">Qty Sisa</th><th class="text-end">Price KHUSUS-1</th><th class="text-end">Sisa Stok</th><th class="text-end">Sales MTD</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($page as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['item_code'] }}</td>
                        <td>{{ $row['item_name'] }}</td>
                        <td class="number">{{ $row['age'] }} hari</td>
                        <td class="number">{{ $qty($row['qty']) }}</td>
                        <td class="number">{{ $money($row['price']) }}</td>
                        <td class="number fw-bold">{{ $money($row['sisa_stock']) }}</td>
                        <td class="number">{{ $money($row['sales_mtd']) }}</td>
                        <td><span class="badge {{ ($row['status_key'] ?? '')==='normal' ? 'text-bg-success' : (($row['status_key'] ?? '')==='reject' ? 'text-bg-warning' : 'text-bg-danger') }}">{{ $row['status_label'] ?? 'NORMAL' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Tidak ada item pada bucket ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($page->hasPages())
    <div class="mt-3">{{ $page->appends(request()->except('page'))->links() }}</div>
@endif
@endsection
