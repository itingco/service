@extends('layouts.app')

@section('title', 'Role & Permission')

@push('styles')
<style>
    .role-card { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.06); }
    .permission-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:.6rem .9rem; }
    .permission-item { border:1px solid #e4e7ec; border-radius:9px; padding:.65rem .75rem; background:#f9fafb; }
    .system-role { border-color:#f2c94c; background:#fffaf0; }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Role &amp; Permission</h1>
        <div class="text-secondary small">Akses diberikan per menu. Administrator selalu memiliki akses penuh.</div>
    </div>
    @if($dashboardMenuAccess['security_users'] ?? false)<a class="btn btn-outline-secondary" href="{{ route('security.users.index') }}">Pengguna</a>@endif
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="role-card p-3 p-lg-4 mb-4">
    <h2 class="h6 fw-bold mb-3">Buat Role Baru</h2>
    <form method="POST" action="{{ route('security.roles.store') }}">
        @csrf
        <div class="row g-3 align-items-end mb-3">
            <div class="col-lg-5">
                <label class="form-label">Nama Role</label>
                <input class="form-control" name="name" value="{{ old('name') }}" placeholder="Contoh: Supervisor Sales" required maxlength="120">
            </div>
            <div class="col-lg-3">
                <button class="btn btn-warning fw-bold w-100">Buat Role</button>
            </div>
        </div>
        <div class="permission-grid">
            @foreach($menuCatalog as $menuKey => $menu)
                <label class="permission-item d-flex gap-2 align-items-start">
                    <input class="form-check-input mt-1" type="checkbox" name="menus[]" value="{{ $menuKey }}" @checked(in_array($menuKey, (array) old('menus', []), true))>
                    <span><strong>{{ $menu['label'] }}</strong><br><span class="small text-secondary">{{ $menu['group'] }}</span></span>
                </label>
            @endforeach
        </div>
    </form>
</div>

<div class="d-grid gap-3">
    @foreach($roles as $role)
        @php($granted = $role->menus->pluck('menu_key')->all())
        <div class="role-card p-3 p-lg-4 {{ $role->isAdministrator() ? 'system-role' : '' }}">
            @if($role->isAdministrator())
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2"><h2 class="h5 fw-bold mb-0">{{ $role->name }}</h2><span class="badge text-bg-warning">SYSTEM ROLE</span></div>
                        <div class="text-secondary small mt-1">Kode: {{ $role->role_key }} · akses penuh permanen.</div>
                    </div>
                    <span class="badge text-bg-dark">Semua Menu</span>
                </div>
            @else
                <form method="POST" action="{{ route('security.roles.update', $role) }}">
                    @csrf @method('PUT')
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-lg-5">
                            <label class="form-label">Nama Role</label>
                            <input class="form-control" name="name" value="{{ $role->name }}" required maxlength="120">
                            <div class="small text-secondary mt-1">Kode role: {{ $role->role_key }}</div>
                        </div>
                        <div class="col-lg-3">
                            <button class="btn btn-primary w-100">Simpan Permission</button>
                        </div>
                    </div>
                    <div class="permission-grid">
                        @foreach($menuCatalog as $menuKey => $menu)
                            <label class="permission-item d-flex gap-2 align-items-start">
                                <input class="form-check-input mt-1" type="checkbox" name="menus[]" value="{{ $menuKey }}" @checked(in_array($menuKey, $granted, true))>
                                <span><strong>{{ $menu['label'] }}</strong><br><span class="small text-secondary">{{ $menu['group'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </form>
            @endif
        </div>
    @endforeach
</div>
@endsection
