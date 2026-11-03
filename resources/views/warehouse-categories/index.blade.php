@extends('layouts.app')
@section('title', 'Master Klasifikasi Gudang')
@push('styles')
<style>
.wc-shell{max-width:1500px;margin:auto}.wc-hero{background:linear-gradient(110deg,#101828,#263e5a);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:18px}.wc-panel{background:#fff;border:1px solid #d9e0ea;border-radius:13px;overflow:hidden;box-shadow:0 4px 14px rgba(16,24,40,.035)}.wc-panel+.wc-panel{margin-top:12px}.wc-header{display:flex;align-items:center;gap:12px;padding:16px 18px;cursor:pointer;list-style:none}.wc-header::-webkit-details-marker{display:none}.wc-header:after{content:'⌄';font-size:22px;color:#667085;margin-left:auto;transition:transform .2s}.wc-panel[open]>.wc-header:after{transform:rotate(180deg)}.wc-count{border-radius:99px;background:#eff4fb;color:#375474;padding:4px 9px;font-size:.75rem;font-weight:700}.wc-content{border-top:1px solid #e7ecf2;padding:16px 18px}.wc-chips{display:flex;flex-wrap:wrap;gap:8px}.wc-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:8px;background:#f6f8fb;border:1px solid #e2e7ef;font-size:.84rem;overflow-wrap:anywhere}.wc-source{font-size:.68rem;font-weight:800;background:#dce8fb;color:#194d8d;border-radius:5px;padding:2px 5px}.wc-empty{border:1px dashed #d2dae7;padding:15px;border-radius:9px;color:#667085}.wc-pick{border:1px solid #d6deea;border-radius:10px;overflow:hidden}.wc-pick-top{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px;border-bottom:1px solid #e4e9f1;background:#fafbfc}.wc-pick-list{max-height:290px;overflow:auto;padding:8px}.wc-pick-row{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:7px;cursor:pointer;font-size:.86rem}.wc-pick-row:hover{background:#f3f6fa}.wc-pick-row input{width:17px;height:17px;flex-shrink:0;accent-color:#d49e00}.wc-pick-row .wc-warehouse-name{min-width:0;overflow-wrap:anywhere}.wc-pick-row[hidden]{display:none!important}.wc-form-actions{display:flex;justify-content:flex-end;gap:9px;flex-wrap:wrap;margin-top:14px}.wc-edit{margin-top:16px;padding:16px;background:#fafcff;border:1px solid #dee6f0;border-radius:10px}.wc-management{display:none}.wc-management.is-open{display:block}.wc-title{font-weight:800;font-size:1rem;margin:0}.wc-header-buttons{display:flex;align-items:center;gap:7px;margin-left:auto}.wc-toggle{display:flex;align-items:center;gap:7px}.wc-error{font-size:.82rem;color:#b42318}.wc-hidden-count{font-size:.8rem;color:#667085}.wc-section-heading{font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#607089}.wc-create-box{padding:18px}.wc-checkbox-tools{display:flex;gap:8px;flex-wrap:wrap}.wc-header:focus-visible{outline:3px solid #ffc000;outline-offset:-3px}@media(max-width:650px){.wc-hero{padding:18px}.wc-header{flex-wrap:wrap;padding:13px}.wc-content{padding:13px}.wc-header-buttons{width:100%;justify-content:flex-end}}
</style>
@endpush
@section('content')
@php
    $membership = [];
    $warehouseMap = [];
    foreach ($warehouses as $w) {
        $warehouseMap[(string) $w['key']] = $w;
    }
    foreach ($members as $m) {
        $membership[(int) $m['CategoryID']][] = strtoupper((string) $m['SourceCode']).'|'.(int) $m['WarehouseID'];
    }
    $warehouseCount = count($warehouses);
@endphp
<div class="wc-shell">
    <div class="wc-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="text-warning fw-bold small mb-1">MASTER DATA • INVENTORY</div>
            <h1 class="h4 fw-bold mb-1">Klasifikasi Gudang</h1>
            <div class="text-white-50 small">Kelola kategori sebagai header dan gudang sebagai anggota detail. Tidak mengubah master gudang ERP.</div>
        </div>
        <button class="btn btn-warning fw-bold" type="button" id="wcShowCreate"><i class="bi bi-plus-lg me-1"></i> Tambah Kategori</button>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <section class="wc-panel mb-3 wc-management {{ $errors->any() ? 'is-open' : '' }}" id="wcCreatePanel" aria-label="Tambah kategori gudang">
        <div class="wc-create-box">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="wc-title">Buat kategori baru</h2><button type="button" class="btn-close" id="wcCloseCreate" aria-label="Tutup"></button></div>
            <form method="POST" action="{{ route('warehouse-categories.store') }}">
                @csrf
                <label class="form-label fw-bold">Nama Kategori</label>
                <input class="form-control mb-3" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Contoh: GUDANG JTE" required>
                <div class="form-label fw-bold">Detail Gudang <span class="text-secondary fw-normal">(bisa dipilih lebih dari satu)</span></div>
                @include('warehouse-categories._warehouse-picker', ['pickerId' => 'wc-new', 'warehouseOptions' => $warehouses, 'selectedKeys' => old('warehouses', [])])
                <div class="wc-form-actions"><button type="button" class="btn btn-outline-secondary" data-cancel-create>Batal</button><button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-floppy me-1"></i> Simpan Kategori</button></div>
            </form>
        </div>
    </section>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 class="wc-section-heading mb-0">Daftar Kategori ({{ count($categories) }})</h2><div class="small text-secondary">Klik header untuk melihat detail gudang</div></div>
    @forelse($categories as $category)
        @php
            $id = (int) $category['CategoryID'];
            $selected = $membership[$id] ?? [];
            $selected = array_values(array_unique($selected));
        @endphp
        <details class="wc-panel" id="wc-category-{{ $id }}">
            <summary class="wc-header">
                <span class="bg-warning bg-opacity-25 text-dark rounded-3 p-2"><i class="bi bi-folder2-open"></i></span>
                <div class="flex-grow-1"><h3 class="wc-title">{{ $category['CategoryName'] }}</h3><div class="small text-secondary">Kategori #{{ $id }}</div></div>
                <span class="wc-count">{{ count($selected) }} gudang</span>
            </summary>
            <div class="wc-content">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3"><div class="wc-section-heading">Detail Gudang</div><button type="button" class="btn btn-sm btn-outline-primary" data-edit-target="wc-edit-{{ $id }}"><i class="bi bi-pencil-square me-1"></i> Edit kategori / anggota</button></div>
                @if(count($selected) === 0)
                    <div class="wc-empty">Belum ada gudang dalam kategori ini. Klik <strong>Edit kategori / anggota</strong> untuk menambahkannya.</div>
                @else
                    <div class="wc-chips">
                    @foreach($selected as $key)
                        @php $w = $warehouseMap[$key] ?? null; $source = explode('|', $key)[0] ?? ''; @endphp
                        <span class="wc-chip"><span class="wc-source">{{ $source }}</span>{{ $w['name'] ?? ('Gudang #'.(explode('|', $key)[1] ?? '?').' (tidak ditemukan di ERP)') }}</span>
                    @endforeach
                    </div>
                @endif
                <div class="wc-edit wc-management" id="wc-edit-{{ $id }}">
                    <form method="POST" action="{{ route('warehouse-categories.update', $id) }}">
                        @csrf @method('PUT')
                        <label class="form-label fw-bold">Nama Kategori</label>
                        <input class="form-control mb-3" name="name" value="{{ $category['CategoryName'] }}" maxlength="100" required>
                        <label class="form-label fw-bold">Pilih Anggota Gudang</label>
                        @include('warehouse-categories._warehouse-picker', ['pickerId' => 'wc-edit-picker-'.$id, 'warehouseOptions' => $warehouses, 'selectedKeys' => $selected])
                        <div class="wc-form-actions"><button type="button" class="btn btn-outline-secondary" data-close-edit="wc-edit-{{ $id }}">Batal</button><button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button></div>
                    </form>
                    <div class="border-top mt-3 pt-3 d-flex justify-content-end">
                        <form method="POST" action="{{ route('warehouse-categories.destroy', $id) }}" onsubmit="return confirm('Hapus kategori {{ addslashes($category['CategoryName']) }} beserta seluruh anggotanya?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i> Hapus kategori</button>
                        </form>
                    </div>
                </div>
            </div>
        </details>
    @empty
        <div class="wc-panel p-4 text-center text-secondary">Belum ada kategori gudang. Klik <strong>Tambah Kategori</strong> untuk memulai.</div>
    @endforelse
</div>
@endsection
@push('scripts')
<script>
(function () {
    'use strict';
    const create = document.getElementById('wcCreatePanel');
    document.getElementById('wcShowCreate')?.addEventListener('click', () => {create.classList.add('is-open'); create.querySelector('[name="name"]')?.focus();});
    const closeCreate = () => create.classList.remove('is-open');
    document.getElementById('wcCloseCreate')?.addEventListener('click', closeCreate);
    document.querySelectorAll('[data-cancel-create]').forEach(btn => btn.addEventListener('click', closeCreate));
    document.querySelectorAll('[data-edit-target]').forEach(btn => btn.addEventListener('click', () => {const panel = document.getElementById(btn.dataset.editTarget); if(panel) {panel.classList.toggle('is-open'); if(panel.classList.contains('is-open')) panel.querySelector('[name="name"]')?.focus();}}));
    document.querySelectorAll('[data-close-edit]').forEach(btn => btn.addEventListener('click', () => document.getElementById(btn.dataset.closeEdit)?.classList.remove('is-open')));
    document.querySelectorAll('[data-wc-picker]').forEach(picker => {
        const search = picker.querySelector('[data-wc-search]');
        const checkboxes = Array.from(picker.querySelectorAll('input[type="checkbox"]'));
        const count = picker.querySelector('[data-wc-count]');
        const visible = () => checkboxes.filter(ch => !ch.closest('.wc-pick-row').hidden);
        const update = () => {count.textContent = checkboxes.filter(ch => ch.checked).length + ' gudang dipilih';};
        const filter = () => {
            const q = (search.value || '').toLocaleLowerCase('id-ID').trim();
            checkboxes.forEach(ch => {ch.closest('.wc-pick-row').hidden = !ch.closest('.wc-pick-row').textContent.toLocaleLowerCase('id-ID').includes(q);});
            const note = picker.querySelector('[data-wc-visible]');
            if(note) note.textContent = visible().length + ' dari ' + checkboxes.length + ' gudang';
        };
        search?.addEventListener('input', filter);
        picker.querySelector('[data-wc-all]')?.addEventListener('click', () => {visible().forEach(ch => {ch.checked = true;}); update();});
        picker.querySelector('[data-wc-none]')?.addEventListener('click', () => {visible().forEach(ch => {ch.checked = false;}); update();});
        checkboxes.forEach(ch => ch.addEventListener('change', update));
        filter(); update();
    });
})();
</script>
@endpush
