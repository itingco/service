@extends('layouts.app')

@section('title', 'Product Focus')

@push('styles')
<style>
    .strategy-hero{background:linear-gradient(135deg,#101828,#344054);color:#fff;border-radius:14px;padding:1.1rem 1.2rem}
    .strategy-card{background:#fff;border:1px solid #d0d5dd;border-radius:12px;padding:1rem;box-shadow:0 5px 16px rgba(16,24,40,.05)}
    .strategy-table{font-size:.8rem}.strategy-table th{background:#f2f4f7;white-space:nowrap}
    .focus-badge{display:inline-flex;border-radius:999px;background:#fff2cc;border:1px solid #e5bd31;padding:.25rem .55rem;font-size:.7rem;font-weight:900;color:#7a5a00}
    .master-paging .pagination{margin:0;flex-wrap:wrap;gap:.2rem}.master-paging .page-link{border-radius:6px}
</style>
@endpush

@section('content')
<div class="strategy-hero mb-3 d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
    <div>
        <div class="small fw-bold text-warning">PRODUCT STRATEGY MASTER</div>
        <h1 class="h4 fw-bold mb-1">Product Focus</h1>
        <div class="small text-white-50">Master tersimpan di SQL Server DB_AppHub.dbo.ItemFocus. Item Name dapat diambil dari Business Analytics jika belum terisi.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-light" href="{{ route('product-strategy.similar-items.index') }}">Similar Items</a>
        <a class="btn btn-sm btn-success" href="{{ route('product-strategy.product-focus.export') }}"><i class="bi bi-file-earmark-excel me-1"></i>Export Data Excel</a>
        <a class="btn btn-sm btn-outline-light" href="{{ route('product-strategy.product-focus.template') }}"><i class="bi bi-download me-1"></i>Template Import</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Data belum valid.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <section class="strategy-card h-100">
            <h2 class="h6 fw-bold mb-3">Tambah / Update Product Focus</h2>
            <form method="POST" action="{{ route('product-strategy.product-focus.store') }}" class="row g-2">@csrf
                <div class="col-md-5">
                    <label class="form-label fw-bold">Item Code</label>
                    <input class="form-control" name="item_code" list="focusItemList" placeholder="Contoh: AG750282" required>
                    <datalist id="focusItemList">@foreach($items as $item)<option value="{{ $item['item_code'] }}">{{ $item['item_name'] }}</option>@endforeach</datalist>
                </div>
                <div class="col-md-4"><label class="form-label fw-bold">Item Name</label><input class="form-control" name="item_name" placeholder="Boleh kosong, akan lookup otomatis"></div>
                <div class="col-md-3"><label class="form-label fw-bold">Product Focus</label><input class="form-control" name="focus_category" placeholder="TOP 25" required></div>
                <div class="col-12"><div class="small text-secondary">Item Name disimpan di DB_AppHub. Jika kosong, sistem mencari nama existing lalu fallback ke cache Business Analytics.</div></div>
                <div class="col-12 d-grid d-md-flex justify-content-md-end"><button class="btn btn-report px-4">Simpan</button></div>
            </form>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="strategy-card h-100">
            <h2 class="h6 fw-bold mb-2">Replace Data dari Excel</h2>
            <div class="alert alert-warning py-2 small mb-3"><strong>Perhatian:</strong> import menggantikan SELURUH data Product Focus, bukan hanya halaman yang sedang tampil.</div>
            <div class="small text-secondary mb-3">Kolom: <code>item_code</code>, <code>item_name</code>, dan <code>product_focus</code>. Nama boleh kosong.</div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('product-strategy.product-focus.import') }}" class="row g-2" onsubmit="return confirm('Semua Product Focus existing akan dihapus dan diganti dengan isi Excel ini. Lanjutkan?')">@csrf
                <div class="col-12"><input class="form-control" type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
                <div class="col-12 d-grid"><button class="btn btn-warning fw-bold"><i class="bi bi-arrow-repeat me-1"></i>Replace Product Focus dari Excel</button></div>
            </form>
        </section>
    </div>
</div>

<section class="strategy-card">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h6 fw-bold mb-1">Data Product Focus</h2>
            <div class="small text-secondary">Menampilkan {{ number_format($rows->firstItem() ?? 0,0,',','.') }}–{{ number_format($rows->lastItem() ?? 0,0,',','.') }} dari {{ number_format($rows->total(),0,',','.') }} item (halaman {{ $rows->currentPage() }}).</div>
        </div>
        <form method="GET" action="{{ route('product-strategy.product-focus.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <input class="form-control form-control-sm" style="max-width:240px" name="q" value="{{ $search }}" placeholder="Cari kode / nama / focus" maxlength="100" aria-label="Cari Product Focus">
            <select class="form-select form-select-sm" name="per_page" style="width:auto" aria-label="Jumlah baris per halaman">
                @foreach([25,50,100] as $size)<option value="{{ $size }}" @selected($rows->perPage() === $size)>{{ $size }} / halaman</option>@endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">Tampilkan</button>
            @if($search !== '')<a class="btn btn-sm btn-light" href="{{ route('product-strategy.product-focus.index') }}">Reset</a>@endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover strategy-table mb-0">
            <thead><tr><th>#</th><th>Item Code</th><th>Item Name</th><th>Product Focus</th><th>Priority</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ ($rows->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="fw-bold">{{ $row['item_code'] }}</td>
                    <td>{{ $row['item_name'] ?: '—' }}</td>
                    <td><span class="focus-badge">{{ $row['focus_category'] }}</span></td>
                    <td>{{ $row['focus_rank'] >= 999999 ? '—' : $row['focus_rank'] }}</td>
                    <td>{{ $row['updated_at'] ?? '—' }}</td>
                    <td class="text-end"><form method="POST" action="{{ route('product-strategy.product-focus.delete',['id'=>$row['id']]) }}" onsubmit="return confirm('Hapus Product Focus ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form></td>
                </tr>
            @empty<tr><td colspan="7" class="text-center py-4 text-secondary">Tidak ada Product Focus sesuai pencarian.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())
        <div class="master-paging d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-2 pt-3">
            <div class="small text-secondary">Total {{ number_format($rows->total(),0,',','.') }} data • {{ $rows->perPage() }} per halaman</div>
            {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
</section>
@endsection
