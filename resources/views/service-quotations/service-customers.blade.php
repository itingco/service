@extends('layouts.app')
@section('title', 'Master Customer Service')
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <div class="small text-muted fw-bold">MASTER DATA / SERVICE</div>
    <h1 class="h4 fw-bold">Master Customer per Service Type</h1>
    <p class="text-muted small">CustomerID ERP ditentukan sekali untuk setiap Service Type di tiap database. Nama Customer pada penawaran tidak akan diganti oleh nama master ERP.</p>
  </div>
  <a href="{{ route('service-quotations.service-centers', ['source' => $source]) }}" class="btn btn-outline-secondary btn-sm">Master Alamat Service Center</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($needsMigration)<div class="alert alert-warning">Tabel mapping belum tersedia di DB_AppHub. Jalankan <code>php INSTALL_SERVICE_CUSTOMERS_APPHUB.php</code> terlebih dahulu.</div>@endif
@if($typeError)<div class="alert alert-warning">{{ $typeError }}</div>@endif
<div class="row g-3">
  <div class="col-lg-7">
    <section class="filter-panel p-3 p-lg-4">
      <form method="GET" action="{{ route('service-quotations.service-customers') }}" class="mb-3">
        <label class="form-label fw-bold" for="source">Database ERP</label>
        <select class="form-select" id="source" name="source" onchange="this.form.submit()">
          <option value="INGCO" @selected($source === 'INGCO')>AS_INGCO — INGCO</option>
          <option value="SMI" @selected($source === 'SMI')>AS_SMI — WADFOW</option>
        </select>
      </form>
      <form method="POST" action="{{ route('service-quotations.service-customers.save') }}" id="mappingForm">
        @csrf
        <input type="hidden" name="erp_source" value="{{ $source }}">
        <div class="mb-3">
          <label class="form-label fw-bold" for="typeCode">Service Type</label>
          <select class="form-select" name="doc_type_code" id="typeCode" required @disabled($needsMigration || empty($docTypes))>
            @foreach($docTypes as $type)
              <option value="{{ $type['code'] }}" @selected(old('doc_type_code') === $type['code'])>{{ $type['code'] }} — {{ $type['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="alert alert-light border small" id="existingMapping" aria-live="polite">Belum ada customer ERP yang dipetakan.</div>
        <div class="mb-3">
          <label class="form-label fw-bold" for="customerQuery">Cari Customer dari AR_Customers</label>
          <input type="search" id="customerQuery" class="form-control" autocomplete="off" maxlength="80" placeholder="Ketik minimal 2 karakter: nama / kode / CustomerID" @disabled($needsMigration || empty($docTypes))>
          <div class="form-text">Pencarian hanya membaca AR_Customers pada AS_{{ $source }}. Maksimal 30 hasil.</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" for="customerResults">Pilih Customer ERP</label>
          <select class="form-select" id="customerResults" size="6" aria-label="Hasil pencarian customer ERP" @disabled($needsMigration || empty($docTypes))></select>
          <div class="form-text" id="customerFeedback" aria-live="polite">Pilih Service Type, kemudian cari customer.</div>
        </div>
        <input type="hidden" name="customer_id" id="customerId" value="{{ old('customer_id') }}">
        <div class="small mb-3"><strong>Customer terpilih:</strong> <span id="selectedCustomer">Belum dipilih</span></div>
        <button type="submit" class="btn btn-report" id="saveMapping" @disabled($needsMigration || empty($docTypes))>Simpan Mapping CustomerID</button>
      </form>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="filter-panel p-3 p-lg-4">
      <h2 class="h6 fw-bold">Mapping Customer pada AS_{{ $source }}</h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle small">
          <thead><tr><th>Service Type</th><th>CustomerID ERP</th></tr></thead>
          <tbody>
          @forelse($docTypes as $type)
            @php $mapping = $mappings[strtoupper($type['code'])] ?? null; @endphp
            <tr>
              <td><strong>{{ $type['code'] }}</strong><div class="text-muted">{{ $type['label'] }}</div></td>
              <td>
                @if($mapping)
                  <strong>{{ $mapping['customer_id'] }}</strong>
                  <div class="text-muted">{{ $mapping['customer_code'] }} — {{ $mapping['customer_name'] }}</div>
                @else
                  <span class="text-warning fw-bold">Belum diatur</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="2" class="text-muted">Belum ada Service Type dari ERP.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
@push('scripts')
<script>
(() => {
  const mappings = @json($mappings);
  const source = @json($source);
  const lookupUrl = @json(route('service-quotations.customers'));
  const code = document.getElementById('typeCode');
  const search = document.getElementById('customerQuery');
  const results = document.getElementById('customerResults');
  const id = document.getElementById('customerId');
  const selected = document.getElementById('selectedCustomer');
  const status = document.getElementById('existingMapping');
  const feedback = document.getElementById('customerFeedback');
  const form = document.getElementById('mappingForm');
  let found = [];
  let requestId = 0;
  let abort = null;
  const label = c => `${c.customer_id} — ${c.customer_code || '(tanpa kode)'} — ${c.customer_name}`;
  function selectCustomer(c) {
    if (!c) { id.value = ''; selected.textContent = 'Belum dipilih'; return; }
    id.value = String(c.customer_id);
    selected.textContent = label(c);
  }
  function changeType() {
    ++requestId;
    if (abort) abort.abort();
    results.replaceChildren(); found = []; search.value = '';
    const c = mappings[(code.value || '').toUpperCase()] ?? null;
    status.textContent = c ? 'Mapping tersimpan: ' + label(c) : 'Belum ada mapping untuk Service Type ini.';
    // Do not silently reuse an old mapping when changing to another Service Type.
    selectCustomer(c);
    feedback.textContent = 'Cari customer ERP, atau simpan kembali mapping yang ada.';
  }
  code?.addEventListener('change', changeType);
  let timer;
  search?.addEventListener('input', () => {
    clearTimeout(timer);
    const query = search.value.trim();
    if (abort) abort.abort();
    ++requestId;
    results.replaceChildren(); found = [];
    if (query.length < 2) { feedback.textContent = 'Ketik minimal 2 karakter.'; return; }
    const seq = requestId;
    timer = setTimeout(async () => {
      abort = new AbortController();
      feedback.textContent = 'Mencari customer ERP...';
      try {
        const url = new URL(lookupUrl, window.location.href);
        url.searchParams.set('source', source); url.searchParams.set('q', query);
        const response = await fetch(url.toString(), { headers: {'Accept':'application/json'}, credentials:'same-origin', signal: abort.signal });
        const data = await response.json();
        if (seq !== requestId) return;
        if (!response.ok) throw Error(data.message || 'Pencarian gagal.');
        found = data.customers || [];
        for (const c of found) {
          const opt = document.createElement('option');
          opt.value = String(c.customer_id);
          opt.textContent = label(c); // never write ERP-controlled text as HTML
          results.appendChild(opt);
        }
        feedback.textContent = found.length ? `${found.length} hasil. Klik salah satu customer untuk memilih.` : 'Tidak ditemukan customer dengan kata kunci tersebut.';
      } catch (e) {
        if (e.name !== 'AbortError' && seq === requestId) feedback.textContent = e.message || 'Gagal mencari.';
      }
    }, 250);
  });
  results?.addEventListener('change', () => {
    selectCustomer(found.find(c => String(c.customer_id) === results.value) || null);
  });
  form?.addEventListener('submit', e => {
    if (!id.value || Number(id.value) <= 0) { e.preventDefault(); feedback.textContent = 'Pilih Customer ERP terlebih dahulu.'; }
  });
  changeType();
})();
</script>
@endpush
@endsection
