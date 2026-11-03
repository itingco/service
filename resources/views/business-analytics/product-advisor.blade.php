@extends('layouts.app')

@section('title', 'Product & Sales Strategy Advisor')

@php
    $money = static function (mixed $value): string {
        $n = (float) ($value ?? 0);
        if (abs($n) >= 1000000000) return 'Rp '.number_format($n / 1000000000, 2, ',', '.').' M';
        if (abs($n) >= 1000000) return 'Rp '.number_format($n / 1000000, 1, ',', '.').' Jt';
        return 'Rp '.number_format($n, 0, ',', '.');
    };
    $pct = static fn (mixed $value): string => $value === null ? '—' : number_format((float)$value * 100, 1, ',', '.').'%';
    $num = static fn (mixed $value): string => number_format((float)($value ?? 0), 0, ',', '.');
    $profile = $facts['profile'] ?? [];
    $diagnosis = $facts['diagnosis'] ?? [];
    $crossSell = $facts['cross_sell_candidates'] ?? [];
    $relatedItems = $facts['related_items'] ?? [];
    $strategies = $strategy['strategies'] ?? [];
    $pickerSelectedCode = '';
    $pickerSelectedName = '';
    if ($facts) {
        $pickerSelectedCode = (string) ($facts['item_code'] ?? $itemCode);
        $pickerSelectedName = (string) ($facts['item_name'] ?? '');
    } elseif ($itemCode !== '') {
        foreach ($items as $candidate) {
            if (strcasecmp((string) ($candidate['item_code'] ?? ''), $itemCode) === 0) {
                $pickerSelectedCode = (string) $candidate['item_code'];
                $pickerSelectedName = (string) ($candidate['item_name'] ?? '');
                break;
            }
        }
    }
    $pickerSearchValue = $pickerSelectedCode !== ''
        ? $pickerSelectedCode.($pickerSelectedName !== '' ? ' — '.$pickerSelectedName : '')
        : $itemCode;
@endphp

@push('styles')
<style>
    .pa-hero{background:linear-gradient(135deg,#101828,#1f3b63);color:#fff;border-radius:14px;padding:1.15rem 1.25rem}.pa-hero p{color:#d0d5dd;margin:.3rem 0 0;font-size:.78rem}.pa-card{background:#fff;border:1px solid #d0d5dd;border-radius:11px;padding:1rem;box-shadow:0 5px 14px rgba(16,24,40,.05);height:100%}.pa-kpi-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;font-weight:900;color:#667085}.pa-kpi-value{font-size:1.05rem;font-weight:900;color:#101828;margin:.25rem 0}.pa-section-title{font-size:1rem;font-weight:900;color:#101828;margin:0}.pa-sub{font-size:.72rem;color:#667085}.pa-label{display:inline-flex;border-radius:999px;padding:.22rem .48rem;font-size:.61rem;font-weight:900;border:1px solid #d0d5dd;background:#f8fafc}.pa-label.fact{background:#eef4ff;color:#3538cd;border-color:#c7d7fe}.pa-label.inference{background:#f2f4f7;color:#344054}.pa-label.recommendation{background:#ecfdf3;color:#027a48;border-color:#abefc6}.pa-label.test{background:#fffaeb;color:#b54708;border-color:#fedf89}.pa-label.external{background:#f4f3ff;color:#5925dc;border-color:#d9d6fe}.pa-strategy{border:1px solid #eaecf0;border-left:4px solid #475467;border-radius:9px;padding:.85rem;margin-bottom:.7rem}.pa-strategy.online{border-left-color:#2563eb}.pa-strategy.offline{border-left-color:#15803d}.pa-strategy.omnichannel{border-left-color:#7c3aed}.pa-strategy h4{font-size:.86rem;font-weight:900;margin:0}.pa-mini{font-size:.7rem;color:#475467;line-height:1.45}.pa-table{font-size:.72rem}.pa-table th{white-space:nowrap;background:#f2f4f7}.pa-table td{vertical-align:top}.pa-chat{border:1px solid #eaecf0;border-radius:9px;padding:.75rem;background:#f8fafc}.pa-chat-answer{display:grid;gap:.65rem}.pa-answer-section{background:#fff;border:1px solid #eaecf0;border-radius:8px;padding:.7rem}.pa-source{word-break:break-word;font-size:.68rem}.pa-empty{padding:2rem;text-align:center;color:#667085}.pa-score{font-size:1rem;font-weight:900}.pa-status{border-radius:999px;padding:.28rem .6rem;font-weight:900;font-size:.65rem}.pa-scroll{overflow:auto;max-height:430px}.pa-experiment-form{background:#f8fafc;border:1px dashed #98a2b3;border-radius:9px;padding:.85rem}.pa-item-picker{position:relative}.pa-item-results{position:absolute;z-index:1050;top:100%;left:0;right:0;margin-top:.35rem;background:#fff;border:1px solid #d0d5dd;border-radius:10px;box-shadow:0 12px 28px rgba(16,24,40,.16);max-height:360px;overflow:auto}.pa-item-result{width:100%;border:0;border-bottom:1px solid #eaecf0;background:#fff;text-align:left;padding:.7rem .8rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.pa-item-result:last-child{border-bottom:0}.pa-item-result:hover,.pa-item-result.active{background:#f2f4f7}.pa-item-code{font-weight:900;color:#101828}.pa-item-name{font-size:.75rem;color:#475467;margin-top:.1rem}.pa-item-sales{white-space:nowrap;font-size:.68rem;color:#667085}.pa-item-empty{padding:1rem;text-align:center;color:#667085;font-size:.75rem}
</style>
@endpush

@section('content')
<div class="pa-hero mb-3 d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
    <div>
        <div class="small fw-bold text-warning">BUSINESS ANALYTICS · PHASE 3</div>
        <h1 class="h4 fw-bold mb-0">Product &amp; Sales Strategy Advisor</h1>
        <p>Fakta internal → diagnosis → target market → strategi online/offline → experiment terukur → SCALE / MODIFY / STOP.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('business-analytics.index') }}" class="btn btn-sm btn-light">Kembali ke Business Analytics</a>
        @if($reviewId > 0)<a href="{{ route('business-analytics.product-advisor.export.pdf', ['review_id'=>$reviewId]) }}" class="btn btn-sm btn-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Export Review PDF</a>@endif
        <span class="btn btn-sm btn-outline-light disabled">{{ $provider['badge'] }}</span>
    </div>
</div>

@if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger"><strong>Input belum valid.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif

<section class="pa-card mb-3">
    <form id="productAdvisorForm" method="GET" action="{{ route('business-analytics.product-advisor.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-lg-5 pa-item-picker">
            <label class="form-label fw-bold" for="productAdvisorSearch">Cari ItemCode / ItemName</label>
            <input id="productAdvisorSearch" class="form-control" type="search" value="{{ $pickerSearchValue }}" placeholder="Cari kode atau nama item, contoh: gergaji / IDLI2002" autocomplete="off" aria-autocomplete="list" aria-controls="productAdvisorResults" aria-expanded="false" required>
            <input id="productAdvisorItemCode" type="hidden" name="item_code" value="{{ $pickerSelectedCode }}">
            <div id="productAdvisorResults" class="pa-item-results d-none" role="listbox" aria-label="Hasil pencarian produk"></div>
            <div id="productAdvisorSelectionError" class="invalid-feedback">Pilih item dari hasil pencarian.</div>
            <div class="pa-sub mt-1">Klik field untuk melihat item teratas, atau ketik sebagian nama/kode produk.</div>
        </div>
        <div class="col-6 col-lg-2"><label class="form-label fw-bold">Source</label><select id="productAdvisorSource" class="form-select" name="source"><option value="ALL" @selected($source==='ALL')>INGCO &amp; SMI</option><option value="INGCO" @selected($source==='INGCO')>INGCO</option><option value="SMI" @selected($source==='SMI')>SMI</option></select></div>
        <div class="col-6 col-lg-2"><label class="form-label fw-bold">Review sampai</label><input type="month" class="form-control" name="end_month" value="{{ $endMonth }}"></div>
        <div class="col-12 col-lg-3 d-grid"><button class="btn btn-report" type="submit">Review Product</button></div>
    </form>
</section>

@if (!$facts)
    <div class="pa-card pa-empty">
        <div class="fw-bold mb-1">Pilih produk yang ingin direview</div>
        <div>Advisor akan membaca histori penjualan, customer, channel, item terkait, forecast, dan membuat strategi yang bisa diuji.</div>
    </div>
@else
<div class="d-flex flex-column flex-xl-row justify-content-between gap-2 align-items-xl-center mb-2">
    <div><h2 class="h5 fw-bold mb-0">{{ $facts['item_code'] }} · {{ $facts['item_name'] }}</h2><div class="pa-sub">Snapshot review #{{ $reviewId }} · {{ $source }} · sampai {{ $endMonth }}</div></div>
    <div class="d-flex gap-2 flex-wrap">@foreach(($diagnosis['labels'] ?? []) as $label)<span class="pa-status bg-dark text-white">{{ $label }}</span>@endforeach</div>
</div>

<div class="row g-2 mb-3">
    @foreach([
        ['Sales 3M',$money($facts['sales_3m'] ?? 0)],['YoY 3M',$pct($facts['yoy_growth'] ?? null)],['Momentum 3M',$pct($facts['momentum_3m'] ?? null)],
        ['Online Share',$pct($facts['online_share'] ?? null)],['Active Buyers',$num($facts['active_buyers'] ?? 0)],['Lost Revenue',$money($facts['lost_revenue'] ?? 0)],
        ['Stock Input',isset($profile['current_stock']) && $profile['current_stock']!==null ? $num($profile['current_stock']) : 'Belum diisi'],['Forecast Accuracy',$pct($facts['forecast_backtest']['accuracy_score'] ?? null)]
    ] as $kpi)
        <div class="col-6 col-lg-3 col-xxl-2"><div class="pa-card"><div class="pa-kpi-label">{{ $kpi[0] }}</div><div class="pa-kpi-value">{{ $kpi[1] }}</div></div></div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <section class="pa-card">
            <div class="d-flex justify-content-between gap-2 mb-3"><div><h3 class="pa-section-title">Diagnosis Produk</h3><div class="pa-sub">Angka berasal dari data internal. Inference ditulis sebagai indikasi, bukan klaim kausal.</div></div><span class="pa-label fact">FACT ENGINE</span></div>
            @forelse(($diagnosis['facts'] ?? []) as $fact)
                <div class="border-bottom py-2"><span class="pa-label fact me-2">FACT</span><strong>{{ $fact['metric'] ?? 'Metric' }}</strong>: {{ is_numeric($fact['value'] ?? null) ? number_format((float)$fact['value'],4,',','.') : ($fact['value'] ?? '—') }}</div>
            @empty <div class="pa-sub">Belum ada evidence khusus dari rule diagnosis.</div> @endforelse
            @foreach(($diagnosis['inferences'] ?? []) as $inference)
                <div class="mt-2 p-2 rounded bg-light"><span class="pa-label inference me-2">INFERENCE · {{ $inference['confidence'] ?? 'MEDIUM' }}</span>{{ $inference['text'] }}</div>
            @endforeach
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="pa-card">
            <h3 class="pa-section-title mb-1">Product Context</h3><div class="pa-sub mb-3">Isi spesifikasi agar target market dan selling angle semakin tajam.</div>
            <form method="POST" action="{{ route('business-analytics.product-advisor.profile') }}" class="row g-2">@csrf
                <input type="hidden" name="item_code" value="{{ $facts['item_code'] }}"><input type="hidden" name="item_name" value="{{ $facts['item_name'] }}"><input type="hidden" name="source" value="{{ $source }}"><input type="hidden" name="end_month" value="{{ $endMonth }}">
                <div class="col-12"><label class="form-label">Spesifikasi *</label><textarea class="form-control form-control-sm" rows="3" name="specifications" required>{{ $profile['specifications'] ?? '' }}</textarea></div>
                <div class="col-6"><label class="form-label">Kegunaan</label><textarea class="form-control form-control-sm" rows="2" name="use_cases">{{ $profile['use_cases'] ?? '' }}</textarea></div>
                <div class="col-6"><label class="form-label">Keunggulan</label><textarea class="form-control form-control-sm" rows="2" name="advantages">{{ $profile['advantages'] ?? '' }}</textarea></div>
                <div class="col-4"><label class="form-label">Harga Referensi</label><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="reference_price" value="{{ $profile['reference_price'] ?? '' }}"></div>
                <div class="col-4"><label class="form-label">Stock</label><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="current_stock" value="{{ $profile['current_stock'] ?? '' }}"></div>
                <div class="col-4"><label class="form-label">Target Margin %</label><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="target_margin_percent" value="{{ $profile['target_margin_percent'] ?? '' }}"></div>
                <div class="col-6"><label class="form-label">Kompetitor / Catatan</label><textarea class="form-control form-control-sm" rows="2" name="competitor_notes">{{ $profile['competitor_notes'] ?? '' }}</textarea></div>
                <div class="col-6"><label class="form-label">Constraint Promo</label><textarea class="form-control form-control-sm" rows="2" name="constraints">{{ $profile['constraints'] ?? '' }}</textarea></div>
                <div class="col-12 d-grid"><button class="btn btn-sm btn-outline-primary">Simpan Product Context &amp; Re-review</button></div>
            </form>
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <section class="pa-card">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h3 class="pa-section-title">Peluang Pasar</h3><div class="pa-sub">Lihat siapa yang cocok ditawarkan, alasannya, dan aksi pertama yang bisa dicoba.</div></div>
                <form method="POST" action="{{ route('business-analytics.product-advisor.research') }}">@csrf<input type="hidden" name="review_id" value="{{ $reviewId }}"><button class="btn btn-sm btn-outline-primary" @disabled(!$provider['configured'])>Cari Ide dari Internet / AI</button></form>
            </div>
            @if(($provider['state'] ?? '')==='DISABLED')
                <div class="alert alert-secondary py-2 small"><strong>AI external masih OFF.</strong> Ranking tetap bekerja dari data internal. Jalankan aktivasi Hybrid AI untuk menyalakan internet research.</div>
            @elseif(($provider['state'] ?? '')==='NEEDS_KEY')
                <div class="alert alert-warning py-2 small"><strong>AI belum siap:</strong> provider OpenAI sudah dipilih tetapi API key belum tersedia.</div>
            @elseif(($provider['state'] ?? '')==='READY')
                <div class="alert alert-success py-2 small"><strong>Hybrid AI ready.</strong> Research Internet akan memakai {{ $provider['display_model'] }} dan evidence publik.</div>
            @else
                <div class="alert alert-warning py-2 small">Provider AI belum didukung oleh konfigurasi saat ini.</div>
            @endif
            @if($research && ($research['status'] ?? '')==='FAILED')<div class="alert alert-warning py-2 small">{{ $research['summary'] ?? 'External research gagal.' }}</div>@endif
            <div class="table-responsive"><table class="table table-sm table-bordered pa-table mb-0"><thead><tr><th>Target</th><th>Skor</th><th>Channel</th><th>Kenapa Cocok</th><th>Cara Menawarkan</th><th>Aksi yang Dicoba</th></tr></thead><tbody>
                @forelse($market as $row)<tr>
                    <td><div class="fw-bold">{{ $row['segment_display'] ?? $row['segment'] }}</div></td>
                    <td><span class="pa-score">{{ $row['fit_score'] }}</span>/100</td>
                    <td>{{ $row['preferred_channel'] ?? '—' }}</td>
                    <td>{{ $row['reason_simple'] ?? ($row['why'] ?? '—') }}</td>
                    <td>{{ $row['offer_simple'] ?? ($row['selling_angle'] ?? '—') }}</td>
                    <td>{{ $row['action_simple'] ?? ($row['first_test'] ?? '—') }}</td>
                </tr>
                @empty <tr><td colspan="6" class="text-center text-secondary">Isi spesifikasi produk untuk melihat peluang pasar.</td></tr> @endforelse
            </tbody></table></div>
            @if($research && ($research['sources'] ?? [])!==[])<div class="mt-3"><span class="pa-label external">SUMBER INTERNET</span><div class="mt-2">@foreach($research['sources'] as $src)<div class="pa-source"><a href="{{ $src['url'] }}" target="_blank" rel="noopener noreferrer">{{ $src['title'] }}</a></div>@endforeach</div></div>@endif
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="pa-card">
            <h3 class="pa-section-title">Customer yang Bisa Ditawarkan</h3><div class="pa-sub mb-2">Customer aktif yang sudah membeli produk terkait, tetapi belum membeli item ini.</div>
            <div class="pa-scroll"><table class="table table-sm pa-table"><thead><tr><th>Skor</th><th>Customer</th><th>Sales</th><th>Kenapa</th></tr></thead><tbody>
                @forelse(array_slice($crossSell,0,30) as $row)<tr><td class="fw-bold">{{ $row['opportunity_score'] }}</td><td><div class="fw-bold">{{ $row['customer_name'] ?: $row['customer_code'] }}</div><div class="text-secondary">{{ $row['customer_code'] }}</div></td><td>{{ $row['salesperson_name'] ?: '—' }}</td><td>{{ $row['reason'] }}</td></tr>
                @empty <tr><td colspan="4" class="text-secondary text-center">Belum ditemukan kandidat cross-sell dari pola basket.</td></tr> @endforelse
            </tbody></table></div>
        </section>
    </div>
</div>

<section class="pa-card mb-3">
    <div class="d-flex justify-content-between gap-2 mb-3"><div><h3 class="pa-section-title">Saran Tindakan</h3><div class="pa-sub">Pilih satu tindakan kecil, jalankan, ukur hasilnya, lalu putuskan lanjut atau ubah.</div></div><span class="pa-label recommendation">AKSI TERUKUR</span></div>
    <div class="row g-3">
        @foreach($strategies as $s)
            <div class="col-12 col-xl-6"><div class="pa-strategy {{ strtolower($s['channel']) }} h-100">
                <div class="d-flex justify-content-between gap-2"><h4>{{ $s['title_display'] ?? $s['title'] }}</h4><div><span class="pa-label recommendation">{{ $s['priority'] }}</span> <span class="pa-label">{{ $s['channel'] }}</span></div></div>
                <div class="pa-mini mt-2"><strong>Kenapa:</strong> {{ $s['reason_simple'] ?? $s['hypothesis'] }}</div>
                <div class="pa-mini mt-1"><strong>Target:</strong> {{ $s['target_simple'] ?? $s['target_audience'] }}</div>
                <div class="pa-mini mt-1"><strong>Tawarkan:</strong> {{ $s['offer_simple'] ?? $s['offer'] }}</div>
                <div class="pa-mini mt-1"><strong>Coba:</strong> {{ $s['duration_days'] }} hari</div>
                <div class="pa-mini mt-1"><strong>Ukur:</strong> {{ $s['measure_simple'] ?? implode(', ', $s['kpis']) }}</div>
                <div class="pa-mini mt-1"><strong>Berhasil jika:</strong> {{ $s['success_simple'] ?? 'Hasil lebih baik dari sebelum test.' }}</div>
                <div class="pa-mini mt-1"><strong>Ubah / hentikan jika:</strong> {{ $s['stop_simple'] ?? $s['stop_rule'] }}</div>
                <div class="mt-2">@foreach(($s['evidence'] ?? []) as $e)<span class="pa-label fact me-1">DATA</span><span class="pa-mini me-2">{{ $e }}</span>@endforeach</div>
                <div class="mt-2"><button type="button" class="btn btn-sm btn-outline-warning fw-bold" data-use-strategy data-strategy='{{ json_encode(["title"=>$s["title_display"]??$s["title"],"channel"=>$s["channel"],"hypothesis"=>$s["reason_simple"]??$s["hypothesis"],"audience"=>$s["target_simple"]??$s["target_audience"],"offer"=>$s["offer_simple"]??$s["offer"],"duration"=>$s["duration_days"],"revenue_min"=>$s["success_threshold"]["revenue_uplift_min"]??0.10,"qty_min"=>$s["success_threshold"]["quantity_uplift_min"]??0.05,"stop_rule"=>$s["stop_simple"]??$s["stop_rule"]], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) }}'>Jadikan Tes Penjualan</button></div>
            </div></div>
        @endforeach
    </div>
</section>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <section class="pa-card" data-product-advisor-chat>
            <div class="d-flex justify-content-between gap-2 mb-2"><div><h3 class="pa-section-title">Tanya Advisor</h3><div class="pa-sub">Tanya: kenapa turun, stok 500 pcs harus diapakan, online vs offline, promo apa yang perlu dites, atau target market apa.</div></div><span class="pa-label {{ $provider['configured'] ? 'external' : 'inference' }}">{{ $provider['configured'] ? 'HYBRID AI' : 'DETERMINISTIC' }}</span></div>
            <div class="pa-chat mb-2"><textarea class="form-control" rows="3" data-advisor-question placeholder="Contoh: Stok saya 500 pcs. Dalam 2 bulan strategi online dan offline apa yang paling masuk akal untuk diuji?"></textarea><div class="d-flex justify-content-end mt-2"><button type="button" class="btn btn-sm btn-primary" data-advisor-submit>Minta Saran</button></div></div>
            <div class="alert alert-danger d-none" data-advisor-error></div><div class="pa-chat-answer" data-advisor-answer></div>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="pa-card">
            <h3 class="pa-section-title">Item yang Cocok Dibundle</h3><div class="pa-sub mb-2">Berdasarkan customer yang membeli item yang sama dalam 12 bulan.</div>
            <table class="table table-sm pa-table"><thead><tr><th>Item</th><th>Customer Sama</th><th>Sales</th></tr></thead><tbody>@forelse(array_slice($relatedItems,0,10) as $r)<tr><td>{{ $r['item_code'] }}<div class="text-secondary">{{ $r['item_name'] }}</div></td><td>{{ $r['buyer_overlap'] }}</td><td>{{ $money($r['sales']) }}</td></tr>@empty<tr><td colspan="3" class="text-secondary text-center">Belum ada pola related item.</td></tr>@endforelse</tbody></table>
        </section>
    </div>
</div>

<section class="pa-card mb-3" id="experimentLab">
    <div class="d-flex justify-content-between gap-2 mb-3"><div><h3 class="pa-section-title">Catatan Tes Penjualan</h3><div class="pa-sub">Bandingkan hasil sebelum dan sesudah tes. Lanjutkan yang bekerja, ubah yang tidak.</div></div><span class="pa-label test">COBA → UKUR → PUTUSKAN</span></div>
    <div class="row g-3">
        <div class="col-12 col-xl-5"><form method="POST" action="{{ route('business-analytics.product-advisor.experiments.create') }}" class="pa-experiment-form" data-experiment-form>@csrf
            <input type="hidden" name="review_id" value="{{ $reviewId }}"><input type="hidden" name="item_code" value="{{ $facts['item_code'] }}"><input type="hidden" name="source" value="{{ $source }}">
            <div class="row g-2"><div class="col-8"><label class="form-label">Nama Tes</label><input class="form-control form-control-sm" name="title" required></div><div class="col-4"><label class="form-label">Channel</label><select class="form-select form-select-sm" name="channel"><option>ONLINE</option><option>OFFLINE</option><option>OMNICHANNEL</option></select></div>
            <div class="col-12"><label class="form-label">Yang Ingin Diuji</label><textarea class="form-control form-control-sm" rows="2" name="hypothesis" required></textarea></div><div class="col-12"><label class="form-label">Target Customer</label><textarea class="form-control form-control-sm" rows="2" name="target_audience" required></textarea></div><div class="col-12"><label class="form-label">Penawaran / Aksi</label><textarea class="form-control form-control-sm" rows="2" name="offer_description" required></textarea></div>
            <div class="col-6"><label class="form-label">Mulai</label><input class="form-control form-control-sm" type="date" name="start_date" required></div><div class="col-6"><label class="form-label">Selesai</label><input class="form-control form-control-sm" type="date" name="end_date" required></div><div class="col-6"><label class="form-label">Target Kenaikan Penjualan</label><input class="form-control form-control-sm" type="number" step="0.01" name="revenue_uplift_min" value="0.10"></div><div class="col-6"><label class="form-label">Target Kenaikan Qty</label><input class="form-control form-control-sm" type="number" step="0.01" name="quantity_uplift_min" value="0.05"></div>
            <div class="col-12"><label class="form-label">Batas Ubah / Berhenti</label><textarea class="form-control form-control-sm" rows="2" name="stop_rule" required>Jika hasil awal tidak membaik, ubah target atau penawaran sebelum dilanjutkan.</textarea></div><div class="col-12 d-grid"><button class="btn btn-sm btn-warning fw-bold">Buat Tes Penjualan</button></div></div>
        </form></div>
        <div class="col-12 col-xl-7"><div class="table-responsive"><table class="table table-sm table-bordered pa-table"><thead><tr><th>Tes</th><th>Periode</th><th>Status</th><th>Keputusan</th><th>Aksi</th></tr></thead><tbody>
            @forelse($experiments as $e)<tr><td><div class="fw-bold">#{{ $e['id'] }} {{ $e['title'] }}</div><div class="text-secondary">{{ $e['channel'] }} · {{ $e['target_audience'] }}</div></td><td>{{ $e['start_date'] }}<br>{{ $e['end_date'] }}</td><td>{{ $e['status'] }}</td><td><strong>{{ $e['decision'] ?: '—' }}</strong></td><td><div class="d-flex flex-wrap gap-1"><form method="POST" action="{{ route('business-analytics.product-advisor.experiments.evaluate', ['experiment'=>$e['id']]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Cek Hasil</button></form><form method="POST" action="{{ route('business-analytics.product-advisor.experiments.update', ['experiment'=>$e['id']]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="RUNNING"><button class="btn btn-sm btn-outline-success">Mulai Tes</button></form></div></td></tr>
            @empty<tr><td colspan="5" class="text-secondary text-center">Belum ada experiment untuk item ini.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
</section>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('productAdvisorForm');
    const searchInput = document.getElementById('productAdvisorSearch');
    const itemCodeInput = document.getElementById('productAdvisorItemCode');
    const resultsPanel = document.getElementById('productAdvisorResults');
    const sourceSelect = document.getElementById('productAdvisorSource');
    if (!form || !searchInput || !itemCodeInput || !resultsPanel || !sourceSelect) return;

    const itemSearchUrl = {{ Illuminate\Support\Js::from(route('business-analytics.product-advisor.items')) }};
    const initialItems = {{ Illuminate\Support\Js::from($items) }};
    let visibleItems = Array.isArray(initialItems) ? initialItems : [];
    let activeIndex = -1;
    let timer = null;
    let requestController = null;

    const rupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID', {maximumFractionDigits: 0}).format(Number(value || 0));
    const openResults = () => {
        resultsPanel.classList.remove('d-none');
        searchInput.setAttribute('aria-expanded', 'true');
    };
    const closeResults = () => {
        resultsPanel.classList.add('d-none');
        searchInput.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    };
    const syncActive = () => {
        resultsPanel.querySelectorAll('.pa-item-result').forEach((button, index) => {
            button.classList.toggle('active', index === activeIndex);
        });
    };
    const selectItem = (item) => {
        itemCodeInput.value = String(item.item_code || '');
        searchInput.value = itemCodeInput.value + (item.item_name ? ' — ' + item.item_name : '');
        searchInput.classList.remove('is-invalid');
        closeResults();
    };
    const renderResults = (items, emptyMessage = 'Item tidak ditemukan.') => {
        visibleItems = Array.isArray(items) ? items : [];
        activeIndex = -1;
        resultsPanel.replaceChildren();
        if (visibleItems.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'pa-item-empty';
            empty.textContent = emptyMessage;
            resultsPanel.appendChild(empty);
            openResults();
            return;
        }
        visibleItems.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pa-item-result';
            button.setAttribute('role', 'option');

            const identity = document.createElement('div');
            const code = document.createElement('div');
            code.className = 'pa-item-code';
            code.textContent = item.item_code || '-';
            const name = document.createElement('div');
            name.className = 'pa-item-name';
            name.textContent = item.item_name || item.item_code || '-';
            identity.append(code, name);

            const sales = document.createElement('div');
            sales.className = 'pa-item-sales';
            sales.textContent = rupiah(item.historical_sales);
            button.append(identity, sales);
            button.addEventListener('click', () => selectItem(item));
            resultsPanel.appendChild(button);
        });
        openResults();
    };
    const fetchItems = async (query) => {
        if (requestController) requestController.abort();
        requestController = new AbortController();
        const params = new URLSearchParams({q: query, source: sourceSelect.value});
        try {
            const response = await fetch(itemSearchUrl + '?' + params.toString(), {
                headers: {'Accept': 'application/json'},
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error('Gagal mencari item.');
            const payload = await response.json();
            renderResults(payload.data || []);
        } catch (error) {
            if (error.name === 'AbortError') return;
            renderResults([], 'Pencarian item gagal. Coba lagi.');
        }
    };

    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim() === '' && visibleItems.length > 0) renderResults(visibleItems);
    });
    searchInput.addEventListener('input', () => {
        itemCodeInput.value = '';
        searchInput.classList.remove('is-invalid');
        clearTimeout(timer);
        const query = searchInput.value.trim();
        timer = setTimeout(() => fetchItems(query), 220);
    });
    searchInput.addEventListener('keydown', (event) => {
        if (resultsPanel.classList.contains('d-none')) return;
        if (event.key === 'ArrowDown' && visibleItems.length > 0) {
            event.preventDefault();
            activeIndex = Math.min(activeIndex + 1, visibleItems.length - 1);
            syncActive();
        } else if (event.key === 'ArrowUp' && visibleItems.length > 0) {
            event.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            syncActive();
        } else if (event.key === 'Enter' && activeIndex >= 0 && visibleItems[activeIndex]) {
            event.preventDefault();
            selectItem(visibleItems[activeIndex]);
        } else if (event.key === 'Escape') {
            closeResults();
        }
    });
    sourceSelect.addEventListener('change', () => {
        itemCodeInput.value = '';
        searchInput.value = '';
        fetchItems('');
    });
    form.addEventListener('submit', (event) => {
        if (itemCodeInput.value.trim() !== '') return;
        event.preventDefault();
        searchInput.classList.add('is-invalid');
        searchInput.focus();
        renderResults(visibleItems, 'Ketik nama atau kode item untuk mencari.');
    });
    document.addEventListener('mousedown', (event) => {
        if (!form.contains(event.target)) closeResults();
    });
})();
</script>
@if($facts)
<script>
window.productStrategyAdvisorConfig = {{ Illuminate\Support\Js::from([
    'askSelesaipoint'=>route('business-analytics.product-advisor.ask'),
    'reviewId'=>$reviewId,
    'csrf'=>csrf_token(),
]) }};
</script>
<script src="{{ asset('js/product-strategy-advisor.js') }}"></script>
@endif
@endpush
