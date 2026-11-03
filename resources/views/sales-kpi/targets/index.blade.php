@extends('layouts.app')

@section('title', 'Pengaturan Target KPI')

@push('styles')
<style>
    .target-panel { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 8px 24px rgba(16,24,40,.06); overflow:hidden; }
    .target-table-wrap { overflow:auto; max-height:70vh; }
    .target-table { min-width:900px; margin:0; }
    .target-table thead th { position:sticky; top:0; z-index:2; background:#101828; color:#fff; white-space:nowrap; }
    .target-money-input { min-width:190px; text-align:right; font-variant-numeric:tabular-nums; }
    .status-dot { display:inline-flex; border-radius:999px; padding:.28rem .6rem; font-size:.75rem; font-weight:800; }
    .status-active { background:#dcfce7; color:#166534; }
    .status-inactive { background:#f2f4f7; color:#475467; }
    .status-missing { background:#fef3c7; color:#92400e; }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Pengaturan Target KPI</h1>
        <div class="text-secondary">Satu target gabungan untuk setiap LiniPenjualan. Target berlaku untuk semua periode laporan.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('sales-kpi.index') }}">Kembali ke Dashboard KPI</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if (session('report_error') || isset($error))
    <div class="alert alert-danger" style="white-space:pre-line">{{ session('report_error') ?? $error }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-bold mb-1">Target belum dapat disimpan.</div>
        <ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif

<div class="target-panel">
    <form method="POST" action="{{ route('sales-kpi.targets.update') }}">
        @csrf
        @method('PUT')
        <div class="p-3 border-bottom d-flex flex-column flex-md-row justify-content-between gap-2">
            <input id="targetSearch" type="search" class="form-control" style="max-width:360px" placeholder="Cari LiniPenjualan">
            <button class="btn btn-report" type="submit" @disabled(empty($rows))>Simpan Semua Perubahan</button>
        </div>
        <div class="target-table-wrap">
            <table class="table table-bordered align-middle target-table" id="targetTable">
                <thead>
                <tr>
                    <th>LiniPenjualan</th>
                    <th>Target Gabungan</th>
                    <th>Omzet Bulan Ini</th>
                    <th>Status Transaksi</th>
                    <th>Status Target</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr data-target-row data-line="{{ strtoupper($row->line) }}">
                        <td class="fw-bold">{{ $row->line }}</td>
                        <td>
                            <input
                                name="targets[{{ $row->lineKey }}]"
                                value="{{ old('targets.'.$row->lineKey, $row->monthlyTarget === null ? '' : number_format($row->monthlyTarget, 0, ',', '.')) }}"
                                inputmode="numeric"
                                class="form-control target-money-input"
                                aria-label="Target {{ $row->line }}"
                            >
                        </td>
                        <td class="text-end">Rp {{ number_format($row->currentOmzet, 0, ',', '.') }}</td>
                        <td>
                            <span class="status-dot {{ $row->hasCurrentTransactions ? 'status-active' : 'status-inactive' }}">
                                {{ $row->hasCurrentTransactions ? 'Aktif bulan ini' : 'Tidak ada transaksi bulan ini' }}
                            </span>
                        </td>
                        <td>
                            <span class="status-dot {{ $row->hasTarget ? 'status-active' : 'status-missing' }}">
                                {{ $row->hasTarget ? 'Sudah diatur' : 'Target belum diatur' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-5">Daftar LiniPenjualan belum tersedia.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top d-flex justify-content-end">
            <button class="btn btn-report" type="submit" @disabled(empty($rows))>Simpan Semua Perubahan</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const formatMoney = (value) => {
        const digits = String(value || '').replace(/\D/g, '');
        return digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
    };
    document.querySelectorAll('.target-money-input').forEach((input) => {
        input.addEventListener('input', () => { input.value = formatMoney(input.value); });
    });
    const search = document.getElementById('targetSearch');
    search?.addEventListener('input', () => {
        const term = search.value.trim().toUpperCase();
        document.querySelectorAll('[data-target-row]').forEach((row) => {
            row.hidden = term !== '' && !row.dataset.line.includes(term);
        });
    });
})();
</script>
@endpush
