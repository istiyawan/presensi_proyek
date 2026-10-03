@php
    $user = auth()->user();
    $isSuper = $user->isSuperAdmin();
    $menu = [
        'Utama' => [
            ['route' => 'dashboard', 'icon' => 'grid-1x2', 'label' => 'Dashboard', 'show' => true],
            ['route' => 'attendances.index', 'icon' => 'fingerprint', 'label' => 'Monitoring Presensi', 'show' => true],
            ['route' => 'approvals.index', 'icon' => 'patch-check', 'label' => 'Persetujuan', 'show' => true, 'badge' => $pendingApprovals],
        ],
        'Data Proyek' => [
            ['route' => 'employees.index', 'icon' => 'people', 'label' => 'Karyawan', 'show' => $canManageProject],
            ['route' => 'shifts.index', 'icon' => 'clock', 'label' => 'Shift & Jam Kerja', 'show' => $canManageProject],
            ['route' => 'locations.index', 'icon' => 'geo-alt', 'label' => 'Titik Lokasi', 'show' => $canManageProject],
            ['route' => 'holidays.index', 'icon' => 'calendar2-heart', 'label' => 'Hari Libur', 'show' => $canManageProject],
        ],
        'Laporan' => [
            ['route' => 'reports.index', 'icon' => 'file-earmark-bar-graph', 'label' => 'Laporan Absensi', 'show' => true],
        ],
        'Pengaturan' => [
            ['route' => 'settings.project', 'match' => 'settings.project*', 'icon' => 'sliders', 'label' => 'Pengaturan Proyek', 'show' => $canManageProject],
            ['route' => 'positions.index', 'icon' => 'person-badge', 'label' => 'Jabatan', 'show' => $canManageProject || $isSuper],
            ['route' => 'projects.index', 'icon' => 'buildings', 'label' => $multiProject ? 'Daftar Proyek' : 'Profil Proyek', 'show' => $isSuper],
            ['route' => 'users.index', 'icon' => 'shield-lock', 'label' => 'Pengguna Admin', 'show' => $isSuper],
            ['route' => 'settings.app', 'match' => 'settings.app*', 'icon' => 'palette', 'label' => 'Pengaturan Aplikasi', 'show' => $isSuper],
        ],
    ];
@endphp

<aside class="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <span class="brand-mark">
            @if (! empty($appSettings['logo']))
                <img src="{{ route('branding.logo') }}" alt="">
            @else
                {{ \App\Support\Brand::initials($appSettings['app_name'] ?? 'PP') }}
            @endif
        </span>
        <span class="brand-text">
            <strong>{{ $appSettings['app_name'] ?? 'Presensi Proyek' }}</strong>
            <small>{{ $appSettings['company_name'] ?? '' }}</small>
        </span>
    </a>

    <nav class="sidebar-nav">
        @foreach ($menu as $section => $items)
            @php $visible = collect($items)->where('show', true); @endphp
            @continue($visible->isEmpty())
            <div class="nav-section">{{ $section }}</div>
            @foreach ($visible as $item)
                @if (! empty($item['soon']))
                    <span class="nav-link disabled">
                        <i class="bi bi-{{ $item['icon'] }}"></i>{{ $item['label'] }}<span class="nav-soon">Fase 3</span>
                    </span>
                @else
                    <a href="{{ route($item['route']) }}"
                       class="nav-link {{ request()->routeIs($item['match'] ?? \Illuminate\Support\Str::beforeLast($item['route'], '.').'.*') ? 'active' : '' }}">
                        <i class="bi bi-{{ $item['icon'] }}"></i>{{ $item['label'] }}
                        @if (! empty($item['badge']))
                            <span class="nav-badge">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        @endforeach
    </nav>

    <div class="sidebar-footer">
        Versi {{ config('presensi.version', '1.0') }} · {{ now()->year }}
    </div>
</aside>
