<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Service Sparepart</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color:#101828; background:#f2f4f7; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:radial-gradient(circle at top,#fff7dd 0,#f2f4f7 44%,#e9edf4 100%); }
        .login-shell { width:min(100%,430px); }
        .brand { text-align:center; margin-bottom:22px; }
        .brand-mark { width:66px; height:66px; margin:0 auto 12px; border-radius:18px; display:grid; place-items:center; background:#ffc400; color:#111827; font-weight:900; font-size:20px; box-shadow:0 14px 35px rgba(16,24,40,.16); }
        .brand h1 { margin:0; font-size:26px; }
        .brand p { margin:7px 0 0; color:#667085; font-size:14px; }
        .card { background:rgba(255,255,255,.96); border:1px solid #e4e7ec; border-radius:20px; padding:30px; box-shadow:0 22px 50px rgba(16,24,40,.12); }
        label { display:block; margin-bottom:7px; font-size:14px; font-weight:700; }
        .field { margin-bottom:18px; }
        input { width:100%; border:1px solid #d0d5dd; border-radius:10px; padding:12px 13px; font-size:15px; outline:none; transition:.15s; }
        input:focus { border-color:#f0b400; box-shadow:0 0 0 4px rgba(255,196,0,.18); }
        button { width:100%; border:0; border-radius:10px; padding:13px; background:#f9bf00; color:#111827; font-weight:800; font-size:15px; cursor:pointer; }
        button:hover { background:#eeb600; }
        .alert { margin-bottom:18px; border-radius:10px; padding:11px 13px; background:#fef3f2; color:#b42318; border:1px solid #fecdca; font-size:14px; }
        .hint { text-align:center; color:#98a2b3; margin-top:16px; font-size:12px; }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="brand">
        <div class="brand-mark">SP</div>
        <h1>Service &amp; Sparepart</h1>
        <p>Penawaran service, pencarian sparepart, master service, dan laporan sparepart.</p>
    </div>

    <div class="card">
        @if (isset($securityReady) && !$securityReady)
            <div class="alert">
                <strong>Database user Service belum siap.</strong><br>
                Jalankan <code>php artisan dashboard-security:install</code>.
            </div>
        @endif

        @if ($errors->has('login'))
            <div class="alert">{{ $errors->first('login') }}</div>
        @endif

        @if (session('status'))
            <div class="alert" style="background:#ecfdf3;color:#027a48;border-color:#abefc6">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.login.store', [], false) }}">
            @csrf
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
                @error('username')<div class="alert" style="margin-top:8px;margin-bottom:0">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                @error('password')<div class="alert" style="margin-top:8px;margin-bottom:0">{{ $message }}</div>@enderror
            </div>
            <button type="submit" @disabled(isset($securityReady) && !$securityReady)>Masuk ke Service</button>
        </form>
    </div>
    <div class="hint">User Service terpisah dari user Sales Dashboard.</div>
</div>
</body>
</html>
