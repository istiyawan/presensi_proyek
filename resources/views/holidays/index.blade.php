@extends('layouts.app')

@php
    $typeLabels = ['national' => 'Libur nasional', 'cuti_bersama' => 'Cuti bersama', 'project' => 'Libur proyek'];
    $typeTones = ['national' => 'danger', 'cuti_bersama' => 'warning', 'project' => 'brand'];
@endphp

@section('content')
    <x-page-header title="Hari Libur" :eyebrow="$currentProject->name"
                   subtitle="Hari libur tidak dihitung <em>alpha</em>. Karyawan yang tetap masuk dicatat hadir di hari libur.">
        <x-slot:actions>
            <div class="btn-group">
                <a href="{{ route('holidays.index', ['year' => $year - 1]) }}" class="btn btn-soft" aria-label="Tahun sebelumnya"><i class="bi bi-chevron-left"></i></a>
                <span class="btn btn-soft fw-bold disabled text-ink" style="opacity:1">{{ $year }}</span>
                <a href="{{ route('holidays.index', ['year' => $year + 1]) }}" class="btn btn-soft" aria-label="Tahun berikutnya"><i class="bi bi-chevron-right"></i></a>
            </div>
            @if ($canImport)
                <button class="btn btn-soft" id="btnImport" data-year="{{ $year }}"><i class="bi bi-cloud-download me-1"></i>Impor libur nasional {{ $year }}</button>
            @endif
            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
        </x-slot:actions>
    </x-page-header>

    <div @class(['alert-soft mb-3', 'info' => $nationalEnabled, 'warning' => ! $nationalEnabled])>
        <i class="bi bi-{{ $nationalEnabled ? 'check-circle' : 'exclamation-circle' }}"></i>
        <div class="flex-grow-1">
            @if ($nationalEnabled)
                Libur nasional &amp; cuti bersama <strong>berlaku</strong> di proyek ini.
            @else
                Libur nasional &amp; cuti bersama <strong>tidak berlaku</strong> di proyek ini (proyek bekerja {{ count($workingDays) }} hari/minggu). Hanya <em>libur proyek</em> yang dihitung.
            @endif
            <a href="{{ route('settings.project', ['tab' => 'workdays']) }}" class="fw-semibold ms-1">Ubah di pengaturan proyek →</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xxl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Kalender {{ $year }}</h2>
                        <p class="card-subtitle">Arahkan kursor ke tanggal bertanda untuk melihat keterangan</p>
                    </div>
                    <div class="legend">
                        <span><span class="swatch" style="background:#fdeceb;box-shadow:inset 0 0 0 1px #b42323"></span>Nasional</span>
                        <span><span class="swatch" style="background:#fff4dc;box-shadow:inset 0 0 0 1px #a86400"></span>Cuti bersama</span>
                        <span><span class="swatch" style="background:rgba(var(--brand-rgb),.12);box-shadow:inset 0 0 0 1px var(--brand)"></span>Proyek</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2" id="yearCalendar"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xxl-5">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">{{ $holidays->count() }} hari libur</h2>
                        <p class="card-subtitle">Tahun {{ $year }}</p>
                    </div>
                </div>
                <div class="card-body pt-1">
                    @forelse ($holidays as $h)
                        <div class="setting-row py-2">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="text-center flex-shrink-0 rounded-3 soft-{{ $typeTones[$h->type] }}" style="width:3rem;padding:.35rem 0">
                                    <div class="fw-bold lh-1 fs-5">{{ $h->date->format('j') }}</div>
                                    <div class="fs-8 fw-semibold text-uppercase">{{ $h->date->translatedFormat('M') }}</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="setting-title fs-7 text-truncate">{{ $h->name }}</div>
                                    <div class="setting-desc">{{ $h->date->translatedFormat('l') }} · {{ $typeLabels[$h->type] }}</div>
                                </div>
                            </div>
                            @if ($h->project_id || $isSuper)
                                <div class="dropdown">
                                    <button class="btn btn-soft btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Aksi"><i class="bi bi-three-dots"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button class="dropdown-item" data-edit="{{ json_encode(['id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name, 'type' => $h->type]) }}"><i class="bi bi-pencil"></i>Ubah</button>
                                        <button class="dropdown-item text-danger" data-delete="{{ route('holidays.destroy', $h) }}" data-name="{{ $h->name }}"><i class="bi bi-trash"></i>Hapus</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-calendar2-x"></i></div>
                            <h6>Belum ada hari libur {{ $year }}</h6>
                            <p>{{ $canImport ? 'Impor libur nasional atau tambah manual.' : 'Tambahkan secara manual.' }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalHoliday" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formHoliday" method="POST" action="{{ route('holidays.store') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Hari Libur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input name="date" class="form-control datepicker" placeholder="Pilih tanggal">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <input name="name" class="form-control" placeholder="mis. Libur akhir tahun proyek">
                    </div>
                    <div>
                        <label class="form-label">Jenis</label>
                        <select name="type" class="form-select">
                            <option value="project">Libur proyek (hanya {{ $currentProject->code }})</option>
                            @if ($isSuper)
                                <option value="national">Libur nasional (semua proyek)</option>
                                <option value="cuti_bersama">Cuti bersama (semua proyek)</option>
                            @endif
                        </select>
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

@push('scripts')
    <script>
        window.HolidayData = {{ Js::from([
            'year' => $year,
            'workingDays' => $workingDays,
            'holidays' => $holidays->map(fn ($h) => ['date' => $h->date->toDateString(), 'name' => $h->name, 'type' => $h->type]),
            'today' => now($currentProject->timezone)->toDateString(),
        ]) }};
    </script>
@endpush
