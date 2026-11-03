@props([
    'name' => 'warehouse',
    'id' => 'warehousePicker',
    'options' => [],
    'selected' => [],
    'placeholder' => 'Semua warehouse',
])

@php
    $fieldName = str_ends_with((string) $name, '[]') ? (string) $name : (string) $name.'[]';
    $selectedValues = array_values(array_map('strval', is_array($selected) ? $selected : []));
@endphp

<div class="warehouse-combo" data-warehouse-picker id="{{ $id }}">
    <select class="d-none" name="{{ $fieldName }}" multiple data-warehouse-native tabindex="-1" aria-hidden="true">
        @foreach($options as $value => $label)
            <option value="{{ $value }}" @selected(in_array((string) $value, $selectedValues, true))>{{ $label }}</option>
        @endforeach
    </select>

    <button type="button" class="warehouse-combo-toggle" data-warehouse-toggle aria-haspopup="listbox" aria-expanded="false">
        <span class="warehouse-combo-summary" data-warehouse-summary>{{ $placeholder }}</span>
        <span class="warehouse-combo-caret" aria-hidden="true">⌄</span>
    </button>

    <div class="warehouse-combo-menu" data-warehouse-menu hidden>
        <div class="warehouse-combo-search-wrap">
            <input type="search" class="form-control form-control-sm" placeholder="Cari warehouse..." autocomplete="off" data-warehouse-search>
        </div>
        <div class="warehouse-combo-actions">
            <button type="button" class="btn btn-sm btn-outline-primary" data-warehouse-select-all>Pilih Semua</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-warehouse-clear>Bersihkan</button>
        </div>
        <div class="warehouse-combo-list" role="listbox" aria-multiselectable="true" data-warehouse-list>
            @forelse($options as $value => $label)
                <label class="warehouse-combo-option" data-warehouse-option data-search="{{ strtolower((string) $label.' '.(string) $value) }}">
                    <input class="form-check-input" type="checkbox" value="{{ $value }}" data-warehouse-checkbox @checked(in_array((string) $value, $selectedValues, true))>
                    <span class="warehouse-combo-option-text">{{ $label }}</span>
                </label>
            @empty
                <div class="warehouse-combo-empty text-secondary">Tidak ada warehouse untuk filter saat ini.</div>
            @endforelse
        </div>
    </div>

    <div class="warehouse-combo-chips" data-warehouse-chips></div>
    <div class="form-text">Bisa pilih lebih dari satu. Kosong = semua warehouse.</div>
</div>

@once
    <style>
        .warehouse-combo{position:relative}.warehouse-combo-toggle{width:100%;min-height:38px;padding:.375rem 2rem .375rem .75rem;background:#fff;border:1px solid #ced4da;border-radius:.375rem;color:#212529;text-align:left;position:relative;display:flex;align-items:center}.warehouse-combo-toggle:focus{border-color:#86b7fe;box-shadow:0 0 0 .25rem rgba(13,110,253,.25);outline:0}.warehouse-combo-summary{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.warehouse-combo-caret{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);color:#667085}.warehouse-combo-menu{position:absolute;z-index:1080;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1px solid #d0d5dd;border-radius:10px;box-shadow:0 14px 35px rgba(16,24,40,.16);padding:.65rem}.warehouse-combo-search-wrap{margin-bottom:.5rem}.warehouse-combo-actions{display:flex;gap:.4rem;margin-bottom:.5rem}.warehouse-combo-list{max-height:260px;overflow:auto;border-top:1px solid #eaecf0;padding-top:.35rem}.warehouse-combo-option{display:flex;align-items:flex-start;gap:.55rem;padding:.5rem .45rem;border-radius:7px;cursor:pointer;line-height:1.25}.warehouse-combo-option:hover{background:#f2f4f7}.warehouse-combo-option .form-check-input{margin-top:.1rem;flex:0 0 auto}.warehouse-combo-option-text{font-size:.86rem;font-weight:600;color:#344054}.warehouse-combo-empty{font-size:.82rem;padding:.6rem .4rem}.warehouse-combo-chips{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.45rem}.warehouse-combo-chip{display:inline-flex;align-items:center;gap:.3rem;max-width:100%;padding:.2rem .45rem;border-radius:999px;background:#eef4ff;border:1px solid #c7d7fe;color:#1849a9;font-size:.72rem;font-weight:700}.warehouse-combo-chip-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:210px}.warehouse-combo-chip-remove{border:0;background:transparent;color:#1849a9;padding:0;line-height:1;font-weight:900;font-size:.9rem}.warehouse-combo-option[hidden]{display:none!important}
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-warehouse-picker]').forEach(function (picker) {
            if (picker.dataset.ready === '1') return;
            picker.dataset.ready = '1';

            const nativeSelect = picker.querySelector('[data-warehouse-native]');
            const toggle = picker.querySelector('[data-warehouse-toggle]');
            const menu = picker.querySelector('[data-warehouse-menu]');
            const summary = picker.querySelector('[data-warehouse-summary]');
            const search = picker.querySelector('[data-warehouse-search]');
            const chips = picker.querySelector('[data-warehouse-chips]');
            const selectAll = picker.querySelector('[data-warehouse-select-all]');
            const clear = picker.querySelector('[data-warehouse-clear]');
            const checkboxes = Array.from(picker.querySelectorAll('[data-warehouse-checkbox]'));
            const optionRows = Array.from(picker.querySelectorAll('[data-warehouse-option]'));

            const labelFor = function (value) {
                const option = Array.from(nativeSelect.options).find(function (item) { return item.value === value; });
                return option ? option.textContent.trim() : value;
            };

            const syncNative = function () {
                const selected = new Set(checkboxes.filter(function (box) { return box.checked; }).map(function (box) { return box.value; }));
                Array.from(nativeSelect.options).forEach(function (option) { option.selected = selected.has(option.value); });
            };

            const render = function () {
                const selectedBoxes = checkboxes.filter(function (box) { return box.checked; });
                if (selectedBoxes.length === 0) {
                    summary.textContent = @json($placeholder);
                } else if (selectedBoxes.length === 1) {
                    summary.textContent = labelFor(selectedBoxes[0].value);
                } else {
                    summary.textContent = selectedBoxes.length + ' warehouse dipilih';
                }

                chips.replaceChildren();
                selectedBoxes.forEach(function (box) {
                    const chip = document.createElement('span');
                    chip.className = 'warehouse-combo-chip';

                    const text = document.createElement('span');
                    text.className = 'warehouse-combo-chip-text';
                    text.textContent = labelFor(box.value);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'warehouse-combo-chip-remove';
                    remove.setAttribute('aria-label', 'Hapus ' + labelFor(box.value));
                    remove.textContent = '×';
                    remove.addEventListener('click', function () {
                        box.checked = false;
                        syncNative();
                        render();
                    });

                    chip.append(text, remove);
                    chips.appendChild(chip);
                });
            };

            const openMenu = function () {
                menu.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
                if (search) setTimeout(function () { search.focus(); }, 0);
            };
            const closeMenu = function () {
                menu.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle.addEventListener('click', function () {
                if (menu.hidden) openMenu(); else closeMenu();
            });

            checkboxes.forEach(function (box) {
                box.addEventListener('change', function () {
                    syncNative();
                    render();
                });
            });

            if (search) {
                search.addEventListener('input', function () {
                    const keyword = search.value.trim().toLowerCase();
                    optionRows.forEach(function (row) {
                        row.hidden = keyword !== '' && !row.dataset.search.includes(keyword);
                    });
                });
            }

            if (selectAll) {
                selectAll.addEventListener('click', function () {
                    checkboxes.forEach(function (box) { box.checked = true; });
                    syncNative();
                    render();
                });
            }

            if (clear) {
                clear.addEventListener('click', function () {
                    checkboxes.forEach(function (box) { box.checked = false; });
                    syncNative();
                    render();
                });
            }

            document.addEventListener('click', function (event) {
                if (!picker.contains(event.target)) closeMenu();
            });
            picker.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeMenu();
                    toggle.focus();
                }
            });

            syncNative();
            render();
        });
    });
    </script>
@endonce
