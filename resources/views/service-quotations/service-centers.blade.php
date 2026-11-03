@extends('layouts.app')
@section('title', 'Master Alamat Service Center')
@section('content')
<div class="d-flex justify-content-between flex-wrap align-items-center gap-2 mb-3">
  <div>
    <div class="text-uppercase text-muted small fw-bold">Penawaran Service / Master Data</div>
    <h1 class="h4 fw-bold mb-1">Master Alamat Service Center</h1>
    <div class="text-muted small">Satu alamat untuk setiap Service Type pada AS_INGCO dan AS_SMI. Alamat akan muncul otomatis pada printout penawaran.</div>
  </div>
  <a href="{{ route('service-quotations.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali ke Penawaran</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($needsMigration)<div class="alert alert-warning"><strong>Master alamat belum tersedia.</strong> Jalankan <code>php INSTALL_SERVICE_ADDRESS_APPHUB.php</code> dari folder root Laravel. Installer hanya membuat tabel di DB_AppHub.</div>@endif
@if($typeError)<div class="alert alert-warning">{{ $typeError }}</div>@endif
<div class="row g-3">
  <div class="col-lg-7">
    <section class="filter-panel p-3 p-md-4">
      <h2 class="h6 fw-bold mb-3">Pengaturan alamat</h2>
      <form method="GET" action="{{ route('service-quotations.service-centers') }}" id="selectSourceForm" class="mb-3">
        <label for="sourceSelect" class="form-label fw-bold">Database / Brand</label>
        <select id="sourceSelect" name="source" class="form-select" onchange="this.form.submit()">
          <option value="INGCO" @selected($source === 'INGCO')>AS_INGCO — INGCO</option>
          <option value="SMI" @selected($source === 'SMI')>AS_SMI — WADFOW</option>
        </select>
      </form>
      <form method="POST" action="{{ route('service-quotations.service-centers.save') }}" id="addressForm">
        @csrf
        <input type="hidden" name="erp_source" value="{{ $source }}">
        <div class="mb-3">
          <label for="serviceCode" class="form-label fw-bold">Service Type</label>
          <select id="serviceCode" class="form-select" name="doc_type_code" required @disabled($needsMigration || $docTypes === [])>
            @foreach($docTypes as $type)
              <option value="{{ $type['code'] }}" @selected(old('doc_type_code') === $type['code'])>{{ $type['code'] }} — {{ $type['label'] }}</option>
            @endforeach
          </select>
          <div class="form-text">Daftar Service Type diambil dari <code>USP_HRM_DocTypes_QueryAll</code> pada database yang dipilih.</div>
        </div>
        <div class="mb-3">
          <label for="addressText" class="form-label fw-bold">Alamat untuk printout</label>
          <textarea id="addressText" name="address" class="form-control" rows="7" maxlength="3000" placeholder="Nama Service Center&#10;Alamat lengkap, jalan / nomor, kelurahan, kota, provinsi&#10;Kode pos / Telepon (jika perlu)" @disabled($needsMigration || $docTypes === [])>{{ old('address') }}</textarea>
          <div class="form-text">Gunakan Enter untuk memisahkan baris alamat. Data disimpan di DB_AppHub. Kosongkan lalu Simpan jika ingin menghapus alamat yang lama.</div>
        </div>
        <button class="btn btn-report" type="submit" @disabled($needsMigration || $docTypes === [])><i class="bi bi-check-circle me-1"></i> Simpan Alamat</button>
      </form>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="filter-panel p-3 p-md-4">
      <h2 class="h6 fw-bold">Status Alamat AS_{{ $source }}</h2>
      <p class="text-muted small">Alamat di kanan logo akan mengikuti Service Type pada masing-masing penawaran.</p>
      <div class="table-responsive"><table class="table table-sm align-middle small">
        <thead><tr><th>Service Type</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($docTypes as $type)
          <tr><td><strong>{{ $type['code'] }}</strong><div class="text-muted">{{ $type['label'] }}</div></td>
              <td>@if(trim((string)($addresses[strtoupper($type['code'])] ?? '')) !== '')<span class="badge bg-success">Terisi</span>@else<span class="badge bg-warning text-dark">Belum diisi</span>@endif</td></tr>
        @empty<tr><td colspan="2" class="text-muted">Belum ada Service Type yang tersedia.</td></tr>@endforelse
        </tbody>
      </table></div>
    </section>
  </div>
</div>
@push('scripts')
<script>
(() => {
  const addresses = @json($addresses);
  const code = document.getElementById('serviceCode');
  const editor = document.getElementById('addressText');
  const oldAddress = @json(old('address'));
  if (!code || !editor) return;
  function updateAddress() {
    editor.value = addresses[code.value.toUpperCase()] ?? '';
  }
  code.addEventListener('change', updateAddress);
  if (oldAddress === null) updateAddress();
})();
</script>
@endpush
@endsection
