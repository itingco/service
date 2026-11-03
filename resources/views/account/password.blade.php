@extends('layouts.app')

@section('title', 'Ganti Password')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="filter-panel p-3 p-lg-4">
            <div class="mb-3">
                <h1 class="h4 fw-bold mb-1">Ganti Password</h1>
                <div class="text-secondary small">
                    Akun: <strong>{{ $user->name }}</strong> ({{ $user->username }})
                </div>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('account.password.update') }}" class="vstack gap-3">
                @csrf
                @method('PUT')

                <div>
                    <label class="form-label fw-bold">Password Lama</label>
                    <input type="password" name="current_password" class="form-control" autocomplete="current-password" required autofocus>
                </div>

                <div>
                    <label class="form-label fw-bold">Password Baru</label>
                    <input type="password" name="password" class="form-control" minlength="{{ $passwordMinLength }}" autocomplete="new-password" required>
                    <div class="form-text">Minimal {{ $passwordMinLength }} karakter. Kombinasi karakter bebas.</div>
                </div>

                <div>
                    <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="{{ $passwordMinLength }}" autocomplete="new-password" required>
                </div>

                <div class="alert alert-info small mb-0">
                    Setelah password berhasil diubah, sesi saat ini tetap aktif. Riwayat perangkat tetap disimpan untuk audit.
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Kembali</a>
                    <button type="submit" class="btn btn-warning fw-bold">Simpan Password Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
