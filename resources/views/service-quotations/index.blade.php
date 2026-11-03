@extends('layouts.app')
@section('title', 'Penawaran Service')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
 <div><h1 class="h4 fw-bold">Penawaran Service</h1><div class="small text-muted">Status persetujuan terpisah dari status pengiriman ERP.</div></div>
 <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('service-quotations.sparepart-report') }}">Laporan Sparepart</a><a class="btn btn-report" href="{{ route('service-quotations.create') }}">Buat Penawaran</a></div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($needsMigration)<div class="alert alert-warning">Kolom status belum tersedia. Jalankan migration khusus DB_AppHub.</div>@else
<div class="filter-panel p-3 mb-3"><form method="GET" action="{{ route('service-quotations.index') }}" class="row g-2 align-items-end">
 <div class="col-md-3"><label class="form-label">Cari penawaran / customer / barang</label><input class="form-control" name="q" maxlength="120" value="{{ $filters['q'] }}"></div>
 <div class="col-md-2"><label class="form-label">Dari tanggal</label><input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
 <div class="col-md-2"><label class="form-label">Sampai tanggal</label><input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
 <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="all">Semua</option>@foreach(['pending'=>'Pending','approved'=>'Approved','cancel'=>'Cancel'] as $v=>$label)<option value="{{ $v }}" @selected($filters['status']===$v)>{{ $label }}</option>@endforeach</select></div>
 <div class="col-md-2"><label class="form-label">Database</label><select class="form-select" name="source"><option value="all">Semua</option>@foreach(['INGCO','SMI'] as $src)<option value="{{ $src }}" @selected($filters['source']===$src)>{{ $src }}</option>@endforeach</select></div>
 <div class="col-md-1"><label class="form-label">Baris</label><select class="form-select" name="per_page">@foreach([10,20,50] as $n)<option value="{{ $n }}" @selected($filters['per_page']===$n)>{{ $n }}</option>@endforeach</select></div>
 <div class="col-12 d-flex gap-2"><button class="btn btn-report" type="submit">Filter</button><a class="btn btn-outline-secondary" href="{{ route('service-quotations.index') }}">Reset</a><a class="btn btn-success" href="{{ route('service-quotations.export', request()->except('page')) }}">Export Excel (CSV)</a></div>
</form></div>
<div class="filter-panel p-3"><div class="small text-muted mb-2">{{ number_format($rows->total(),0,',','.') }} penawaran ditemukan</div><div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr><th>Tanggal</th><th>No Penawaran / ERP</th><th>Customer</th><th>Barang Rusak</th><th>Source</th><th class="text-end">Total</th><th>Status Persetujuan</th><th>ERP</th><th>Aksi</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y') }}</td><td><strong>{{ $row->quote_no }}</strong><div class="text-muted">{{ $row->erp_doc_no ?: 'Belum dikirim' }}</div></td><td>{{ $row->customer_name }}</td><td>{{ $row->damaged_item_code }}<div class="text-muted">{{ $row->damaged_item_name }}</div></td><td>{{ $row->erp_source }}</td><td class="text-end text-nowrap">Rp {{ number_format((float)$row->subtotal,0,',','.') }}</td>
<td><form method="POST" action="{{ route('service-quotations.approval-status', $row->id) }}" class="d-flex gap-1" onsubmit="return confirm('Ubah status penawaran ini?')">@csrf @method('PATCH')<select class="form-select form-select-sm" name="approval_status" aria-label="Status {{ $row->quote_no }}">@foreach(['pending'=>'Pending','approved'=>'Approved','cancel'=>'Cancel'] as $v=>$label)<option value="{{ $v }}" @selected($row->approval_status===$v)>{{ $label }}</option>@endforeach</select><button type="submit" class="btn btn-sm btn-outline-primary">Simpan</button></form></td>
<td>{{ $row->status==='sent'?'Terkirim':'Draft' }}</td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('service-quotations.show', $row->id) }}">Detail</a> @if($row->status==='draft')<a class="btn btn-sm btn-outline-secondary" href="{{ route('service-quotations.edit',$row->id) }}">Edit</a>@endif <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('service-quotations.print',$row->id) }}">Cetak</a></td></tr>
@empty<tr><td colspan="9" class="text-center p-4">Tidak ada data sesuai filter.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}</div>
@endif
@endsection
