@extends('layouts.app')
@section('title', 'Business Intelligence & R&D')
@push('styles')
<style>
.bi-hub-card{border:1px solid #d0d5dd;border-radius:12px;background:white;padding:1.1rem;height:100%}
.bi-hub-card h2{font-size:1rem;font-weight:800}
.bi-hub-card p{color:#667085;font-size:.85rem}
.bi-panel{border:1px solid #d0d5dd;border-radius:12px;background:white;overflow:hidden}
.bi-panel iframe{display:block;width:100%;min-height:82vh;border:0;background:#f3f5f8}
.bi-chip{font-size:.7rem;color:#667085}
</style>
@endpush
@section('content')
<h1 class="h4 fw-bold mb-1">Business Intelligence &amp; R&amp;D</h1>
<p class="text-secondary">Lima area keputusan bisnis dalam satu menu. Pilih area untuk memuat analisis sesuai kebutuhan; analisis lainnya tidak dimuat bersamaan.</p>
<div class="row g-3 mb-3" id="biHubSections">
@foreach([
 ['key'=>'sales','icon'=>'bi-graph-up-arrow','title'=>'Sales Performance & Forecast','desc'=>'Tren penjualan, pencapaian target, forecast, dan pertumbuhan MoM/YoY.'],
 ['key'=>'product','icon'=>'bi-box-seam','title'=>'Product Performance','desc'=>'Produk bertumbuh/menurun dan prioritas product focus.'],
 ['key'=>'customer','icon'=>'bi-people','title'=>'Customer Movement','desc'=>'Perubahan kontribusi customer, aktif, dan berisiko.'],
 ['key'=>'channel','icon'=>'bi-shop','title'=>'Channel Performance','desc'=>'Kinerja online/offline dan pendorong penjualan.'],
 ['key'=>'strategy','icon'=>'bi-lightbulb','title'=>'Strategic Recommendations','desc'=>'Prioritas tindakan berdasarkan analisis produk dan penjualan.'],
] as $section)
<div class="col-md-6 col-xl-4"><div class="bi-hub-card">
  <h2><i class="bi {{ $section['icon'] }} me-2"></i>{{ $section['title'] }}</h2>
  <p>{{ $section['desc'] }}</p>
  <button type="button" class="btn btn-outline-dark btn-sm" data-bi-open="{{ $section['key'] }}">Buka analisis</button>
</div></div>
@endforeach
</div>
<section class="bi-panel d-none" id="biHubPanel" aria-label="Analisis terpilih">
<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
<strong id="biHubTitle">Analisis</strong><button class="btn btn-sm btn-outline-secondary" type="button" id="biHubClose">Tutup</button>
</div>
<div class="px-3 py-2 small text-secondary" id="biHubNote" role="status">Data dimuat saat dibuka.</div>
<iframe id="biHubFrame" title="Detail analisis" loading="eager" referrerpolicy="same-origin"></iframe>
</section>
@endsection
@push('scripts')
<script>
(() => {
  const routes = {
    sales: @json(route('business-analytics.index')),
    product: @json(route('sales-insights.index')),
    customer: @json(route('business-analytics.index')),
    channel: @json(route('business-analytics.index')),
    strategy: @json(route('business-analytics.product-advisor.index'))
  };
  const names = {
    sales:'Sales Performance & Forecast',product:'Product Performance',customer:'Customer Movement',
    channel:'Channel Performance',strategy:'Strategic Recommendations'
  };
  const panel = document.getElementById('biHubPanel');
  const frame = document.getElementById('biHubFrame');
  const note = document.getElementById('biHubNote');
  const title = document.getElementById('biHubTitle');
  const EMBED_STYLE_ID = 'bi-hub-embedded-view';
  const embeddedCss = `
    .app-sidebar, .sidebar-backdrop, .mobile-topbar { display:none !important; }
    .app-content, body.sidebar-collapsed .app-content {
      margin-left:0 !important; padding-top:0 !important;
      min-height:100vh !important;
    }
    .app-content > main.page-shell { max-width:none !important; }
    body.sidebar-mobile-open { overflow:auto !important; }
  `;

  function removeNestedSidebar() {
    try {
      // Semua URL analisis adalah origin yang sama. Tidak mengubah autentikasi atau permission.
      const doc = frame.contentDocument;
      if (!doc || !doc.head || !doc.body) return;
      let css = doc.getElementById(EMBED_STYLE_ID);
      if (!css) {
        css = doc.createElement('style');
        css.id = EMBED_STYLE_ID;
        css.textContent = embeddedCss;
        doc.head.appendChild(css);
      }
      note.textContent = 'Analisis ditampilkan tanpa sidebar tambahan.';
    } catch (error) {
      note.textContent = 'Halaman analisis tidak dapat disesuaikan. Pastikan halaman dibuka dari domain yang sama.';
      console.error('Business Intelligence embedded view:', error);
    }
  }

  // Terapkan ulang jika navigasi atau form GET mengganti dokumen iframe.
  frame.addEventListener('load', removeNestedSidebar);
  document.querySelectorAll('[data-bi-open]').forEach(button => button.addEventListener('click', () => {
    const key = button.dataset.biOpen;
    if (!Object.prototype.hasOwnProperty.call(routes, key)) return;
    title.textContent = names[key];
    note.textContent = 'Memuat analisis...';
    panel.classList.remove('d-none');
    frame.src = routes[key];
    panel.scrollIntoView({behavior:'smooth',block:'start'});
  }));
  document.getElementById('biHubClose').addEventListener('click', () => {
    frame.removeAttribute('src');
    panel.classList.add('d-none');
  });
})();
</script>
@endpush
