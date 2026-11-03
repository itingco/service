@extends('layouts.app')

@section('title', 'Riwayat Perangkat Dashboard')

@push('styles')
<style>
    .security-card { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.06); }
    .device-history { border-left:5px solid #2563eb; }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Riwayat Perangkat Dashboard</h1>
        <div class="text-secondary small">Perangkat hanya dicatat untuk audit. Akses dashboard ditentukan oleh username, password, dan status akun.</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('security.users.index') }}">Pengguna</a>
        <a class="btn btn-outline-secondary" href="{{ route('security.audits.index') }}">Audit</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="security-card p-3 mb-3">
    <div class="small text-secondary">Status approval lama tidak lagi digunakan untuk memblokir login. Daftar ini berfungsi sebagai inventaris browser/perangkat yang pernah digunakan.</div>
</div>

<div class="row g-3">
@forelse($devices as $device)
    <div class="col-12">
        <div class="security-card p-3 device-history">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <h2 class="h6 fw-bold mb-0">{{ $device->label }}</h2>
                        <span class="badge text-bg-primary">TERCATAT</span>
                        @if($currentDeviceId===$device->id)<span class="badge text-bg-success">SESI SAAT INI</span>@endif
                    </div>
                    <div class="small text-secondary mt-2">{{ $device->user?->name }} ({{ $device->user?->username }}) · {{ $device->browser ?: '—' }} / {{ $device->platform ?: '—' }}</div>
                    <div class="small text-secondary">IP pertama {{ $device->first_ip ?: '—' }} · IP terakhir {{ $device->last_ip ?: '—' }} · terakhir terlihat {{ optional($device->last_seen_at)->format('d-m-Y H:i:s') ?? '—' }}</div>
                    <div class="small text-secondary">Pertama tercatat {{ optional($device->created_at)->format('d-m-Y H:i:s') ?? '—' }}</div>
                </div>
                <div style="min-width:360px">
                    <form method="POST" action="{{ route('security.devices.update', $device) }}" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <input class="form-control form-control-sm" name="label" value="{{ $device->label }}" required>
                        <button class="btn btn-sm btn-outline-primary">Ubah nama</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="col-12"><div class="security-card p-5 text-center text-secondary">Belum ada perangkat yang tercatat.</div></div>
@endforelse
</div>
<div class="mt-3">{{ $devices->links() }}</div>
@endsection
