@extends('layouts.app')

@section('title', 'Pengguna Dashboard')

@push('styles')
<style>
    .security-card { background:#fff; border:1px solid #d0d5dd; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,.06); }
    .security-table th { white-space:nowrap; }
    .password-form { min-width:260px; }
    .sales-map-note { font-size:.72rem; color:#667085; }
</style>
@endpush

@section('content')
@php($passwordMinLength = max(1, (int) config('dashboard_security.password_min_length', 8)))
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div><h1 class="h4 fw-bold mb-1">Pengguna Dashboard</h1><div class="text-secondary small">Setiap orang menggunakan akun pribadi agar aktivitas dapat ditelusuri. User role Sales wajib dipetakan ke Sales Person ERP.</div></div>
    <div class="d-flex gap-2 flex-wrap">@if($dashboardMenuAccess['security_roles'] ?? false)<a class="btn btn-outline-secondary" href="{{ route('security.roles.index') }}">Role & Permission</a>@endif @if($dashboardMenuAccess['security_devices'] ?? false)<a class="btn btn-outline-secondary" href="{{ route('security.devices.index') }}">Perangkat</a>@endif @if($dashboardMenuAccess['security_audits'] ?? false)<a class="btn btn-outline-secondary" href="{{ route('security.audits.index') }}">Audit</a>@endif</div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="security-card p-3 p-lg-4 mb-4">
    <h2 class="h6 fw-bold">Tambah Pengguna</h2>
    <form method="POST" action="{{ route('security.users.store') }}" class="row g-3 user-profile-form">
        @csrf
        <div class="col-md-6 col-xl-3"><label class="form-label">Nama lengkap</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
        <div class="col-md-6 col-xl-2"><label class="form-label">Username</label><input class="form-control" name="username" value="{{ old('username') }}" required></div>
        <div class="col-md-6 col-xl-2"><label class="form-label">Role</label><select class="form-select role-select" name="role" required>@foreach($roles as $value=>$label)<option value="{{ $value }}" @selected(old('role')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Sales Person ERP</label>
            <select class="form-select sales-person-select" name="sales_person_id">
                <option value="">— Tidak terkait Sales —</option>
                @foreach($salesPersonOptions as $value=>$label)<option value="{{ $value }}" @selected((string) old('sales_person_id')===(string) $value)>{{ $label }}</option>@endforeach
            </select>
            <div class="sales-map-note mt-1">Wajib jika role = Sales. Mapping ini membatasi Customer Analysis ke customer milik sales tersebut.</div>
        </div>
        <div class="col-md-6 col-xl-2"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="{{ $passwordMinLength }}" required></div>
        <div class="col-md-6 col-xl-2"><label class="form-label">Konfirmasi</label><input class="form-control" type="password" name="password_confirmation" minlength="{{ $passwordMinLength }}" required></div>
        <div class="col-md-3 col-xl-1 d-grid align-self-end"><button class="btn btn-warning fw-bold">Tambah</button></div>
    </form>
</div>

<div class="security-card p-3 p-lg-4">
    <div class="table-responsive">
        <table class="table table-sm align-middle security-table">
            <thead><tr><th>Pengguna</th><th>Username</th><th>Role / Status</th><th>Sales Person ERP</th><th>Perangkat</th><th>Login terakhir</th><th>Ubah profil</th><th>Reset password</th></tr></thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td class="fw-bold">{{ $user->name }}</td>
                    <td>{{ $user->username }}</td>
                    <td><span class="badge text-bg-dark">{{ $roles[$user->role] ?? $user->role }}</span> <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'AKTIF' : 'NONAKTIF' }}</span></td>
                    <td>
                        @if($user->sales_person_id)
                            <span class="badge text-bg-warning">{{ $salesPersonOptions[(int) $user->sales_person_id] ?? ('ID '.$user->sales_person_id) }}</span>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td><span class="badge text-bg-primary">{{ $user->devices_count }} tercatat</span></td>
                    <td>{{ optional($user->last_login_at)->format('d-m-Y H:i') ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('security.users.update', $user) }}" class="d-flex gap-1 flex-wrap user-profile-form">
                            @csrf @method('PUT')
                            <input class="form-control form-control-sm" name="name" value="{{ $user->name }}" style="width:160px" required>
                            <select class="form-select form-select-sm role-select" name="role" style="width:160px" @disabled($user->role === 'admin' && !$canManageAdministrator)>
                                @if($user->role === 'admin' && !array_key_exists('admin', $roles))<option value="admin" selected>Administrator</option>@endif
                                @foreach($roles as $value=>$label)<option value="{{ $value }}" @selected($user->role===$value)>{{ $label }}</option>@endforeach
                            </select>
                            @if($user->role === 'admin' && !$canManageAdministrator)<input type="hidden" name="role" value="admin">@endif
                            <select class="form-select form-select-sm sales-person-select" name="sales_person_id" style="width:230px">
                                <option value="">— Tidak terkait Sales —</option>
                                @if($user->sales_person_id && !array_key_exists((int) $user->sales_person_id, $salesPersonOptions))
                                    <option value="{{ $user->sales_person_id }}" selected>ID {{ $user->sales_person_id }} (tidak ditemukan di master)</option>
                                @endif
                                @foreach($salesPersonOptions as $value=>$label)<option value="{{ $value }}" @selected((int) $user->sales_person_id===(int) $value)>{{ $label }}</option>@endforeach
                            </select>
                            <input type="hidden" name="is_active" value="0"><label class="form-check d-flex align-items-center gap-1 m-0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($user->is_active)> Aktif</label>
                            <button class="btn btn-sm btn-primary">Simpan</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('security.users.password', $user) }}" class="d-flex gap-1 password-form">
                            @csrf @method('PUT')
                            <input class="form-control form-control-sm" type="password" name="password" placeholder="Password baru" minlength="{{ $passwordMinLength }}" required>
                            <input class="form-control form-control-sm" type="password" name="password_confirmation" placeholder="Konfirmasi" minlength="{{ $passwordMinLength }}" required>
                            <button class="btn btn-sm btn-outline-danger">Reset</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function syncSalesRequired(form) {
        const role = form.querySelector('.role-select');
        const sales = form.querySelector('.sales-person-select');
        if (!role || !sales) return;
        sales.required = role.value === 'sales';
        sales.classList.toggle('border-warning', role.value === 'sales' && !sales.value);
    }

    document.querySelectorAll('.user-profile-form').forEach(function (form) {
        syncSalesRequired(form);
        form.querySelector('.role-select')?.addEventListener('change', function () { syncSalesRequired(form); });
        form.querySelector('.sales-person-select')?.addEventListener('change', function () { syncSalesRequired(form); });
    });
})();
</script>
@endpush
