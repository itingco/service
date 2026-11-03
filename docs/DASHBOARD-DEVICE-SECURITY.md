# Dashboard User Security & Device Audit

## Tujuan

Dashboard menggunakan akun pribadi per pengguna agar akses dan aktivitas dapat ditelusuri. Validasi akses hanya menggunakan username, password, status akun, role, rate limit, dan idle session timeout.

Informasi perangkat/browser tetap dicatat sebagai audit/history, tetapi **tidak digunakan untuk memblokir login**. Browser biasa tidak memberikan MAC address atau hostname Windows secara andal, sehingga browser profile dikenali secara best-effort menggunakan device token pada cookie HttpOnly.

## Instalasi

Pastikan `.env` memiliki:

```env
DASHBOARD_ADMIN_NAME="Dashboard Administrator"
DASHBOARD_ADMIN_USER=admin
DASHBOARD_ADMIN_PASSWORD="password-minimal-8"
DASHBOARD_PASSWORD_MIN_LENGTH=8
DASHBOARD_SESSION_IDLE_MINUTES=30
DASHBOARD_LOGIN_MAX_ATTEMPTS=5
DASHBOARD_LOGIN_IP_MAX_ATTEMPTS=15
DASHBOARD_LOGIN_DECAY_SECONDS=60
DASHBOARD_DEVICE_COOKIE_SECURE=true
DASHBOARD_DEVICE_COOKIE_SAME_SITE=strict
```

Pada lokal/production yang masih HTTP, gunakan `DASHBOARD_DEVICE_COOKIE_SECURE=false`. Pada HTTPS gunakan `true`. Nilai cookie ini hanya memengaruhi korelasi riwayat perangkat, bukan hak login.

Jalankan:

```bash
php artisan optimize:clear
php artisan dashboard-security:install
```

Jika akun admin sudah ada dan password `.env` ingin diterapkan ulang:

```bash
php artisan dashboard-security:install --reset-password
```

## Permission SQLite di Linux

Security menggunakan file SQLite yang sama dengan target KPI:

```text
database/database.sqlite
```

PHP-FPM harus dapat menulis ke file dan folder:

```bash
sudo chown "$USER":www-data database database/database.sqlite
sudo chmod 2775 database
sudo chmod 664 database/database.sqlite
```

`database/database.sqlite` adalah **runtime database** dan tidak boleh di-track Git. Pastikan:

```bash
git ls-files database/database.sqlite
```

tidak menghasilkan output.

## Alur login

1. Pengguna memasukkan username dan password pribadi.
2. Sistem memvalidasi akun aktif dan password.
3. Jika benar, session dashboard langsung aktif.
4. Sistem mencoba mencatat/update browser/perangkat, IP, user agent, dan waktu terakhir terlihat.
5. Jika pencatatan device gagal, login user yang valid tetap tidak diblokir.
6. Status lama `pending/approved/rejected/revoked` pada tabel device tidak lagi dipakai untuk authorization.

## Halaman keamanan

- `/security/users` — tambah pengguna, ubah role/status, reset password.
- `/security/devices` — riwayat/inventaris perangkat yang pernah digunakan; admin dapat mengganti label.
- `/security/audits` — audit login dan aktivitas dashboard.
- `/account/password` — pengguna mengganti password sendiri.

## Role

- `admin` — semua laporan, export, target, dan administrasi keamanan.
- `direksi` — laporan, insights, KPI, dan export.
- `sales` — laporan, insights, KPI, dan export.
- `rnd` — laporan, Sales Insights & R&D, KPI, dan export.
- `viewer` — tampilan laporan tanpa export dan tanpa pengaturan target.

## Audit

Audit mencatat login sukses/gagal, rate-limit, logout, session denied, pembukaan dashboard, export Excel/PDF, perubahan target, perubahan password, user management, dan label device.

Device history mencatat label, browser, platform, first/last IP, user agent, waktu pertama tercatat, dan terakhir terlihat.

Password dan token perangkat tidak pernah ditulis ke audit.

## Password sederhana dan ganti password sendiri

Kebijakan password menggunakan `DASHBOARD_PASSWORD_MIN_LENGTH` dengan default `8`. Tidak ada kewajiban kombinasi huruf besar, huruf kecil, angka, atau simbol.

Pengguna dapat membuka `/account/password`, lalu memasukkan password lama, password baru, dan konfirmasi. Setelah berhasil, sesi saat ini tetap aktif dan riwayat perangkat tidak diubah/dicabut.

## Catatan keamanan production

- Gunakan HTTPS jika memungkinkan.
- Set `APP_DEBUG=false` di production.
- Jangan commit `.env` atau `database/database.sqlite`.
- Backup SQLite secara berkala.
- Untuk pembatasan berdasarkan device fisik yang benar-benar kuat, gunakan Tailscale, Cloudflare Access/device certificate, VPN, atau MDM di luar Laravel.
