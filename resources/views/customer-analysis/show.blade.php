@extends('layouts.app')

@section('title', 'Detail Customer')

@push('styles')
<style>
    .detail-panel,.detail-card{background:#fff;border:1px solid #d0d5dd;border-radius:12px;box-shadow:0 6px 18px rgba(16,24,40,.06)}
    .detail-card{padding:1rem;height:100%}.detail-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#667085;font-weight:800}.detail-value{font-size:1.25rem;font-weight:900;margin-top:.25rem;color:#101828}.status-large{display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.45rem .75rem;font-weight:900;font-size:.75rem}.status-large.active{background:#dcfce7;color:#166534;border:1px solid #86efac}.status-large.inactive{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
    .rank-row{display:grid;grid-template-columns:34px 1fr auto;gap:.65rem;align-items:center;padding:.65rem 0;border-bottom:1px solid #eaecf0}.rank-row:last-child{border-bottom:0}.rank-no{width:28px;height:28px;display:grid;place-items:center;border-radius:50%;background:#101828;color:#fff;font-size:.72rem;font-weight:900}.rank-name{font-weight:800;color:#101828}.rank-code{font-size:.68rem;color:#667085}.rank-value{text-align:right;font-weight:900;font-variant-numeric:tabular-nums}
    .month-box{background:#f8fafc;border:1px solid #e4e7ec;border-radius:10px;padding:.8rem}.month-name{font-size:.72rem;color:#667085;font-weight:800}.month-sales{font-weight:900;font-size:1.05rem}.transaction-table{font-size:.76rem;min-width:1050px}.transaction-table th{white-space:nowrap;background:#101828;color:#fff}.transaction-table td{vertical-align:middle}.inactive-box{background:#fff8dd;border:1px solid #f2d46b;border-radius:10px;padding:1rem}
</style>
@endpush

@section('content')
@php
    $money = static fn($v) => 'Rp '.number_format((float)$v,0,',','.');
    $qty = static fn($v) => number_format((float)$v,0,',','.');
    $date = static function($v) { if(!$v) return '—'; try{return \Carbon\Carbon::parse($v)->format('d-m-Y');}catch(\Throwable){return (string)$v;} };
    $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $customer = $detail['customer'];
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
    <div>
        <a class="small text-decoration-none" href="{{ route('customer-analysis.index', ['database'=>$customer['source'],'end_date'=>$detail['period_end']->format('Y-m-d')]) }}">← Kembali ke Analisa Customer</a>
        <h1 class="h4 fw-bold mb-1 mt-2">{{ $customer['customer_name'] }}</h1>
        <div class="text-secondary small">{{ $customer['customer_code'] ?: 'ID '.$customer['customer_id'] }} · {{ $customer['source'] }} · Sales: {{ $customer['sales_person_name'] ?: '—' }} (ID {{ $customer['sales_person_id'] ?: '—' }})</div>
    </div>
    <span class="status-large {{ $detail['status'] }}"><i class="bi {{ $detail['status']==='active' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>{{ $detail['status']==='active' ? 'AKTIF' : 'TIDAK AKTIF' }}</span>
</div>

@if($detail['status']==='inactive')
<div class="inactive-box mb-3">
    <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Tidak ada transaksi pada periode {{ $detail['period_start']->format('d-m-Y') }} s/d {{ $detail['period_end']->format('d-m-Y') }}.</div>
    <div class="small mt-1">Transaksi terakhir dicari mundur maksimum {{ $detail['history_months'] }} bulan.</div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="detail-card"><div class="detail-label">Sales 3 Bulan</div><div class="detail-value">{{ $money($detail['total_sales_3m']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="detail-card"><div class="detail-label">Qty 3 Bulan</div><div class="detail-value">{{ $qty($detail['total_qty_3m']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="detail-card"><div class="detail-label">Jumlah Invoice</div><div class="detail-value">{{ number_format($detail['invoice_count_3m'],0,',','.') }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="detail-card"><div class="detail-label">Terakhir Beli</div><div class="detail-value" style="font-size:1.05rem">{{ $date($detail['last_purchase_date']) }}</div><div class="small text-secondary">{{ $detail['last_invoice'] ?: '—' }}</div></div></div>
</div>

<div class="detail-panel p-3 p-lg-4 mb-3">
    <h2 class="h6 fw-bold mb-3">History Penjualan 3 Bulan</h2>
    <div class="row g-3">
        @foreach($detail['monthly'] as $month)
            @php([$year,$m] = array_map('intval', explode('-', $month['month'])))
            <div class="col-md-4"><div class="month-box"><div class="month-name">{{ $monthNames[$m] ?? $m }} {{ $year }}</div><div class="month-sales">{{ $money($month['sales']) }}</div><div class="small text-secondary">Qty {{ $qty($month['qty']) }}</div></div></div>
        @endforeach
    </div>
</div>

@if($detail['status']==='active')
<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="detail-panel p-3 p-lg-4 h-100"><h2 class="h6 fw-bold mb-2">Top 3 Item Berdasarkan Qty</h2>@forelse($detail['top_qty'] as $i=>$item)<div class="rank-row"><div class="rank-no">{{ $i+1 }}</div><div><div class="rank-name">{{ $item['item_name'] }}</div><div class="rank-code">{{ $item['item_code'] }}</div></div><div class="rank-value">{{ $qty($item['quantity']) }}<div class="small text-secondary">{{ $money($item['sales']) }}</div></div></div>@empty<div class="text-secondary small py-3">Belum ada item.</div>@endforelse</div></div>
    <div class="col-lg-6"><div class="detail-panel p-3 p-lg-4 h-100"><h2 class="h6 fw-bold mb-2">Top 3 Item Berdasarkan Total Penjualan</h2>@forelse($detail['top_sales'] as $i=>$item)<div class="rank-row"><div class="rank-no">{{ $i+1 }}</div><div><div class="rank-name">{{ $item['item_name'] }}</div><div class="rank-code">{{ $item['item_code'] }}</div></div><div class="rank-value">{{ $money($item['sales']) }}<div class="small text-secondary">Qty {{ $qty($item['quantity']) }}</div></div></div>@empty<div class="text-secondary small py-3">Belum ada item.</div>@endforelse</div></div>
</div>
@else
<div class="detail-panel p-3 p-lg-4 mb-3">
    <h2 class="h6 fw-bold mb-2">Pembelian Terakhir — Top 3 Barang</h2>
    @if($detail['last_purchase'])
        <div class="small text-secondary mb-2">Invoice {{ $detail['last_purchase']['invoice'] ?: '—' }} · {{ $date($detail['last_purchase']['date']) }}</div>
        @forelse($detail['last_purchase']['items'] as $i=>$item)
            <div class="rank-row"><div class="rank-no">{{ $i+1 }}</div><div><div class="rank-name">{{ $item['item_name'] }}</div><div class="rank-code">{{ $item['item_code'] }}</div></div><div class="rank-value">Qty {{ $qty($item['quantity']) }}<div class="small text-secondary">{{ $money($item['sales']) }}</div></div></div>
        @empty<div class="text-secondary small py-3">Item transaksi terakhir tidak tersedia.</div>@endforelse
    @else
        <div class="text-secondary small py-3">Tidak ditemukan histori pembelian dalam {{ $detail['history_months'] }} bulan terakhir sebelum periode review.</div>
    @endif
</div>
@endif

@if($detail['status']==='active')
<div class="detail-panel overflow-hidden">
    <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-1">Detail Item Transaksi 3 Bulan</h2><div class="small text-secondary">Maksimum 250 baris terbaru.</div></div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 transaction-table"><thead><tr><th>Tanggal</th><th>Invoice</th><th>Item Code</th><th>Nama Item</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead><tbody>@forelse($detail['transactions'] as $row)<tr><td>{{ $date($row['invoice_date']) }}</td><td class="fw-bold">{{ $row['invoice_number'] ?: '—' }}</td><td>{{ $row['item_code'] ?: '—' }}</td><td>{{ $row['item_name'] ?: '—' }}</td><td class="text-end">{{ $qty($row['quantity']) }}</td><td class="text-end fw-bold">{{ $money($row['sales']) }}</td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada transaksi.</td></tr>@endforelse</tbody></table></div>
</div>
@endif
@endsection
