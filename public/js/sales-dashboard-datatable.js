(() => {
    'use strict';

    const container = document.querySelector('[data-sales-raw-table]');
    const config = window.salesDashboardTableConfig;
    if (!container || !config?.endpoint) return;

    const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const percent = new Intl.NumberFormat('id-ID', { style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const search = container.querySelector('[data-raw-search]');
    const length = container.querySelector('[data-raw-length]');
    const order = container.querySelector('[data-raw-order]');
    const direction = container.querySelector('[data-raw-dir]');
    const info = container.querySelector('[data-raw-info]');
    const body = container.querySelector('[data-raw-body]');
    const loading = container.querySelector('[data-raw-loading]');
    const wrap = container.querySelector('[data-raw-wrap]');
    const error = container.querySelector('[data-raw-error]');
    const previous = container.querySelector('[data-raw-prev]');
    const next = container.querySelector('[data-raw-next]');
    let start = 0;
    let draw = 0;
    let totalFiltered = 0;
    let timer = null;

    function appendCell(row, value, formatter = null) {
        const cell = document.createElement('td');
        cell.textContent = formatter ? formatter(value) : String(value ?? '');
        row.appendChild(cell);
    }

    function renderRows(rows) {
        body.textContent = '';
        rows.forEach((item) => {
            const row = document.createElement('tr');
            appendCell(row, item.database);
            appendCell(row, item.invoice_date);
            appendCell(row, item.invoice_number);
            appendCell(row, item.line);
            appendCell(row, item.line_contribution, (value) => value === null ? '—' : percent.format(Number(value)));
            appendCell(row, item.customer_code);
            appendCell(row, item.customer_name);
            appendCell(row, item.sales_person_name);
            appendCell(row, item.item_code);
            appendCell(row, item.item_name);
            appendCell(row, item.grade || '-');
            appendCell(row, item.discontinued ? 'Ya' : 'Tidak');
            appendCell(row, item.status_item || (item.discontinued ? 'DISCONTINUE' : 'ACTIVE'));
            appendCell(row, item.clearance ? 'Ya' : 'Tidak');
            appendCell(row, item.clearance ? 'ITEM REJECT' : 'BUKAN ITEM REJECT');
            appendCell(row, item.clearance_discount, (value) => value === '' || value === null ? '-' : (Number.isFinite(Number(value)) ? decimal.format(Number(value)) : String(value)));
            appendCell(row, item.quantity, (value) => decimal.format(Number(value || 0)));
            appendCell(row, item.discount, (value) => decimal.format(Number(value || 0)));
            appendCell(row, item.discount2, (value) => decimal.format(Number(value || 0)));
            appendCell(row, item.price_level_name);
            appendCell(row, item.line_total, (value) => `Rp ${money.format(Number(value || 0))}`);
            appendCell(row, item.remarks);
            body.appendChild(row);
        });

        if (rows.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 22;
            cell.className = 'text-center text-secondary py-4';
            cell.textContent = 'Tidak ada data mentah yang sesuai filter.';
            row.appendChild(cell);
            body.appendChild(row);
        }
    }

    async function load() {
        draw += 1;
        const currentDraw = draw;
        const pageLength = Number(length.value || 25);
        const params = new URLSearchParams(config.query || {});
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
            if (!response.ok) throw new Error(payload.message || 'Data mentah gagal dimuat.');
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
            error.textContent = exception instanceof Error ? exception.message : String(exception);
            error.classList.remove('d-none');
        } finally {
            loading.classList.add('d-none');
        }
    }

    function resetAndLoad() { start = 0; load(); }
    search.addEventListener('input', () => { window.clearTimeout(timer); timer = window.setTimeout(resetAndLoad, 300); });
    length.addEventListener('change', resetAndLoad);
    order.addEventListener('change', resetAndLoad);
    direction.addEventListener('change', resetAndLoad);
    previous.addEventListener('click', () => { start = Math.max(0, start - Number(length.value || 25)); load(); });
    next.addEventListener('click', () => { if (start + Number(length.value || 25) < totalFiltered) { start += Number(length.value || 25); load(); } });

    load();
})();
