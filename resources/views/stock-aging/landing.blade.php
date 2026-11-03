@extends('layouts.app')
@section('title', 'Stock & Aging Review')
@section('content')
<div class="mb-3">
  <div class="small fw-bold text-warning">INVENTORY · STOCK AGING</div>
  <h1 class="h4 fw-bold mb-1">Stock &amp; Aging Review</h1>
  <p class="text-secondary mb-0">Atur filter lalu klik <strong>Terapkan</strong>. Data stok tidak diambil secara otomatis ketika membuka menu.</p>
</div>
<div class="card border-0 shadow-sm rounded-4">
 <div class="card-body p-3 p-lg-4">
  <form method="GET" action="{{ route('stock-aging.index') }}">
   <input type="hidden" name="load" value="1">
   <div class="row g-3 align-items-end">
    <div class="col-12 col-md-3">
     <label class="form-label fw-bold" for="stock-date">Tanggal laporan</label>
     <input id="stock-date" type="date" name="date" class="form-control" value="{{ $filter->selectedDate->format('Y-m-d') }}" required>
    </div>
    <div class="col-12 col-md-3">
     <label class="form-label fw-bold" for="stock-db">Kategori Database</label>
     <select id="stock-db" name="database" class="form-select">
      <option value="all" @selected($filter->database === 'all')>Semua (INGCO + WADFOW + SMI)</option>
      <option value="ingco" @selected($filter->database === 'ingco')>INGCO</option>
      <option value="wadfow" @selected($filter->database === 'wadfow')>WADFOW</option>
      <option value="smi" @selected($filter->database === 'smi')>SMI</option>
     </select>
    </div>
    <div class="col-12 col-md-4">
     <label class="form-label fw-bold">Status Item</label>
     @include('components.item-status-multiselect', ['filter' => $filter, 'itemStatusRejectValue' => 'reject'])
    </div>
    <div class="col-12 col-md-2 d-grid">
     <button type="submit" class="btn btn-report"><i class="bi bi-funnel me-1"></i>Terapkan</button>
    </div>
   </div>
  </form>
 </div>
</div>
<div class="bg-white border rounded-4 p-5 mt-3 text-center text-secondary">
 <i class="bi bi-database fs-1 d-block mb-2"></i>
 <h2 class="h6 text-dark fw-bold">Belum ada data dimuat</h2>
 <p class="mb-0 small">Setelah klik Terapkan, detail stok, pilihan gudang, kategori gudang, grouping, dan export akan tersedia seperti biasanya.</p>
</div>
@endsection
