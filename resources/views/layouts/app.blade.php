<!doctype html>
<html lang="id">
<head>
    @include('layouts.head')
    @vite('resources/js/app.js')
    @stack('head')
</head>
<body data-page="{{ $page ?? '' }}">
<div class="app-shell">
    @include('layouts.sidebar')
    <div class="sidebar-backdrop"></div>

    <div class="app-main">
        @include('layouts.topbar')

        <main class="app-content">
            @if (! $currentProject && ! request()->routeIs('projects.*', 'users.*', 'settings.app*', 'positions.*'))
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-buildings"></i></div>
                            <h6>Belum ada proyek</h6>
                            <p>Buat proyek pertama untuk mulai mengelola presensi.</p>
                            @if (auth()->user()->isSuperAdmin())
                                <a href="{{ route('projects.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg me-1"></i>Buat Proyek</a>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                @yield('content')
            @endif
        </main>
    </div>
</div>

@stack('modals')
<script>
    window.App = {{ Js::from([
        'projectId' => $currentProject?->id,
        'timezone' => $currentProject?->timezone ?? config('app.timezone'),
        'canManage' => $canManageProject,
    ]) }};
</script>
@stack('scripts')
</body>
</html>
