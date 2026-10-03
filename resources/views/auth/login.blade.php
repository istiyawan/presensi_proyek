<!doctype html>
<html lang="id">
<head>
    @include('layouts.head', ['title' => 'Masuk'])
    @vite('resources/js/auth.js')
</head>
<body>
<div class="auth-page">
    <section class="auth-hero">
        <div class="hero-brand">
            <span class="sidebar-brand p-0 h-auto"><span class="brand-mark">
                @if (! empty($appSettings['logo']))
                    <img src="{{ route('branding.logo') }}" alt="">
                @else
                    {{ \App\Support\Brand::initials($appSettings['app_name']) }}
                @endif
            </span></span>
            {{ $appSettings['app_name'] }}
        </div>

        <div class="hero-copy">
            <h1>Kehadiran tim proyek, tercatat rapi dari lapangan.</h1>
            <p>Pantau check-in &amp; check-out berbasis lokasi dan foto, kelola izin, lalu cetak laporan absensi bulanan dalam hitungan detik.</p>
            <div class="hero-points">
                <div><i class="bi bi-geo-alt"></i>Validasi radius lokasi proyek &amp; deteksi lokasi palsu</div>
                <div><i class="bi bi-wifi-off"></i>Tetap bisa presensi saat sinyal di lapangan hilang</div>
                <div><i class="bi bi-file-earmark-text"></i>Laporan sesuai format periode proyek</div>
            </div>
        </div>

        <div class="hero-foot">{{ $appSettings['company_name'] }}</div>
    </section>

    <section class="auth-form-wrap">
        <div class="auth-card">
            <h2 class="mb-1">Selamat datang 👋</h2>
            <p class="lead mb-4">Masuk ke panel admin untuk mengelola presensi.</p>

            <form method="POST" action="{{ route('login') }}" data-loading novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus
                           class="form-control @error('username') is-invalid @enderror" placeholder="mis. admin">
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input id="password" type="password" name="password" autocomplete="current-password"
                               class="form-control @error('password') is-invalid @enderror" placeholder="••••••••">
                        <button class="btn btn-soft" type="button" data-toggle-password="#password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                    </div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label fs-7" for="remember">Ingat saya</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Masuk<i class="bi bi-arrow-right ms-2"></i></button>
            </form>

            <p class="text-muted fs-8 mt-4 mb-0 text-center">Karyawan lapangan melakukan presensi melalui aplikasi mobile.</p>
        </div>
    </section>
</div>
</body>
</html>
