@extends('layouts.app')

@section('title', 'Audit Keamanan Dashboard')

@php
    $loginLabels = [
        'login_success' => 'Login berhasil',
        'login_failed' => 'Login gagal',
        'login_throttled' => 'Login dibatasi',
        'device_pending' => 'Perangkat menunggu approval',
        'device_approved_login' => 'Login setelah device approved',
        'device_approved' => 'Perangkat disetujui',
        'device_rejected' => 'Perangkat ditolak',
        'device_revoked' => 'Akses perangkat dicabut',
        'session_denied' => 'Sesi ditolak',
        'logout' => 'Logout',
    ];
    $activityLabels = [
        'view_sales_dashboard' => 'Membuka Sales Dashboard',
        'view_sales_dashboard_details' => 'Membuka data mentah Sales Dashboard',
        'export_sales_dashboard_excel' => 'Export Sales Dashboard Excel',
        'export_sales_dashboard_pdf' => 'Export Sales Dashboard PDF',
        'view_sales_insights' => 'Membuka Sales Insights & R&D',
        'export_sales_insights_excel' => 'Export Sales Insights Excel',
        'view_sales_kpi' => 'Membuka Dashboard KPI',
        'view_sales_kpi_details' => 'Membuka detail KPI',
        'export_sales_kpi_excel' => 'Export KPI Excel',
        'export_sales_kpi_details_excel' => 'Export detail KPI Excel',
        'export_sales_kpi_pdf' => 'Export KPI PDF',
        'update_sales_kpi_targets' => 'Mengubah target KPI',
        'create_dashboard_user' => 'Membuat pengguna',
        'update_dashboard_user' => 'Mengubah pengguna',
        'reset_dashboard_user_password' => 'Reset password pengguna',
        'approve_dashboard_device' => 'Menyetujui perangkat',
        'reject_dashboard_device' => 'Menolak perangkat',
        'revoke_dashboard_device' => 'Mencabut perangkat',
        'rename_dashboard_device' => 'Mengubah nama perangkat',
        'authorization_denied' => 'Akses halaman ditolak',
    ];
@endphp

@push('styles')
<style>.security-card{background:#fff;border:1px solid #d0d5dd;border-radius:12px;box-shadow:0 6px 18px rgba(16,24,40,.06)}.audit-table{font-size:.82rem}.audit-table th{white-space:nowrap}</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div><h1 class="h4 fw-bold mb-1">Audit Keamanan</h1><div class="text-secondary small">Riwayat login, perangkat, pembukaan laporan, perubahan target, dan export.</div></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('security.users.index') }}">Pengguna</a><a class="btn btn-outline-secondary" href="{{ route('security.devices.index') }}">Perangkat</a></div>
</div>

<div class="security-card p-3 p-lg-4">
    <form method="GET" action="{{ route('security.audits.index') }}" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="col-md-5"><label class="form-label">Cari pengguna, device, IP, event, atau aktivitas</label><input class="form-control" name="q" value="{{ $search }}" placeholder="Contoh: Andreas, Laptop R&D, export"></div>
        <div class="col-md-2"><label class="form-label">Dari tanggal</label><input class="form-control" type="date" name="date_from" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label class="form-label">Sampai tanggal</label><input class="form-control" type="date" name="date_to" value="{{ $dateTo }}"></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-warning fw-bold">Terapkan</button><a class="btn btn-outline-secondary" href="{{ route('security.audits.index', ['tab'=>$tab]) }}">Reset</a></div>
    </form>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab==='login' ? 'active' : '' }}" href="{{ route('security.audits.index', ['tab'=>'login', 'q'=>$search, 'date_from'=>$dateFrom, 'date_to'=>$dateTo]) }}">Audit Login</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='activity' ? 'active' : '' }}" href="{{ route('security.audits.index', ['tab'=>'activity', 'q'=>$search, 'date_from'=>$dateFrom, 'date_to'=>$dateTo]) }}">Audit Aktivitas</a></li>
    </ul>

    @if($tab==='login')
        <div class="table-responsive"><table class="table table-sm table-striped align-middle audit-table"><thead><tr><th>Waktu</th><th>Event</th><th>Pengguna</th><th>Perangkat</th><th>IP</th><th>Alasan</th></tr></thead><tbody>@foreach($loginAudits as $audit)<tr><td>{{ optional($audit->created_at)->format('d-m-Y H:i:s') }}</td><td><span class="badge text-bg-dark">{{ $loginLabels[$audit->event] ?? $audit->event }}</span></td><td>{{ $audit->user?->name ?? $audit->username ?? '—' }}</td><td>{{ $audit->device?->label ?? '—' }}</td><td>{{ $audit->ip_address ?? '—' }}</td><td>{{ $audit->reason ?? '—' }}</td></tr>@endforeach</tbody></table></div>
        {{ $loginAudits->links() }}
    @else
        <div class="table-responsive"><table class="table table-sm table-striped align-middle audit-table"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Perangkat</th><th>Aktivitas</th><th>Route</th><th>Status</th><th>IP</th><th>Filter</th></tr></thead><tbody>@foreach($activityAudits as $audit)<tr><td>{{ optional($audit->created_at)->format('d-m-Y H:i:s') }}</td><td>{{ $audit->user?->name ?? '—' }}</td><td>{{ $audit->device?->label ?? '—' }}</td><td><span class="badge text-bg-primary">{{ $activityLabels[$audit->action] ?? $audit->action }}</span></td><td>{{ $audit->route_name ?? $audit->path }}</td><td>{{ $audit->response_status ?? '—' }}</td><td>{{ $audit->ip_address ?? '—' }}</td><td><code>{{ $audit->metadata ? json_encode($audit->metadata, JSON_UNESCAPED_UNICODE) : '—' }}</code></td></tr>@endforeach</tbody></table></div>
        {{ $activityAudits->links() }}
    @endif
</div>
@endsection
