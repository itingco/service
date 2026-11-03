<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Penawaran Perbaikan {{ $header->quote_no }}</title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#222;max-width:980px;margin:22px auto;padding:0 18px;font-size:12px}
  .actions{margin-bottom:18px;display:flex;align-items:center;gap:12px}button{padding:10px 17px;border:0;background:#ffa400;color:#111;font-weight:bold;cursor:pointer}
  .letterhead{display:flex;align-items:center;justify-content:space-between;gap:24px;border-bottom:3px solid #ffa400;padding-bottom:16px;margin-bottom:13px;page-break-inside:avoid}
  .logo-area{width:40%;min-width:145px;display:flex;align-items:center;justify-content:flex-start}
  .logo{display:block;max-width:200px;max-height:88px;width:auto;height:auto;object-fit:contain;object-position:left center}
  .logo.ingco{max-width:165px;max-height:85px}
  .logo.wadfow{max-width:205px;max-height:80px}
  .service-center-address{width:58%;text-align:right;min-width:0;line-height:1.5;font-size:11px;overflow-wrap:anywhere}
  .service-center-address .center-name{font-weight:800;font-size:13px;margin-bottom:3px}
  .service-center-address .address-text{white-space:pre-line}
  .address-unset{color:#a05300;font-style:italic}
  .document-heading{margin-bottom:18px;page-break-inside:avoid}
  .document-heading h1{font-size:20px;line-height:1.25;margin:0 0 6px}
  .document-heading .sub{font-size:11px;color:#555}
  .info{display:grid;grid-template-columns:1fr 1fr;gap:8px 22px;margin:14px 0 20px}.info div{line-height:1.8;min-width:0;overflow-wrap:anywhere}
  table{width:100%;border-collapse:collapse;margin:16px 0;table-layout:fixed}th,td{border:1px solid #aaa;padding:9px 7px;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#f2f2f2}
  .right{text-align:right}.notice{font-size:11px;color:#555;line-height:1.65}.free-service{margin-top:18px;padding:12px 14px;border:1px solid #e8b42e;background:#fff8e5;line-height:1.6;page-break-inside:avoid}
  .free-service strong{display:block;margin-bottom:4px;color:#303030}.document-footer{margin-top:24px;page-break-inside:avoid}
  .confirmation-term{margin-top:10px;border-left:3px solid #ffa400;background:#fff9ea;padding:10px 12px;line-height:1.65;font-size:11px;page-break-inside:avoid}
  .confirmation-term strong{display:block;margin-bottom:3px}
  .signature{margin-top:46px;display:flex;justify-content:space-between;gap:10px;page-break-inside:avoid}
  .signature div{width:185px;text-align:center}.sign-line{border-bottom:1px solid #555;height:53px;margin-bottom:6px}
  @media(max-width:540px){.letterhead{gap:12px}.logo-area{width:36%;min-width:100px}.service-center-address{width:62%;font-size:10px}.logo{max-width:100%;max-height:70px}}
  @media print{body{max-width:none;margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}.actions{display:none}@page{size:A4;margin:14mm}}
</style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><a href="{{ route('service-quotations.show', $header->id) }}">Kembali ke Penawaran</a></div>
<div class="letterhead">
  <div class="logo-area">
    @if(strtoupper((string) $header->erp_source) === 'SMI')
      <img class="logo wadfow" src="{{ asset('images/service-quotation/wadfow.png') }}" alt="WADFOW">
    @else
      <img class="logo ingco" src="{{ asset('images/service-quotation/ingco-indonesia.png') }}" alt="INGCO Indonesia">
    @endif
  </div>
  <div class="service-center-address">
    <div class="center-name">{{ $header->doc_type_label }}</div>
    @if(trim($serviceCenterAddress) !== '')
      <div class="address-text">{{ $serviceCenterAddress }}</div>
    @else
      <div class="address-unset">Alamat Service Center belum diatur</div>
    @endif
  </div>
</div>
<div class="document-heading">
  <h1>PENAWARAN PERBAIKAN / SERVICE</h1>
  <div class="sub"><strong>{{ $header->quote_no }}</strong> — {{ \Illuminate\Support\Carbon::parse($header->created_at)->locale('id')->translatedFormat('d F Y') }}</div>
</div>
<div class="info">
  <div><strong>Kepada:</strong> {{ $header->customer_name }}<br><strong>Telepon:</strong> {{ $header->customer_phone ?? '—' }}<br><strong>Alamat:</strong> {{ $header->customer_address ?? '—' }}</div>
  <div><strong>Barang Rusak:</strong> {{ $header->damaged_item_code }}<br><strong>Deskripsi:</strong> {{ $header->damaged_item_name }}</div>
</div>
<p><strong>Keluhan / Kerusakan:</strong> {{ $header->complaint ?: '—' }}</p>
<table>
  <thead><tr><th style="width:6%">No.</th><th style="width:21%">Kode Sparepart</th><th>Nama / Keterangan</th><th class="right" style="width:8%">Qty</th><th class="right" style="width:16%">Harga Satuan</th><th class="right" style="width:12%">Diskon</th><th class="right" style="width:17%">Jumlah</th></tr></thead>
  <tbody>
  @foreach($lines as $line)
    <tr><td>{{ $line->line_no }}</td><td>{{ $line->sparepart_code }}</td><td>{{ $line->sparepart_name }}@if($line->similar_depth > 0)<div class="notice">Sparepart alternatif; kompatibilitas perlu diperiksa teknisi.</div>@endif</td><td class="right">{{ number_format((float)$line->qty, 0, ',', '.') }}</td><td class="right">Rp {{ number_format((float)$line->unit_price, 0, ',', '.') }}</td><td class="right">{{ ($line->discount_type ?? 'none') === 'percent' ? number_format((float)$line->discount_value, 2, ',', '.').'%' : ((($line->discount_type ?? 'none') === 'amount') ? 'Rp '.number_format((float)$line->discount_value, 0, ',', '.') : '-') }}</td><td class="right">Rp {{ number_format((float)$line->line_total, 0, ',', '.') }}</td></tr>
  @endforeach
  </tbody>
  <tfoot><tr><th colspan="6" class="right">TOTAL SPAREPART</th><th class="right">Rp {{ number_format((float)$header->subtotal, 0, ',', '.') }}</th></tr></tfoot>
</table>
<div class="signature">
  <div>Disiapkan oleh,<div class="sign-line"></div>(__________________)</div>
  <div>Disetujui pelanggan,<div class="sign-line"></div>(__________________)</div>
</div>
<footer class="document-footer">
<div class="free-service">
  <strong>INFORMASI BIAYA JASA PERBAIKAN</strong>
  Jasa perbaikan untuk barang ini diberikan <strong style="display:inline">tanpa biaya (gratis)</strong>.
  Perusahaan tidak menetapkan atau memungut tarif jasa perbaikan. Biaya dalam penawaran ini hanya mencakup sparepart yang tercantum dan disetujui pelanggan.
  Biaya lain, apabila ada, wajib diinformasikan dan disetujui terlebih dahulu.
</div>
<div class="confirmation-term">
  <strong>BATAS WAKTU KONFIRMASI PERBAIKAN</strong>
  Apabila pelanggan tidak memberikan konfirmasi atas penawaran perbaikan ini dalam waktu <strong style="display:inline">14 (empat belas) hari kerja</strong> sejak tanggal penawaran, maka proses perbaikan dianggap batal.
</div>
<p class="notice">Harga dan ketersediaan sparepart dapat berubah sebelum disetujui. Dokumen ini merupakan penawaran, bukan bukti pembayaran atau faktur penjualan.</p>
</footer>
</body>
</html>
