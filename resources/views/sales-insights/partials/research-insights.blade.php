<section class="insight-panel rd-section mt-4" aria-label="research-insights">
    <div class="insight-section-head">
        <div>
            <h2 class="h5 fw-bold mb-1">R&amp;D Insight — Product Demand &amp; Portfolio Direction</h2>
            <div class="small text-secondary">Membandingkan item MTD dengan periode sama bulan lalu untuk menentukan produk yang perlu di-scale, divalidasi, dievaluasi, atau dihentikan.</div>
        </div>
        <span class="status-pill">{{ count($research->productSignals) }} produk dianalisis</span>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['SCALE UP','Produk siap diperluas'],
            ['PERMINTAAN BARU','Demand baru untuk validasi'],
            ['EVALUASI','Demand turun signifikan'],
            ['PERMINTAAN HILANG','Terjual sebelumnya, nol sekarang'],
            ['RISIKO KONSENTRASI','Bergantung pada sedikit customer'],
            ['FASE KELUAR','Discontinued / item reject bergerak'],
        ] as [$signal,$label])
            <div class="col-6 col-lg-4 col-xxl-2"><div class="rd-summary-card"><div class="rd-summary-value">{{ $research->signalCounts[$signal] ?? 0 }}</div><div class="rd-summary-label">{{ $signal }}</div><div class="small text-secondary mt-1">{{ $label }}</div></div></div>
        @endforeach
    </div>

    <div class="insight-panel mb-3">
        <div class="insight-panel-head"><div><h3 class="insight-title">Product Momentum &amp; Rekomendasi R&amp;D</h3><div class="insight-subtitle">Prioritas didasarkan pada pertumbuhan demand, luas customer, konsentrasi, channel, diskon, dan lifecycle.</div></div></div>
        <div class="table-responsive insight-table-wrap" style="max-height:560px">
            <table class="table table-sm align-middle mb-0 insight-table">
                <thead><tr><th>Produk</th><th>Grade</th><th class="text-end">MTD</th><th class="text-end">LMTD</th><th class="text-end">Growth</th><th class="text-end">Qty</th><th class="text-end">Customer</th><th class="text-end">Top Customer</th><th>Channel Utama</th><th class="text-end">Share Channel</th><th class="text-end">Avg Discount</th><th>Signal</th><th>Rekomendasi</th></tr></thead>
                <tbody>
                @forelse (array_slice($research->productSignals, 0, 30) as $product)
                    @php($signalClass = strtolower(str_replace(' ', '-', $product['signal'])))
                    <tr>
                        <td><strong>{{ $product['label'] }}</strong></td><td>{{ $product['grade'] }}</td><td class="text-end">{{ $number($product['current_sales']) }}</td><td class="text-end">{{ $number($product['previous_sales']) }}</td><td class="text-end {{ ($product['growth'] ?? 0) < 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}">{{ $percent($product['growth']) }}</td><td class="text-end">{{ number_format($product['quantity'],2,',','.') }}</td><td class="text-end">{{ $product['customer_count'] }}</td><td class="text-end">{{ $percent($product['top_customer_share']) }}</td><td>{{ $product['primary_line'] }}</td><td class="text-end">{{ $percent($product['primary_line_share']) }}</td><td class="text-end">{{ $plainPercent($product['average_discount']) }}</td><td><span class="signal-badge signal-{{ $signalClass }}">{{ $product['signal'] }}</span></td><td class="recommendation">{{ $product['recommendation'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="text-center text-secondary py-3">Data item belum tersedia.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6"><div class="insight-panel h-100"><div class="insight-panel-head"><div><h3 class="insight-title">Demand Baru</h3><div class="insight-subtitle">Item yang terjual sekarang tetapi tidak terjual pada periode pembanding.</div></div></div>@forelse(array_slice($research->newDemand,0,10) as $row)<div class="entity-row"><div class="entity-label">{{ $row['label'] }}</div><div class="entity-sales">Rp {{ $number($row['current_sales']) }}</div><div class="entity-percent">{{ $row['customer_count'] }} cust</div></div>@empty<div class="insight-empty">Tidak ada demand baru yang terdeteksi.</div>@endforelse</div></div>
        <div class="col-12 col-xl-6"><div class="insight-panel h-100"><div class="insight-panel-head"><div><h3 class="insight-title">Demand Hilang</h3><div class="insight-subtitle">Item yang terjual pada LMTD tetapi tidak terjual pada MTD.</div></div></div>@forelse(array_slice($research->lostDemand,0,10) as $row)<div class="entity-row"><div class="entity-label">{{ $row['label'] }}</div><div class="entity-sales">LMTD Rp {{ $number($row['previous_sales']) }}</div><div class="entity-percent">cek</div></div>@empty<div class="insight-empty">Tidak ada demand hilang yang terdeteksi.</div>@endforelse</div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-4"><div class="insight-panel h-100"><div class="insight-panel-head"><div><h3 class="insight-title">Customer Concentration per Produk</h3><div class="insight-subtitle">Risiko produk terlihat kuat hanya karena satu customer.</div></div></div>@forelse(array_slice($research->concentrationRisks,0,8) as $row)<div class="mix-row"><span>{{ $row['label'] }}</span><strong>Rp {{ $number($row['current_sales']) }}</strong><em>{{ $percent($row['top_customer_share']) }}</em></div>@empty<div class="insight-empty">Tidak ada konsentrasi ≥70%.</div>@endforelse</div></div>
        <div class="col-12 col-xl-4"><div class="insight-panel h-100"><div class="insight-panel-head"><div><h3 class="insight-title">Discount Effectiveness Watch</h3><div class="insight-subtitle">Produk turun atau stagnan meskipun memiliki input diskon.</div></div></div>@forelse(array_slice($research->discountWatch,0,8) as $row)<div class="mix-row"><span>{{ $row['label'] }}</span><strong>{{ $percent($row['growth']) }}</strong><em>{{ $plainPercent($row['average_discount']) }}</em></div>@empty<div class="insight-empty">Tidak ada indikasi diskon tidak efektif.</div>@endforelse</div></div>
        <div class="col-12 col-xl-4"><div class="insight-panel h-100"><div class="insight-panel-head"><div><h3 class="insight-title">Grade Demand</h3><div class="insight-subtitle">Arah demand per grade untuk evaluasi portfolio.</div></div></div>@forelse($research->gradeDemand as $row)<div class="mix-row"><span>Grade {{ $row['grade'] }}</span><strong>Rp {{ $number($row['current_sales']) }}</strong><em>{{ $percent($row['growth']) }}</em></div>@empty<div class="insight-empty">Data grade belum tersedia.</div>@endforelse</div></div>
    </div>

    <div class="insight-panel mt-3"><div class="insight-panel-head"><div><h3 class="insight-title">Channel–Product Fit</h3><div class="insight-subtitle">Produk nomor satu pada setiap LiniPenjualan untuk membantu pemilihan channel uji dan peluncuran.</div></div></div><div class="table-responsive"><table class="table table-sm insight-table mb-0"><thead><tr><th>LiniPenjualan</th><th>Produk Terkuat</th><th class="text-end">Omzet</th><th class="text-end">Share Lini</th><th>Arahan</th></tr></thead><tbody>@forelse($research->channelFit as $row)<tr><td class="fw-bold">{{ $row['line'] }}</td><td>{{ trim($row['item_code'].' — '.$row['item_name'],' —') }}</td><td class="text-end">{{ $number($row['sales']) }}</td><td class="text-end">{{ $percent($row['share_of_line']) }}</td><td>Gunakan lini ini sebagai channel utama untuk validasi demand, stok, dan komunikasi produk.</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary">Data channel belum tersedia.</td></tr>@endforelse</tbody></table></div></div>
</section>
