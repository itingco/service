@php $selectedKeys = is_array($selectedKeys ?? null) ? $selectedKeys : []; @endphp
<div class="wc-pick" data-wc-picker>
    <div class="wc-pick-top">
        <input class="form-control form-control-sm flex-grow-1" style="min-width:170px;max-width:420px" type="search" data-wc-search placeholder="Cari kode, nama gudang, atau INGCO/SMI..." aria-label="Cari gudang">
        <div class="wc-checkbox-tools"><button class="btn btn-sm btn-outline-secondary" type="button" data-wc-all>Pilih hasil pencarian</button><button class="btn btn-sm btn-outline-secondary" type="button" data-wc-none>Hapus pilihan terlihat</button></div>
    </div>
    <div class="wc-pick-list">
        @foreach($warehouseOptions as $w)
            <label class="wc-pick-row">
                <input type="checkbox" name="warehouses[]" value="{{ $w['key'] }}" @checked(in_array($w['key'], $selectedKeys, true))>
                <span class="wc-source">{{ $w['source'] }}</span>
                <span class="wc-warehouse-name">{{ $w['name'] }}</span>
            </label>
        @endforeach
    </div>
    <div class="wc-pick-top justify-content-between"><strong class="small" data-wc-count>0 gudang dipilih</strong><span class="wc-hidden-count" data-wc-visible></span></div>
</div>
