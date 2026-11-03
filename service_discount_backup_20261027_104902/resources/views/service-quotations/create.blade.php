@extends('layouts.app')
@section('title', isset($editHeader) ? 'Edit Draft Penawaran Service' : 'Buat Penawaran Service')
@push('styles')
<style>
.sq-panel{background:#fff;border:1px solid #e1e5eb;border-radius:13px;padding:1.2rem;margin-bottom:1rem;box-shadow:0 3px 14px rgba(0,0,0,.035)}
.sq-hero{background:linear-gradient(110deg,#182538,#34516b);color:#fff;border-radius:14px;padding:1.35rem;margin-bottom:1rem}
.sq-kicker{font-size:.74rem;text-transform:uppercase;letter-spacing:.09em;color:#ffc000;font-weight:800}
.sq-result{width:100%;text-align:left;border:1px solid #e1e5eb;border-radius:8px;background:#fff;padding:.55rem .8rem;margin:.2rem 0}
.sq-result:hover{background:#fff7df;border-color:#ffa400}
.sq-pill{display:inline-block;background:#f3f4f6;border-radius:20px;padding:.2rem .5rem;font-size:.73rem;font-weight:bold}
.sq-stage{width:27px;height:27px;display:inline-grid;place-items:center;background:#ffa400;color:#181818;border-radius:50%;font-weight:800;margin-right:.4rem}
.sq-small{font-size:.8rem}.sq-result-box{max-height:275px;overflow:auto}.sq-th{font-size:.78rem;background:#f8fafc}.sq-note{font-size:.78rem;color:#667085}
</style>
@endpush
@section('content')
<div class="sq-hero d-flex justify-content-between gap-2 flex-wrap align-items-center"><div><div class="sq-kicker">Service Center</div><h1 class="h4 fw-bold mb-1">{{ isset($editHeader) ? 'Edit Draft Penawaran Service' : 'Buat Penawaran Service' }}</h1><div class="small text-white-50">{{ isset($editHeader) ? 'Perbarui draft sebelum dikirim ke ERP. Stok dan harga diperiksa ulang.' : 'Pilih barang rusak, temukan sparepart, verifikasi stok & harga, lalu cetak.' }}</div></div><div class="d-flex gap-2 flex-wrap"><a href="{{ route('service-quotations.index') }}" class="btn btn-outline-light btn-sm">Riwayat Penawaran</a></div></div>
@if($errors->any())<div class="alert alert-danger"><strong>Penawaran belum disimpan.</strong> {{ $errors->first() }}</div>@endif
<div id="docTypeWarning" class="alert alert-warning" @if(!$typeError) hidden @endif><span id="docTypeWarningText">{{ $typeError }}</span> Penawaran tidak dapat disimpan sebelum Service Type tersedia.</div>
<form id="quotationForm" method="POST" action="{{ isset($editHeader) ? route('service-quotations.update', $editHeader->id) : route('service-quotations.store') }}" autocomplete="off">@csrf
@if(isset($editHeader)) @method('PUT') @endif
<section class="sq-panel"><h2 class="h6 fw-bold"><span class="sq-stage">1</span> Data Customer &amp; Barang Rusak</h2>
  <div class="row g-3 mt-1">
    <div class="col-md-6"><label class="form-label fw-bold">Nama Customer *</label><input class="form-control" name="customer_name" value="{{ old('customer_name', $editHeader->customer_name ?? '') }}" maxlength="200" required placeholder="Nama customer / perusahaan"></div>
    <div class="col-md-3"><label class="form-label fw-bold">Database Barang Rusak *</label><select id="erpSource" class="form-select" name="erp_source"><option value="INGCO" @selected($erpSource === 'INGCO')>AS_INGCO</option><option value="SMI" @selected($erpSource === 'SMI')>AS_SMI</option></select></div>
    <div class="col-md-3"><label class="form-label fw-bold">Type Invoice / Service *</label><select id="docType" class="form-select" name="doc_type_id" required @disabled($typeError !== null)><option value="">Pilih Service Type</option>@foreach($docTypes as $type)<option value="{{ $type['id'] }}" @selected((string) old('doc_type_id', $editHeader->doc_type_id ?? '') === (string) $type['id'])>{{ $type['label'] }} ({{ $type['code'] }})</option>@endforeach</select><div id="docTypeHelp" class="form-text">Service Type dari AS_{{ $erpSource }}.</div></div>
    <div class="col-12 col-lg-6"><label class="form-label fw-bold">Cari Kode / Nama Barang Rusak *</label><input id="damagedSearch" class="form-control" type="text" placeholder="Ketik minimal 2 karakter untuk mencari item" maxlength="90"><input id="damagedCode" name="damaged_item_code" type="hidden" value="{{ old('damaged_item_code', $editHeader->damaged_item_code ?? '') }}"><div id="damagedChosen" class="sq-note mt-1">Belum ada item terpilih</div><div id="itemResults" class="sq-result-box mt-1"></div></div>
    <div class="col-12 col-lg-6"><label class="form-label fw-bold">Keluhan / Kerusakan</label><textarea class="form-control" name="complaint" rows="3" maxlength="3000" placeholder="Contoh: mesin tidak berputar, gear aus">{{ old('complaint', $editHeader->complaint ?? '') }}</textarea></div>
  </div>
</section>
<section class="sq-panel"><h2 class="h6 fw-bold"><span class="sq-stage">2</span> Pilih Komponen yang Rusak</h2><div class="sq-note mb-2">Sistem mencari item <strong>KODE-BARANG-SP-...</strong> dari kode barang induk. Pada tahap ini tidak perlu menghitung stok.</div>
  <div id="partsStatus" class="sq-note">Pilih barang rusak terlebih dahulu.</div><div id="partResults" class="sq-result-box"></div>
</section>
<section class="sq-panel"><h2 class="h6 fw-bold"><span class="sq-stage">3</span> Stok, Similar Items &amp; Harga RETAIL</h2><div id="availabilityStatus" class="sq-note">Pilih komponen di langkah 2 untuk menghitung stok ERP dan menampilkan pilihan pengganti.</div>
  <div class="table-responsive mt-2"><table class="table table-sm table-hover align-middle sq-small"><thead class="sq-th"><tr><th>Kode / Barang</th><th>Jenis</th><th>ERP / Gudang</th><th class="text-end">Stok</th><th class="text-end">Harga RETAIL</th><th class="text-end">Tindakan</th></tr></thead><tbody id="availabilityResults"></tbody></table></div>
  <div class="sq-note">Barang alternatif yang berasal dari master Similar Items perlu diverifikasi kompatibilitasnya. Item tanpa harga RETAIL tidak dapat ditambahkan ke penawaran.</div>
</section>
<section class="sq-panel"><h2 class="h6 fw-bold"><span class="sq-stage">4</span> Detail Penawaran</h2>
  <div class="table-responsive"><table class="table table-bordered align-middle sq-small"><thead class="sq-th"><tr><th>Kode Awal</th><th>Sparepart Terpilih</th><th>ERP / Gudang</th><th style="min-width:90px">Qty</th><th class="text-end">Harga</th><th class="text-end">Jumlah</th><th></th></tr></thead><tbody id="quotationLines"><tr><td colspan="7" class="text-muted text-center py-3">Belum ada sparepart dipilih.</td></tr></tbody></table></div>
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div class="sq-note">Harga diambil ulang saat disimpan. Jika stok/harga berubah, penyimpanan akan ditolak agar pengguna dapat memeriksa ulang.</div><div class="text-end"><div class="text-muted small">Total Penawaran Sparepart</div><div id="grandTotal" class="h4 fw-bold mb-0">Rp 0</div></div></div>
  <div id="hiddenLines"></div><div class="alert alert-info mt-3 mb-0 small">Penerimaan pertama boleh disimpan tanpa sparepart. Setelah pemeriksaan teknisi, buka <strong>Edit Draft</strong> untuk melengkapi sparepart sebelum dikirim ke ERP.</div>
  <hr><button id="saveQuote" class="btn btn-report btn-lg" type="submit" @disabled($typeError !== null)><i class="bi bi-check-circle me-1"></i> {{ isset($editHeader) ? 'Simpan Perubahan Draft' : 'Simpan Draft Penawaran' }}</button>
  <p class="sq-note mt-2 mb-0">{{ isset($editHeader) ? 'Perubahan ini hanya pada draft DB_AppHub. Nomor ERP belum dibuat sampai Kirim ke ERP.' : 'Setelah disimpan, penawaran bisa dipreview dan dicetak. Pengiriman ERP dilakukan terpisah dari halaman detail.' }}</p>
</section>
</form>
@push('scripts')
<script>
(() => {
  const endpoint = @json(url('/service-quotations/lookup'));
  const editSeed = @json($editSeed ?? null);
  const rupiah = n => 'Rp ' + Math.round(Number(n)||0).toLocaleString('id-ID');
  const escapeHtml = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const id = n => document.getElementById(n);
  const source = id('erpSource');
  const search = id('damagedSearch');
  const docType = id('docType');
  const saveButton = id('saveQuote');
  let selectedDamaged = null, activePart = '', results = [], lines = [];
  let findTimer, docTypeRequest = 0;

  function serviceTypeWarning(message) {
    const warning = id('docTypeWarning');
    warning.hidden = !message;
    id('docTypeWarningText').textContent = message || '';
  }
  function updateServiceTypeSubmit() {
    saveButton.disabled = docType.disabled || docType.options.length <= 1;
  }
  async function loadServiceTypes() {
    const requestId = ++docTypeRequest;
    const selectedSource = source.value;
    docType.replaceChildren(new Option('Memuat Service Type AS_' + selectedSource + '...', ''));
    docType.disabled = true;
    id('docTypeHelp').textContent = 'Membaca USP_HRM_DocTypes_QueryAll dari AS_' + selectedSource + '...';
    serviceTypeWarning('');
    updateServiceTypeSubmit();
    try {
      const data = await getData('doc-types', {source: selectedSource});
      if (requestId !== docTypeRequest || selectedSource !== source.value) return;
      if (!Array.isArray(data.items)) throw new Error('Format respons Service Type tidak valid.');
      docType.replaceChildren(new Option('Pilih Service Type', ''));
      data.items.forEach(type => docType.add(new Option(type.label + ' (' + type.code + ')', type.id)));
      docType.disabled = data.items.length === 0;
      if (!data.items.length) serviceTypeWarning(data.message || 'Service Type belum tersedia di AS_' + selectedSource + '.');
      id('docTypeHelp').textContent = data.items.length + ' Service Type dari AS_' + selectedSource + '.';
    } catch (e) {
      if (requestId !== docTypeRequest || selectedSource !== source.value) return;
      docType.replaceChildren(new Option('Service Type tidak tersedia', ''));
      docType.disabled = true;
      serviceTypeWarning(e.message || 'Gagal membaca Service Type dari AS_' + selectedSource + '.');
      id('docTypeHelp').textContent = 'Gagal membaca database AS_' + selectedSource + '.';
    } finally {
      if (requestId === docTypeRequest && selectedSource === source.value) updateServiceTypeSubmit();
    }
  }
  async function getData(path, params) {
    const r = await fetch(endpoint + '/' + path + '?' + new URLSearchParams(params), {headers:{'Accept':'application/json'}});
    const data = await r.json().catch(()=>({}));
    if (!r.ok) throw new Error(data.message || 'Gagal menghubungi server');
    return data;
  }
  function errorTo(el, e) { id(el).textContent = e.message || 'Terjadi kesalahan koneksi ERP.'; }
  async function searchItems() {
    const q = search.value.trim();
    id('itemResults').replaceChildren();
    if (q.length < 2) return;
    id('itemResults').textContent = 'Mencari item...';
    try {
      const data = await getData('items', {source:source.value, q});
      if (search.value.trim() !== q) return;
      id('itemResults').replaceChildren();
      if (!data.items.length) id('itemResults').textContent = 'Item tidak ditemukan.';
      data.items.forEach(item => {
        const b = document.createElement('button'); b.type='button'; b.className='sq-result';
        b.innerHTML = '<strong>'+escapeHtml(item.code)+'</strong> <span class="text-muted">'+escapeHtml(item.name)+'</span>';
        b.addEventListener('click', () => chooseDamaged(item)); id('itemResults').append(b);
      });
    } catch(e) { errorTo('itemResults',e); }
  }
  search.addEventListener('input', () => {clearTimeout(findTimer);findTimer=setTimeout(searchItems,300);});
  source.addEventListener('change', () => { loadServiceTypes(); selectedDamaged=null; id('damagedCode').value=''; search.value=''; id('damagedChosen').textContent='Belum ada item terpilih'; id('itemResults').replaceChildren();id('partResults').replaceChildren();id('partsStatus').textContent='Pilih barang rusak terlebih dahulu.'; id('availabilityResults').replaceChildren();id('availabilityStatus').textContent='Pilih sparepart terlebih dahulu.';lines=[];renderLines();});
  async function chooseDamaged(item, resetLines = true) {
    selectedDamaged=item; id('damagedCode').value=item.code; search.value=item.code;
    id('damagedChosen').textContent='Dipilih: '+item.code+' — '+item.name;
    id('itemResults').replaceChildren(); if (resetLines) { lines=[];renderLines(); }
    id('partResults').replaceChildren();id('availabilityResults').replaceChildren();
    id('partsStatus').textContent='Memuat daftar sparepart '+item.code+'...';
    try {
      const data=await getData('parts',{source:source.value,code:item.code});
      if (selectedDamaged?.code!==item.code) return;
      id('partsStatus').textContent = data.parts.length+' kandidat sparepart ditemukan. Klik kode untuk cek stok dan harga.';
      if (!data.parts.length) id('partsStatus').textContent='Tidak ada kode sparepart yang berawalan '+item.code+'-SP-. Cek master item ERP.';
      data.parts.forEach(part => {
        const b=document.createElement('button');b.type='button';b.className='sq-result';
        b.innerHTML='<strong>'+escapeHtml(part.code)+'</strong> <span class="text-muted">'+escapeHtml(part.name)+'</span>';
        b.addEventListener('click',()=>selectPart(part));id('partResults').append(b);
      });
    } catch(e){errorTo('partsStatus',e);}
  }
  async function selectPart(part) {
    activePart=part.code;id('availabilityResults').replaceChildren();
    id('availabilityStatus').textContent='Memeriksa stok & harga untuk '+part.code+' di INGCO dan SMI...';
    try {
      const data=await getData('availability',{code:part.code});
      if (activePart!==part.code) return;
      results=data.items;
      id('availabilityStatus').textContent = results.length ? 'Komponen: '+part.code+' — '+part.name : 'Tidak ada sparepart asli / similar yang memiliki stok positif.';
      results.forEach(candidate => {
        const tr=document.createElement('tr');
        tr.innerHTML='<td><strong>'+escapeHtml(candidate.item_code)+'</strong><div class="text-muted">'+escapeHtml(candidate.item_name)+'</div></td>'+
          '<td>'+(candidate.depth===0?'<span class="sq-pill">Asli</span>':'<span class="sq-pill">Similar '+candidate.depth+'</span>')+'</td>'+
          '<td>'+escapeHtml(candidate.source)+'<div class="text-muted">'+escapeHtml(candidate.warehouse_name)+'</div></td>'+
          '<td class="text-end">'+Number(candidate.qty_available).toLocaleString('id-ID')+'</td>'+
          '<td class="text-end">'+(candidate.retail_price ? rupiah(candidate.retail_price) : '<span class="text-danger">Tanpa harga RETAIL</span>')+'</td>'+
          '<td class="text-end"></td>';
        const b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline-success'; b.textContent='Tambahkan';b.disabled=!candidate.selectable;
        b.addEventListener('click',()=>addLine(candidate));tr.lastElementChild.append(b);id('availabilityResults').append(tr);
      });
    }catch(e){errorTo('availabilityStatus',e);}
  }
  function addLine(candidate) {
    if (lines.length>=30) return alert('Maksimal 30 baris penawaran.');
    // Qty penawaran selalu bilangan bulat, awal 1, tidak otomatis mengikuti stok.
    // Pengecekan stok dilakukan saat simpan dan dipantau sebagai peringatan di form.
    lines.push({...candidate, qty:1});renderLines();
    id('quotationLines').scrollIntoView({behavior:'smooth',block:'center'});
  }
  function renderLines() {
    const body=id('quotationLines');const hidden=id('hiddenLines');body.replaceChildren();hidden.replaceChildren();
    if(!lines.length)body.innerHTML='<tr><td colspan="7" class="text-muted text-center py-3">Belum ada sparepart dipilih.</td></tr>';
    let total=0;
    lines.forEach((line,i)=>{
      total+=Number(line.qty)*Number(line.retail_price);
      const tr=document.createElement('tr');
      tr.innerHTML='<td>'+escapeHtml(line.root_code)+'</td><td><strong>'+escapeHtml(line.item_code)+'</strong><div class="text-muted">'+escapeHtml(line.item_name)+'</div></td>'+
        '<td>'+escapeHtml(line.source)+'<div class="text-muted">'+escapeHtml(line.warehouse_name)+'</div></td><td></td>'+
        '<td class="text-end">'+rupiah(line.retail_price)+'</td><td class="text-end">'+rupiah(Number(line.qty)*Number(line.retail_price))+'</td><td></td>';
      // Tidak ada atribut min/max stok pada input: user bebas mengetik Qty bulat.
      // Validasi stok tetap wajib saat Simpan, termasuk jika barang sama dipilih berulang.
      const input=document.createElement('input');input.className='form-control form-control-sm';input.type='number';input.step='1';input.required=true;input.value=String(line.qty);
      input.name='lines['+i+'][qty]';
      input.setAttribute('aria-label','Qty '+line.item_code+' di '+line.warehouse_name);
      input.addEventListener('input',()=>{line.qty=input.value==='' ? NaN : Number(input.value);renderTotal();refreshQtyWarnings();});
      tr.children[3].append(input);
      const stockInfo=document.createElement('div');stockInfo.className='sq-note mt-1';stockInfo.textContent='Stok gudang: '+Number(line.qty_available).toLocaleString('id-ID');tr.children[3].append(stockInfo);
      const qtyWarning=document.createElement('div');qtyWarning.className='text-danger small mt-1 sq-qty-warning';qtyWarning.setAttribute('aria-live','polite');tr.children[3].append(qtyWarning);
      const remove=document.createElement('button');remove.type='button';remove.className='btn btn-sm btn-outline-danger';remove.textContent='Hapus';remove.addEventListener('click',()=>{lines.splice(i,1);renderLines();});tr.lastElementChild.append(remove);
      body.append(tr);
      [['requested_code',line.root_code],['item_code',line.item_code],['source',line.source],['warehouse_id',line.warehouse_id]].forEach(([key,val])=>{
        const input=document.createElement('input');input.type='hidden';input.name='lines['+i+']['+key+']';input.value=String(val);hidden.append(input);
      });
      // qty resides in visible input and is submitted with the form.
    });
    id('grandTotal').textContent=rupiah(total);
    refreshQtyWarnings();
  }
  function qtyError(line) {
    if (!Number.isSafeInteger(line.qty) || line.qty <= 0) return 'Qty harus bilangan bulat positif.';
    // Jumlahkan seluruh baris untuk barang + database + gudang yang sama.
    // Peringatan ini tidak membatasi input; stok akan diverifikasi ulang oleh server.
    const combined = lines.reduce((sum, other) =>
      other.item_code === line.item_code && other.source === line.source &&
      String(other.warehouse_id) === String(line.warehouse_id)
        ? sum + Number(other.qty || 0) : sum, 0);
    if (combined > Number(line.qty_available) + 0.000001)
      return 'Qty gabungan '+combined.toLocaleString('id-ID')+' melebihi stok gudang '+Number(line.qty_available).toLocaleString('id-ID')+'.';
    return '';
  }
  function refreshQtyWarnings() {
    Array.from(id('quotationLines').children).forEach((tr,i) => {
      if (!lines[i]) return;
      const problem=qtyError(lines[i]);
      tr.querySelector('input[type="number"]')?.classList.toggle('is-invalid', Boolean(problem));
      const warning=tr.querySelector('.sq-qty-warning');if(warning) warning.textContent=problem;
    });
  }
  function renderTotal(){let total=0;id('quotationLines').querySelectorAll('tr').forEach((tr,i)=>{if(!lines[i])return;const qty=Number(lines[i].qty);const x=Number.isFinite(qty) && qty>0 ? qty*Number(lines[i].retail_price) : 0;tr.children[5].textContent=rupiah(x);total+=x;});id('grandTotal').textContent=rupiah(total);}
  if (editSeed) {
    // Keep saved lines visible and editable. A change of damaged item intentionally clears lines.
    lines = editSeed.lines.map(line => ({...line, qty: Number(line.qty)}));
    renderLines();
    if (editSeed.damaged?.code) chooseDamaged(editSeed.damaged, false);
  }
  updateServiceTypeSubmit();
  id('quotationForm').addEventListener('submit',e=>{
    if (!docType.value || docType.disabled) { e.preventDefault(); alert('Pilih Service Type yang tersedia dari AS_' + source.value + '.'); return; }
    if(!selectedDamaged && !id('damagedCode').value){e.preventDefault();alert('Pilih barang yang rusak dari hasil pencarian terlebih dahulu.');return;}
    for(const line of lines){
      const problem=qtyError(line);
      if (problem) {e.preventDefault();refreshQtyWarnings();alert(problem+' Penawaran tidak dapat disimpan.');return;}
    }
    id('saveQuote').disabled=true;id('saveQuote').textContent='Menyimpan dan verifikasi ERP...';
  });
})();
</script>
@endpush
@endsection
