<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tanda Terima Service {{ $header->erp_doc_no ?: $header->quote_no }}</title>
<style>
*{box-sizing:border-box}body{background:#ddd;color:#111;margin:0;font:11px Arial,Helvetica,sans-serif}.actions{max-width:80mm;margin:10px auto;display:flex;gap:12px;align-items:center}.actions button{padding:8px 13px;background:#ffa400;border:0;cursor:pointer}.receipt{width:80mm;background:#fff;margin:auto;padding:4mm 3mm;min-height:115mm}.center{text-align:center}.address{white-space:pre-line;line-height:1.3;margin-top:3px}.heading{text-align:center;font-weight:bold;font-size:13px;border-top:2px solid;border-bottom:2px solid;padding:5px 0;margin:9px 0 4px}.details{width:100%;border-collapse:collapse;table-layout:fixed}.details td{padding:1px 0;vertical-align:top;overflow-wrap:anywhere}.details td:first-child{width:27%}.details td:nth-child(2){width:4%}.dotted{border-top:1px dotted #444;margin:7px 0 5px}.signature{text-align:center;padding:4px 0;height:67px}.signature .line{height:42px;border-bottom:1px solid #000;width:54%;margin:auto}.status{margin:7px 0 3px}.small{font-size:9px}.footer{padding-top:4px;margin-top:5px;border-top:1px dotted #333}.brand{display:flex;justify-content:space-between;font-size:8px;gap:6px}.product-name{text-align:center;font-weight:bold;overflow-wrap:anywhere}@media print{body{background:#fff}.actions{display:none}.receipt{margin:0;width:74mm;padding:0;min-height:0}@page{size:80mm auto;margin:3mm}}
</style></head><body>
<div class="actions"><button type="button" onclick="window.print()">Cetak Struk</button><a href="{{ route('service-quotations.show', $header->id) }}">Kembali</a></div>
<main class="receipt">
<div class="center"><strong>{{ strtoupper((string)$header->doc_type_label) }}</strong><div class="address">{{ $serviceCenterAddress ?: 'Alamat service center belum diatur' }}</div></div>
<div class="heading">TANDA TERIMA SERVICE</div>
<table class="details">
<tr><td>Nomor</td><td>:</td><td>{{ $header->erp_doc_no ?: $header->quote_no }}</td></tr>
<tr><td>Tanggal</td><td>:</td><td>{{ \Illuminate\Support\Carbon::parse($header->created_at)->format('d/m/Y H:i') }}</td></tr>
<tr><td>Customer</td><td>:</td><td>{{ $header->customer_name }}</td></tr>
<tr><td>Telepon</td><td>:</td><td>{{ $header->customer_phone ?: '-' }}</td></tr>
<tr><td>Input By</td><td>:</td><td>{{ $header->created_by ?: '-' }}</td></tr>
</table>
<div class="dotted"></div>
<table class="details">
<tr><td>Serial Number</td><td>:</td><td>{{ $header->serial_number ?? '-' }}</td></tr>
<tr><td>Description</td><td>:</td><td>{{ $header->service_description ?: ($header->complaint ?: '-') }}</td></tr>
<tr><td>Accessories</td><td>:</td><td>{{ $header->accessories ?: '-' }}</td></tr>
</table>
<div class="dotted"></div>
<div class="product-name">({{ $header->damaged_item_name }})<div>{{ $header->damaged_item_code }}</div></div>
<div class="dotted"></div>
<div class="signature">Diterima Oleh<div class="line"></div></div>
<div class="status"><strong>Service Status:</strong> {{ $header->service_status ?: '-' }}</div>
<div class="dotted"></div>
<div>NB :</div>
<div class="footer center">Terimakasih Atas Kunjungannya</div>
<div class="brand" style="margin-top:8px"><span>{{ strtoupper((string)$header->erp_source) === 'SMI' ? 'WADFOW' : 'IG@INGCOINDONESIA' }}</span><span>{{ strtoupper((string)$header->erp_source) === 'SMI' ? '' : 'youtube.com/ingcoindonesia' }}</span></div>
</main></body></html>
