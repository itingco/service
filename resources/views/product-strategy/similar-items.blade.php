@extends('layouts.app')

@section('title', 'Similar Items')

@push('styles')
<style>
    .strategy-hero{background:linear-gradient(135deg,#101828,#344054);color:#fff;border-radius:14px;padding:1.1rem 1.2rem}
    .strategy-card{background:#fff;border:1px solid #d0d5dd;border-radius:12px;padding:1rem;box-shadow:0 5px 16px rgba(16,24,40,.05)}
    .strategy-table{font-size:.8rem}.strategy-table th{background:#f2f4f7;white-space:nowrap}
    .relation-arrow{font-size:1.15rem;font-weight:900;color:#667085}
    .master-paging .pagination{margin:0;flex-wrap:wrap;gap:.2rem}.master-paging .page-link{border-radius:6px}
</style>
@endpush

@section('content')
<div class="strategy-hero mb-3 d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
    <div>
        <div class="small fw-bold text-warning">PRODUCT STRATEGY MASTER</div>
        <h1 class="h4 fw-bold mb-1">Similar Items</h1>
        <div class="small text-white-50">Relasi tersimpan di SQL Server DB_AppHub. Daftar ini dimuat per halaman, tidak seluruh tabel.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-light" href="{{ route('product-strategy.product-focus.index') }}">Product Focus</a>
        <a class="btn btn-sm btn-success" href="{{ route('product-strategy.similar-items.export') }}"><i class="bi bi-file-earmark-excel me-1"></i>Export Data Excel</a>
        <a class="btn btn-sm btn-outline-light" href="{{ route('product-strategy.similar-items.template') }}"><i class="bi bi-download me-1"></i>Template Import</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Data belum valid.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <section class="strategy-card h-100">
            <h2 class="h6 fw-bold mb-3">Tambah Similar Item</h2>
            <form method="POST" action="{{ route('product-strategy.similar-items.store') }}" class="row g-2">@csrf
                <div class="col-md-3"><label class="form-label fw-bold">Item Code</label><input class="form-control" name="item_code" list="similarItemList" placeholder="ITEM001" required></div>
                <div class="col-md-3"><label class="form-label fw-bold">Item Name <span class="text-secondary fw-normal">(lookup)</span></label><input class="form-control" name="item_name" placeholder="Tidak disimpan"></div>
                <div class="col-md-3"><label class="form-label fw-bold">Similar Item Code</label><input class="form-control" name="similar_item_code" list="similarItemList" placeholder="ITEM003" required></div>
                <div class="col-md-3"><label class="form-label fw-bold">Similar Item Name <span class="text-secondary fw-normal">(lookup)</span></label><input class="form-control" name="similar_item_name" placeholder="Tidak disimpan"></div>
                <datalist id="similarItemList">@foreach($items as $item)<option value="{{ $item['item_code'] }}">{{ $item['item_name'] }}</option>@endforeach</datalist>
                <div class="col-12"><div class="small text-secondary">Master Similar Items hanya menyimpan ItemCode dan SimilarItemCode. Nama ditampilkan dari ItemFocus / cache Business Analytics.</div></div>
                <div class="col-12 d-grid d-md-flex justify-content-md-end"><button class="btn btn-report px-4">Simpan Relasi</button></div>
            </form>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="strategy-card h-100">
            <h2 class="h6 fw-bold mb-2">Replace Data dari Excel</h2>
            <div class="alert alert-warning py-2 small mb-3"><strong>Perhatian:</strong> import menggantikan SELURUH Similar Items di DB_AppHub, bukan hanya halaman yang sedang tampil.</div>
            <div class="small text-secondary mb-3">Kolom minimal: <code>item_code</code> dan <code>similar_item_code</code>. Nama item boleh kosong.</div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('product-strategy.similar-items.import') }}" class="row g-2" onsubmit="return confirm('Semua Similar Items existing akan dihapus dan diganti dengan isi Excel ini. Lanjutkan?')">@csrf
                <div class="col-12"><input class="form-control" type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
                <div class="col-12 d-grid"><button class="btn btn-warning fw-bold"><i class="bi bi-arrow-repeat me-1"></i>Replace Similar Items dari Excel</button></div>
            </form>
        </section>
    </div>
</div>

<section class="strategy-card">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h6 fw-bold mb-1">Data Similar Items</h2>
            <div class="small text-secondary">Menampilkan {{ number_format($rows->firstItem() ?? 0,0,',','.') }}–{{ number_format($rows->lastItem() ?? 0,0,',','.') }} dari {{ number_format($rows->total(),0,',','.') }} relasi (halaman {{ $rows->currentPage() }}).</div>
        </div>
        <form method="GET" action="{{ route('product-strategy.similar-items.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <input class="form-control form-control-sm" style="max-width:240px" name="q" value="{{ $search }}" placeholder="Cari kode item / similar" maxlength="100" aria-label="Cari Similar Items">
            <select class="form-select form-select-sm" name="per_page" style="width:auto" aria-label="Jumlah baris per halaman">
                @foreach([25,50,100] as $size)<option value="{{ $size }}" @selected($rows->perPage() === $size)>{{ $size }} / halaman</option>@endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">Tampilkan</button>
            @if($search !== '')<a class="btn btn-sm btn-light" href="{{ route('product-strategy.similar-items.index') }}">Reset</a>@endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover strategy-table mb-0">
            <thead><tr><th>#</th><th>Item</th><th></th><th>Similar Item</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ ($rows->firstItem() ?? 1) + $loop->index }}</td>
                    <td><div class="fw-bold">{{ $row['item_code'] }}</div><div class="text-secondary">{{ $row['item_name'] ?: '—' }}</div></td>
                    <td class="text-center relation-arrow">→</td>
                    <td><div class="fw-bold">{{ $row['similar_item_code'] }}</div><div class="text-secondary">{{ $row['similar_item_name'] ?: '—' }}</div></td>
                    <td class="text-end"><form method="POST" action="{{ route('product-strategy.similar-items.delete',['id'=>$row['id']]) }}" onsubmit="return confirm('Hapus relasi Similar Item ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form></td>
                </tr>
            @empty<tr><td colspan="5" class="text-center py-4 text-secondary">Tidak ada Similar Items sesuai pencarian.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())
        <div class="master-paging d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-2 pt-3">
            <div class="small text-secondary">Total {{ number_format($rows->total(),0,',','.') }} relasi • {{ $rows->perPage() }} per halaman</div>
            {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
</section>
@endsection
