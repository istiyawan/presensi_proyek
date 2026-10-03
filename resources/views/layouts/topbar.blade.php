@php $user = auth()->user(); @endphp
<header class="topbar">
    <button class="btn btn-soft btn-icon d-lg-none" type="button" data-sidebar-toggle aria-label="Menu">
        <i class="bi bi-list fs-5"></i>
    </button>

    @if ($currentProject && ! $multiProject)
        {{-- Mode 1 proyek: cukup label, tanpa pilihan ganti proyek --}}
        <div class="project-switcher min-w-0">
            <span class="code">{{ $currentProject->code }}</span>
            <span class="label min-w-0">
                <small>Proyek</small>
                <span>{{ $currentProject->name }}</span>
            </span>
        </div>
    @elseif ($currentProject)
        <div class="dropdown min-w-0">
            <button class="project-switcher" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="code">{{ $currentProject->code }}</span>
                <span class="label min-w-0">
                    <small>Proyek aktif</small>
                    <span>{{ $currentProject->name }}</span>
                </span>
                <i class="bi bi-chevron-expand text-muted ms-1"></i>
            </button>
            <div class="dropdown-menu" style="min-width: 320px">
                <h6 class="dropdown-header">Ganti proyek</h6>
                @foreach ($accessibleProjects as $p)
                    <form method="POST" action="{{ route('projects.switch', $p) }}">
                        @csrf
                        <button class="dropdown-item {{ $p->id === $currentProject->id ? 'active-project' : '' }}" type="submit">
                            <span class="chip {{ $p->id === $currentProject->id ? 'soft-brand' : '' }}">{{ $p->code }}</span>
                            <span class="text-truncate">{{ $p->name }}</span>
                            @if (! $p->is_active)<span class="chip soft-slate ms-auto">Nonaktif</span>@endif
                            @if ($p->id === $currentProject->id)<i class="bi bi-check2 ms-auto text-brand"></i>@endif
                        </button>
                    </form>
                @endforeach
                @if ($user->isSuperAdmin())
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('projects.index') }}"><i class="bi bi-buildings"></i>Kelola proyek</a>
                @endif
            </div>
        </div>
    @endif

    <div class="ms-auto d-flex align-items-center gap-3">
        @if ($currentProject)
            <div class="topbar-clock d-none d-xl-block" data-clock="{{ $currentProject->timezone }}"
                 data-tz-label="{{ \App\Support\Brand::timezoneLabel($currentProject->timezone) }}"></div>
        @endif

        <div class="dropdown">
            <button class="user-chip border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar avatar-sm" style="background:linear-gradient(135deg,var(--brand),#0b1324)">{{ \App\Support\Brand::initials($user->name) }}</span>
                <span class="meta d-none d-sm-block">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ ['super_admin' => 'Super Admin', 'project_admin' => 'Admin Proyek', 'team_leader' => 'Team Leader'][$user->getRoleNames()->first(fn ($r) => $r !== 'employee')] ?? 'Pengguna' }}</small>
                </span>
                <i class="bi bi-chevron-down text-muted fs-8 d-none d-sm-inline"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <h6 class="dropdown-header">{{ '@'.$user->username }}</h6>
                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#modalPassword"><i class="bi bi-key"></i>Ganti password</button>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i>Keluar</button>
                </form>
            </div>
        </div>
    </div>
</header>

@push('modals')
    <div class="modal fade" id="modalPassword" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formPassword" method="POST" action="{{ route('account.password') }}" data-ajax-form>
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Ganti Password</h5>
                        <p class="modal-subtitle">Gunakan minimal 8 karakter.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Password saat ini</label>
                        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password baru</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="form-label">Ulangi password baru</label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endpush
