@php
    $chosen = array_filter(explode(',', strtolower((string) ($filter->itemStatus ?? 'all'))));
    $isAll = in_array('all', $chosen, true) || $chosen === [];
    $choices = [
        'normal' => 'Normal',
        'discontinued' => 'Discontinue',
        'discontinued_sale' => 'Discontinue + Sale',
        'clearance' => 'Item Reject',
    ];
    if (isset($itemStatusRejectValue) && $itemStatusRejectValue === 'reject') {
        unset($choices['clearance']);
        $choices['reject'] = 'Item Reject';
    }
    $selectedLabels = $isAll ? ['Semua Status'] : array_values(array_intersect_key($choices, array_flip($chosen)));
@endphp
<details class="position-relative status-multi" data-status-multi>
    <summary class="form-select text-truncate" style="cursor:pointer;list-style:none" aria-label="Pilih beberapa status item">{{ $isAll ? 'Semua Status' : (count($chosen).' status dipilih') }}</summary>
    <div class="bg-white border rounded shadow-sm p-3 position-absolute start-0 end-0" style="z-index:1050;min-width:240px;max-height:310px;overflow-y:auto">
        <div class="small text-muted mb-2">Boleh pilih lebih dari satu status.</div>
        <label class="d-flex align-items-center gap-2 mb-2 fw-bold"><input class="form-check-input m-0" type="checkbox" name="item_status[]" value="all" data-all @checked($isAll)> Semua Status</label>
        <hr class="my-2">
        @foreach($choices as $value => $label)
            <label class="d-flex align-items-center gap-2 mb-2"><input class="form-check-input m-0" type="checkbox" name="item_status[]" value="{{ $value }}" data-individual @checked(!$isAll && in_array($value, $chosen, true))> {{ $label }}</label>
        @endforeach
        <button class="btn btn-sm btn-outline-secondary mt-1" type="button" onclick="this.closest('details').open=false">Selesai</button>
    </div>
</details>
@once
@push('scripts')
<script>
document.querySelectorAll('[data-status-multi]').forEach(function(box) {
    const all = box.querySelector('[data-all]');
    const others = Array.from(box.querySelectorAll('[data-individual]'));
    const title = box.querySelector('summary');
    function update() {
        const selected = others.filter(x => x.checked);
        if (!selected.length) all.checked = true;
        if (all.checked) others.forEach(x => { x.checked = false; });
        title.textContent = all.checked ? 'Semua Status' : selected.length + ' status dipilih';
    }
    all.addEventListener('change', function(){
        if (all.checked) others.forEach(x => { x.checked = false; });
        else if (!others.some(x => x.checked)) all.checked = true;
        update();
    });
    others.forEach(x => x.addEventListener('change', function(){
        all.checked = !others.some(y => y.checked);
        update();
    }));
});
</script>
@endpush
@endonce
