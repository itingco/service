@extends('layouts.app')
@section('title', 'Detail Penawaran Service')
@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <div><a href="{{ route('service-quotations.index') }}" class="small text-decoration-none">&larr; Semua penawaran</a><h1 class="h4 fw-bold mb-0">{{ $header->quote_no }}</h1><span class="badge {{ $header->status === 'sent' ? 'bg-success' : 'bg-secondary' }}">{{ $header->status === 'sent' ? 'Terkirim ke ERP' : 'Draft' }}</span></div>
  <div class="d-flex gap-2 flex-wrap">
    @if($header->status === 'draft')<a href="{{ route('service-quotations.edit', $header->id) }}" class="btn btn-report"><i class="bi bi-pencil-square me-1"></i> Edit Draft</a>@endif
    <a href="{{ route('service-quotations.print', $header->id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i> Cetak Penawaran A4</a>
    <a href="{{ route('service-quotations.print-receipt', $header->id) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-receipt me-1"></i> Cetak Struk Service 80mm</a>
    @if($header->status === 'draft')
      <form method="POST" action="{{ route('service-quotations.send-erp', $header->id) }}" onsubmit="return confirm('Dokumen akan dikirim ke ERP. CustomerID mengikuti mapping Service Type, sedangkan CustomerName sesuai nama customer penawaran. Lanjutkan?')">@csrf
        <button type="submit" class="btn btn-report" @disabled(!$erpEnabled || !$erpCustomerMapping || count($lines) === 0)><i class="bi bi-send me-1"></i> Kirim ke ERP</button>
      </form>
    @endif
  </div>
</div>
@if(count($lines) === 0 && $header->status === 'draft')<div class="alert alert-info">Penerimaan awal tersimpan tanpa sparepart. Gunakan <strong>Edit Draft</strong> untuk melengkapi sparepart. Pengiriman ERP baru dapat dilakukan setelah ada sparepart.</div>@endif
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->has('erp'))<div class="alert alert-warning">{{ $errors->first('erp') }}</div>@endif
@if($header->status === 'draft' && !$erpEnabled)<div class="alert alert-info small">Pengiriman ERP dinonaktifkan lewat SERVICE_ERP_SEND_ENABLED pada .env.</div>@endif
@if($header->status === 'draft' && $erpEnabled)
  @if($erpCustomerMapping)
    <div class="alert alert-info small">Tujuan: <strong>AS_{{ $header->erp_source }}</strong>. CustomerID dari master Service Type <strong>{{ $header->doc_type_code }}</strong>: <strong>{{ $erpCustomerMapping['customer_id'] }}</strong> — {{ $erpCustomerMapping['customer_name'] }}. Nama customer pada dokumen ERP tetap <strong>{{ $header->customer_name }}</strong>, sesuai penawaran (tidak mengikuti nama akun AR_Customers). Harga jual tetap tersimpan di DB_AppHub.</div>
  @else
    <div class="alert alert-warning">CustomerID untuk <strong>AS_{{ $header->erp_source }} / {{ $header->doc_type_code }}</strong> belum diatur. Sebelum mengirim, buka <a href="{{ route('service-quotations.service-customers', ['source' => $header->erp_source]) }}" class="alert-link">Master Customer Service</a> dan pilih CustomerID dari AR_Customers ERP yang sesuai.</div>
  @endif
@endif
<div class="filter-panel p-3 mb-3"><div class="row g-3"><div class="col-md-6"><div class="text-muted small">Customer</div><strong>{{ $header->customer_name }}</strong></div><div class="col-md-6"><div class="text-muted small">Jenis Service / Invoice</div><strong>{{ $header->doc_type_label }} ({{ $header->doc_type_code }})</strong></div><div class="col-md-6"><div class="text-muted small">Barang Rusak</div><strong>{{ $header->damaged_item_code }}</strong> — {{ $header->damaged_item_name }}</div><div class="col-md-6"><div class="text-muted small">ERP Sumber Barang</div><strong>{{ $header->erp_source }}</strong></div><div class="col-md-6"><div class="text-muted small">Tanggal Penawaran</div>{{ \Illuminate\Support\Carbon::parse($header->created_at)->format('d/m/Y H:i') }}</div><div class="col-md-6"><div class="text-muted small">Nomor ERP</div><strong>{{ $header->erp_doc_no ?: 'Belum dikirim' }}</strong></div><div class="col-12"><div class="text-muted small">Kerusakan / Keluhan</div>{{ $header->complaint ?: '—' }}</div></div></div>
<div class="filter-panel p-3"><h2 class="h6 fw-bold">Sparepart yang ditawarkan</h2><div class="table-responsive"><table class="table table-bordered align-middle small"><thead class="table-light"><tr><th>#</th><th>Kode Awal</th><th>Sparepart Dipilih</th><th>Database / Gudang</th><th class="text-end">Qty</th><th class="text-end">Harga/Unit</th><th class="text-end">Diskon</th><th class="text-end">Total Bersih</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line->line_no }}</td><td>{{ $line->requested_sparepart_code }}</td><td><strong>{{ $line->sparepart_code }}</strong><div class="text-muted">{{ $line->sparepart_name }}</div>@if($line->similar_depth > 0)<span class="badge bg-warning text-dark">Similar lvl {{ $line->similar_depth }}</span>@endif</td><td>{{ $line->source }}<div class="text-muted">{{ $line->warehouse_name }} ({{ $line->warehouse_code }})</div></td><td class="text-end">{{ number_format((float)$line->qty, 0, ',', '.') }}</td><td class="text-end">Rp {{ number_format((float)$line->unit_price, 0, ',', '.') }}</td><td class="text-end">{{ ($line->discount_type ?? 'none') === 'percent' ? number_format((float)$line->discount_value, 2, ',', '.').'%' : ((($line->discount_type ?? 'none') === 'amount') ? 'Rp '.number_format((float)$line->discount_value, 0, ',', '.') : '-') }}</td><td class="text-end">Rp {{ number_format((float)$line->line_total, 0, ',', '.') }}</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="7" class="text-end">TOTAL PENAWARAN</th><th class="text-end">Rp {{ number_format((float)$header->subtotal, 0, ',', '.') }}</th></tr></tfoot></table></div>
<p class="small text-muted mb-0">Harga merupakan snapshot RETAIL saat draft disimpan; stok dapat berubah. Stok dihitung dalam UOM terkecil, sedangkan harga mengikuti UOM pada price level. Verifikasi kesamaan UOM, kompatibilitas similar item dan persetujuan teknisi sebelum transaksi ERP.</p></div>
@endsection
