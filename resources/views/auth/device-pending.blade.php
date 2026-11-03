<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>Menunggu Persetujuan Perangkat</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color:#101828; background:#f2f4f7; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:radial-gradient(circle at top,#fff7dd 0,#f2f4f7 45%,#e9edf4 100%); }
        .card { width:min(100%,620px); background:#fff; border:1px solid #e4e7ec; border-radius:20px; padding:30px; box-shadow:0 22px 50px rgba(16,24,40,.12); }
        .icon { width:64px; height:64px; border-radius:18px; display:grid; place-items:center; background:#fef3c7; color:#92400e; font-size:28px; margin-bottom:18px; }
        h1 { margin:0 0 8px; font-size:25px; }
        .muted { color:#667085; line-height:1.55; }
        .grid { display:grid; grid-template-columns:160px 1fr; gap:9px 14px; margin:24px 0; padding:18px; border-radius:12px; background:#f8fafc; border:1px solid #e4e7ec; font-size:14px; }
        .label { color:#667085; font-weight:700; }
        .status { display:inline-flex; padding:5px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-weight:800; text-transform:uppercase; font-size:12px; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border:0; border-radius:10px; padding:11px 15px; font-weight:800; cursor:pointer; }
        .btn-primary { background:#f9bf00; color:#111827; }
        .btn-secondary { background:#eef2f6; color:#344054; }
        @media(max-width:540px){ .grid{grid-template-columns:1fr}.label{margin-top:6px} }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">⌛</div>
    <h1>Perangkat menunggu persetujuan</h1>
    <p class="muted">Username dan password sudah benar, tetapi perangkat ini belum diizinkan. Administrator harus menyetujuinya dari menu <strong>Keamanan → Perangkat</strong>.</p>

    <div class="grid">
        <div class="label">Pengguna</div><div>{{ $user->name }} ({{ $user->username }})</div>
        <div class="label">Nama perangkat</div><div>{{ $device->label }}</div>
        <div class="label">Browser / sistem</div><div>{{ $device->browser ?: '—' }} / {{ $device->platform ?: '—' }}</div>
        <div class="label">Alamat IP</div><div>{{ $device->last_ip ?: '—' }}</div>
        <div class="label">Status</div><div><span class="status">{{ $device->status }}</span></div>
        <div class="label">Didaftarkan</div><div>{{ optional($device->created_at)->format('d-m-Y H:i:s') }}</div>
    </div>

    @if (in_array($device->status, ['rejected', 'revoked'], true))
        <p class="muted"><strong>Akses perangkat ditolak atau dicabut.</strong> Hubungi administrator. Untuk mengajukan perangkat baru, hapus cookie situs lalu login kembali.</p>
    @else
        <p class="muted">Halaman memeriksa status otomatis setiap 15 detik. Setelah disetujui, Anda akan diarahkan ke dashboard.</p>
    @endif

    <div class="actions">
        <a class="btn btn-primary" href="{{ route('dashboard.device.pending') }}">Cek status sekarang</a>
        <a class="btn btn-secondary" href="{{ route('dashboard.login') }}">Kembali ke login</a>
    </div>
</div>
</body>
</html>
