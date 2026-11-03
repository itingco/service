(() => {
    'use strict';

    const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

    function normalized(value) {
        return String(value ?? '').trim().replace(/\s+/g, ' ').toLocaleUpperCase('id-ID');
    }

    function initializeRecap(block) {
        const table = block.querySelector('[data-kpi-recap-table]');
        if (!table) return;

        const body = table.tBodies[0];
        const rows = Array.from(body.querySelectorAll('tr[data-row]'));
        const search = block.querySelector('[data-kpi-table-search]');
        const length = block.querySelector('[data-kpi-table-length]');
        const info = block.querySelector('[data-kpi-table-info]');
        const previous = block.querySelector('[data-kpi-table-prev]');
        const next = block.querySelector('[data-kpi-table-next]');
        const headers = Array.from(table.querySelectorAll('thead th[data-sort-key]'));
        let page = 1;
        let sortIndex = -1;
        let sortDirection = 'asc';
        let sortType = 'text';

        function matchingRows() {
            const needle = normalized(search?.value || '');
            return rows.filter((row) => needle === '' || normalized(row.textContent).includes(needle));
        }

        function sortRows(items) {
            if (sortIndex < 0) return items;
            return [...items].sort((left, right) => {
                const leftCell = left.cells[sortIndex];
                const rightCell = right.cells[sortIndex];
                let comparison;
                if (sortType === 'numeric') {
                    const leftValue = Number(leftCell?.dataset.sortValue ?? Number.NEGATIVE_INFINITY);
                    const rightValue = Number(rightCell?.dataset.sortValue ?? Number.NEGATIVE_INFINITY);
                    comparison = leftValue - rightValue;
                } else {
                    comparison = String(leftCell?.textContent ?? '').localeCompare(
                        String(rightCell?.textContent ?? ''),
                        'id',
                        { numeric: true, sensitivity: 'base' },
                    );
                }
                return sortDirection === 'desc' ? -comparison : comparison;
            });
        }

        function render() {
            const pageLength = Math.max(1, Number(length?.value || 10));
            const filtered = sortRows(matchingRows());
            const totalPages = Math.max(1, Math.ceil(filtered.length / pageLength));
            page = Math.min(page, totalPages);
            const start = (page - 1) * pageLength;
            const visible = new Set(filtered.slice(start, start + pageLength));

            rows.forEach((row) => { row.hidden = !visible.has(row); });
            filtered.forEach((row) => body.appendChild(row));

            const first = filtered.length === 0 ? 0 : start + 1;
            const last = Math.min(start + pageLength, filtered.length);
            if (info) info.textContent = `Menampilkan ${first}–${last} dari ${filtered.length} baris`;
            if (previous) previous.disabled = page <= 1;
            if (next) next.disabled = page >= totalPages || filtered.length === 0;
        }

        search?.addEventListener('input', () => { page = 1; render(); });
        length?.addEventListener('change', () => { page = 1; render(); });
        previous?.addEventListener('click', () => { if (page > 1) { page -= 1; render(); } });
        next?.addEventListener('click', () => { page += 1; render(); });
        headers.forEach((header) => {
            header.addEventListener('click', () => {
                const index = Array.from(header.parentElement.children).indexOf(header);
                if (sortIndex === index) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortIndex = index;
                    sortDirection = 'asc';
                    sortType = header.dataset.sortType || 'text';
                }
                page = 1;
                render();
            });
        });

        render();
    }

    function appendCell(row, value, formatter = null) {
        const cell = document.createElement('td');
        cell.textContent = formatter ? formatter(value) : String(value ?? '');
        row.appendChild(cell);
    }

    function initializeDetail(container, config) {
        const search = container.querySelector('[data-detail-search]');
        const length = container.querySelector('[data-detail-length]');
        const order = container.querySelector('[data-detail-order]');
        const direction = container.querySelector('[data-detail-dir]');
        const info = container.querySelector('[data-detail-info]');
        const body = container.querySelector('[data-detail-body]');
        const loading = container.querySelector('[data-detail-loading]');
        const wrap = container.querySelector('[data-detail-table-wrap]');
        const error = container.querySelector('[data-detail-error]');
        const previous = container.querySelector('[data-detail-prev]');
        const next = container.querySelector('[data-detail-next]');
        const exportLink = container.querySelector('[data-detail-export]');
        const detailType = container.dataset.detailType || 'all';
        let start = 0;
        let draw = 0;
        let totalFiltered = 0;
        let timer = null;

        function setError(message) {
            error.textContent = message || 'Detail transaksi gagal dimuat.';
            error.classList.remove('d-none');
            wrap.classList.add('d-none');
        }

        function updateExportLink() {
            if (!exportLink || !config.exportEndpoint) return;
            const params = new URLSearchParams(config.query || {});
            params.set('detail_type', detailType);
            params.set('detail_search', search.value.trim());
            exportLink.href = `${config.exportEndpoint}?${params.toString()}`;
        }

        function renderRows(rows) {
            body.textContent = '';
            rows.forEach((item) => {
                const row = document.createElement('tr');
                if (config.combined) appendCell(row, item.database);
                appendCell(row, item.invoice_date);
                appendCell(row, item.invoice_number);
                appendCell(row, item.line);
                appendCell(row, item.item_code);
                appendCell(row, item.item_name);
                appendCell(row, item.grade || '-');
                appendCell(row, item.discontinued ? 'Ya' : 'Tidak');
                appendCell(row, item.clearance ? 'ITEM REJECT' : 'BUKAN ITEM REJECT');
                appendCell(row, item.quantity, (value) => decimal.format(Number(value || 0)));
                appendCell(row, item.line_total, (value) => `Rp ${money.format(Number(value || 0))}`);
                body.appendChild(row);
            });

            if (rows.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = config.combined ? 11 : 10;
                cell.className = 'text-center text-secondary py-4';
                cell.textContent = 'Tidak ada detail yang sesuai filter.';
                row.appendChild(cell);
                body.appendChild(row);
            }
        }

        async function load() {
            draw += 1;
            const currentDraw = draw;
            const pageLength = Number(length.value || 10);
            const params = new URLSearchParams(config.query || {});
            params.set('detail_type', detailType);
            params.set('draw', String(currentDraw));
            params.set('start', String(start));
            params.set('length', String(pageLength));
            params.set('order_column', order.value);
            params.set('order_dir', direction.value);
            params.set('search', search.value.trim());

            loading.classList.remove('d-none');
            wrap.classList.add('d-none');
            error.classList.add('d-none');

            try {
                const response = await fetch(`${config.endpoint}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Detail transaksi gagal dimuat.');
                if (payload.draw !== currentDraw) return;

                totalFiltered = Number(payload.recordsFiltered || 0);
                renderRows(Array.isArray(payload.data) ? payload.data : []);
                const first = totalFiltered === 0 ? 0 : start + 1;
                const last = Math.min(start + pageLength, totalFiltered);
                info.textContent = `Menampilkan ${first}–${last} dari ${totalFiltered} baris`;
                previous.disabled = start <= 0;
                next.disabled = start + pageLength >= totalFiltered;
                wrap.classList.remove('d-none');
            } catch (exception) {
                setError(exception instanceof Error ? exception.message : String(exception));
            } finally {
                loading.classList.add('d-none');
            }
        }

        function resetAndLoad() {
            start = 0;
            load();
        }

        search.addEventListener('input', () => {
            updateExportLink();
            window.clearTimeout(timer);
            timer = window.setTimeout(resetAndLoad, 300);
        });
        length.addEventListener('change', resetAndLoad);
        order.addEventListener('change', resetAndLoad);
        direction.addEventListener('change', resetAndLoad);
        previous.addEventListener('click', () => {
            start = Math.max(0, start - Number(length.value || 10));
            load();
        });
        next.addEventListener('click', () => {
            if (start + Number(length.value || 10) < totalFiltered) {
                start += Number(length.value || 10);
                load();
            }
        });

        updateExportLink();
        load();
    }

    document.querySelectorAll('[data-kpi-table-block]').forEach(initializeRecap);

    const config = window.salesKpiTableConfig;
    if (config?.endpoint) {
        document.querySelectorAll('[data-kpi-detail-table]').forEach((container) => initializeDetail(container, config));
    }
})();
