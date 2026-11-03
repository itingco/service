<div class="d-flex flex-wrap gap-2 align-items-end mb-2">
    <div><label class="form-label">Cari detail</label><input type="search" class="form-control form-control-sm" data-detail-search value="{{ $filter->search }}" placeholder="Database, invoice, lini, kode atau nama item"></div>
    <div><label class="form-label">Baris</label><select class="form-select form-select-sm" data-detail-length><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></div>
    <div><label class="form-label">Urutkan</label><select class="form-select form-select-sm" data-detail-order><option value="invoice_date">Tanggal</option><option value="database">Database</option><option value="invoice_number">Invoice</option><option value="line">LiniPenjualan</option><option value="item_code">Kode item</option><option value="item_name">Nama item</option><option value="grade">Grade</option><option value="quantity">Quantity</option><option value="line_total">LineTotal</option></select></div>
    <div><label class="form-label">Arah</label><select class="form-select form-select-sm" data-detail-dir><option value="desc">Menurun</option><option value="asc">Menaik</option></select></div>
    @if (in_array(session(config('dashboard_security.session_user_role_key')), ['admin', 'direksi', 'sales', 'rnd'], true))
        <div>
            <label class="form-label d-block">Export</label>
            <a
                href="{{ route('sales-kpi.details.export.excel', array_merge($filter->query(), ['detail_type' => $detailType, 'detail_search' => $filter->search])) }}"
                class="btn btn-sm btn-success"
                data-detail-export
            >Export Semua Hasil ke Excel</a>
        </div>
    @endif
    <div class="small text-secondary ms-auto" data-detail-info></div>
</div>
<div class="alert alert-danger d-none kpi-detail-error" data-detail-error></div>
<div class="kpi-loading" data-detail-loading>Memuat detail transaksi…</div>
<div class="kpi-table-wrap d-none" data-detail-table-wrap>
    <table class="table table-sm table-bordered kpi-table">
        <thead><tr>
            @if ($showDatabase)<th data-column="database">Database</th>@endif
            <th>InvoiceDate</th><th>InvoiceNumber</th><th>LiniPenjualan</th><th>ItemCode</th><th>ItemName</th><th>Grade</th><th>Discontinue</th><th>Status Item Reject</th><th>Quantity</th><th>LineTotal</th>
        </tr></thead>
        <tbody data-detail-body></tbody>
    </table>
</div>
<div class="kpi-table-pagination"><button type="button" class="btn btn-sm btn-outline-secondary" data-detail-prev>Sebelumnya</button><button type="button" class="btn btn-sm btn-outline-secondary" data-detail-next>Berikutnya</button></div>
