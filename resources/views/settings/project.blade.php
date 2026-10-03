@extends('layouts.app')

@php
    $tabs = [
        'general' => ['icon' => 'info-circle', 'label' => 'Informasi proyek', 'desc' => 'Nama, kontrak, zona waktu'],
        'period' => ['icon' => 'calendar-range', 'label' => 'Periode laporan', 'desc' => 'Tanggal cut-off bulanan'],
        'workdays' => ['icon' => 'calendar-week', 'label' => 'Hari kerja & libur', 'desc' => 'Hari kerja, libur nasional'],
        'location' => ['icon' => 'geo-alt', 'label' => 'Lokasi & presensi', 'desc' => 'Luar lokasi, GPS, Fake GPS'],
        'offline' => ['icon' => 'wifi-off', 'label' => 'Mode offline', 'desc' => 'Presensi tanpa sinyal'],
        'report' => ['icon' => 'file-earmark-text', 'label' => 'Laporan', 'desc' => 'Kota, urutan, foto'],
        'signatories' => ['icon' => 'pen', 'label' => 'Penandatangan', 'desc' => 'Kolom tanda tangan laporan'],
    ];
    $days = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $switch = function (string $name, bool $checked) {
        return '<div class="setting-control form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="'.$name.'" value="1" data-bool '.($checked ? 'checked' : '').'></div>';
    };
@endphp

@section('content')
    <x-page-header title="Pengaturan Proyek" :eyebrow="$project->code.' · '.$project->name"
                   subtitle="Perubahan berlaku langsung untuk aplikasi mobile & laporan proyek ini." />

    <div class="row g-3">
        <div class="col-12 col-lg-4 col-xl-3">
            <div class="card">
                <div class="card-body p-2">
                    <div class="nav flex-column settings-nav" role="tablist">
                        @foreach ($tabs as $key => $t)
                            <button class="nav-link {{ $tab === $key ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tab-{{ $key }}" type="button" role="tab" data-tab="{{ $key }}">
                                <i class="bi bi-{{ $t['icon'] }}"></i>
                                <span class="min-w-0"><strong>{{ $t['label'] }}</strong><small>{{ $t['desc'] }}</small></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8 col-xl-9">
            <div class="tab-content">
                {{-- Informasi proyek --}}
                <div class="tab-pane fade {{ $tab === 'general' ? 'show active' : '' }}" id="tab-general" role="tabpanel">
                    <form class="card" method="POST" action="{{ route('settings.project.update', 'general') }}" data-ajax-form>
                        @csrf @method('PUT')
                        <div class="card-header"><div><h2 class="card-title">Informasi proyek</h2><p class="card-subtitle">Tampil di aplikasi mobile dan laporan</p></div></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Kode</label>
                                    <input name="code" class="form-control text-uppercase" value="{{ $project->code }}">
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">Nama proyek</label>
                                    <input name="name" class="form-control" value="{{ $project->name }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pemberi kerja / klien</label>
                                    <input name="client_name" class="form-control" value="{{ $project->client_name }}" placeholder="opsional">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kota</label>
                                    <input name="city" class="form-control" value="{{ $project->city }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="address" class="form-control" rows="2">{{ $project->address }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mulai kontrak</label>
                                    <input name="contract_start_date" class="form-control datepicker" value="{{ $project->contract_start_date?->toDateString() }}">
                                    <div class="form-text">Dasar penomoran "Bulan ke-N".</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Akhir kontrak</label>
                                    <input name="contract_end_date" class="form-control datepicker" value="{{ $project->contract_end_date?->toDateString() }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Zona waktu</label>
                                    <select name="timezone" class="form-select">
                                        @foreach ($timezones as $k => $v)
                                            <option value="{{ $k }}" @selected($project->timezone === $k)>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="setting-row mt-3 pb-0">
                                <div class="setting-text">
                                    <div class="setting-title">Proyek aktif</div>
                                    <div class="setting-desc">Proyek nonaktif tidak bisa dipakai presensi.</div>
                                </div>
                                {!! $switch('is_active', $project->is_active) !!}
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </div>

                {{-- Periode --}}
                <div class="tab-pane fade {{ $tab === 'period' ? 'show active' : '' }}" id="tab-period" role="tabpanel">
                    <form class="card" id="formPeriod" method="POST" action="{{ route('settings.project.update', 'period') }}" data-ajax-form>
                        @csrf @method('PUT')
                        <div class="card-header"><div><h2 class="card-title">Periode laporan</h2><p class="card-subtitle">Rentang satu "bulan" laporan absensi</p></div></div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-sm-4">
                                    <label class="form-label">Mulai tanggal</label>
                                    <input name="period_start_day" type="number" min="1" max="31" class="form-control" value="{{ $s['period_start_day'] }}">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label">Sampai tanggal</label>
                                    <input name="period_end_day" type="number" min="1" max="31" class="form-control" value="{{ $s['period_end_day'] }}">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label">Tanggal akhir jatuh di</label>
                                    <select name="period_end_next_month" class="form-select">
                                        <option value="1" @selected($s['period_end_next_month'])>Bulan berikutnya</option>
                                        <option value="0" @selected(! $s['period_end_next_month'])>Bulan yang sama</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="form-section-title">Pratinjau</div>
                                <div class="row g-2" id="periodPreview" data-contract="{{ $project->contract_start_date?->toDateString() ?? $project->created_at->toDateString() }}"></div>
                                <div class="form-text mt-2" id="periodOverlap"></div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </div>

                {{-- Hari kerja --}}
                <div class="tab-pane fade {{ $tab === 'workdays' ? 'show active' : '' }}" id="tab-workdays" role="tabpanel">
                    <form class="card" method="POST" action="{{ route('settings.project.update', 'workdays') }}" data-ajax-form>
                        @csrf @method('PUT')
                        <div class="card-header"><div><h2 class="card-title">Hari kerja &amp; libur</h2><p class="card-subtitle">Menentukan kapan karyawan dihitung <em>alpha</em> bila tidak presensi</p></div></div>
                        <div class="card-body">
                            <label class="form-label">Hari kerja</label>
                            <div class="day-picker mb-2" data-error-for="working_days">
                                @foreach ($days as $num => $label)
                                    <input type="checkbox" name="working_days[]" value="{{ $num }}" id="wd{{ $num }}" @checked(in_array($num, $s['working_days']))>
                                    <label for="wd{{ $num }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            <div class="form-text mb-3">Hari di luar hari kerja tercatat <em>libur</em>; yang tetap masuk dicatat hadir di hari libur.</div>

                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Gunakan libur nasional &amp; cuti bersama</div>
                                    <div class="setting-desc">Tanggal merah dari kalender <a href="{{ route('holidays.index') }}">Hari Libur</a> tidak dihitung alpha.</div>
                                </div>
                                {!! $switch('use_national_holidays', $s['use_national_holidays']) !!}
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </div>

                {{-- Lokasi --}}
                <div class="tab-pane fade {{ $tab === 'location' ? 'show active' : '' }}" id="tab-location" role="tabpanel">
                    <form class="card" id="formLocationRules" method="POST" action="{{ route('settings.project.update', 'location') }}" data-ajax-form>
                        @csrf @method('PUT')
                        <div class="card-header">
                            <div><h2 class="card-title">Lokasi &amp; presensi</h2><p class="card-subtitle">Aturan validasi GPS di aplikasi mobile</p></div>
                            <a href="{{ route('locations.index') }}" class="btn btn-sm btn-brand-soft"><i class="bi bi-geo-alt me-1"></i>Titik lokasi</a>
                        </div>
                        <div class="card-body pt-2">
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Izinkan presensi di luar lokasi</div>
                                    <div class="setting-desc">Untuk pekerjaan lapangan (survei, rapat). Presensi ditandai <em>Luar Lokasi</em>.</div>
                                </div>
                                {!! $switch('allow_offsite', $s['allow_offsite']) !!}
                            </div>
                            <div class="offsite-options ps-md-4 border-start ms-md-2" style="border-color: var(--line) !important">
                                <div class="setting-row">
                                    <div class="setting-text">
                                        <div class="setting-title">Berlaku untuk</div>
                                        <div class="setting-desc">"Karyawan tertentu" diatur per karyawan di menu Karyawan.</div>
                                    </div>
                                    <div class="setting-control">
                                        <select name="offsite_scope" class="form-select form-select-sm">
                                            <option value="selected" @selected($s['offsite_scope'] === 'selected')>Karyawan tertentu</option>
                                            <option value="all" @selected($s['offsite_scope'] === 'all')>Semua karyawan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="setting-row">
                                    <div class="setting-text">
                                        <div class="setting-title">Wajib isi keterangan</div>
                                        <div class="setting-desc">Karyawan menuliskan kegiatan di luar lokasi.</div>
                                    </div>
                                    {!! $switch('offsite_requires_note', $s['offsite_requires_note']) !!}
                                </div>
                                <div class="setting-row">
                                    <div class="setting-text">
                                        <div class="setting-title">Perlu persetujuan Team Leader</div>
                                        <div class="setting-desc">Bila mati, presensi luar lokasi langsung tercatat dan hanya ditandai.</div>
                                    </div>
                                    {!! $switch('offsite_requires_approval', $s['offsite_requires_approval']) !!}
                                </div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Batas akurasi GPS</div>
                                    <div class="setting-desc">Presensi online ditolak bila akurasi lebih buruk dari nilai ini.</div>
                                </div>
                                <div class="setting-control">
                                    <div class="input-group input-group-sm" style="width: 130px">
                                        <input name="max_gps_accuracy_m" type="number" min="5" max="500" class="form-control text-end" value="{{ $s['max_gps_accuracy_m'] }}">
                                        <span class="input-group-text">meter</span>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Tolak lokasi palsu (Fake GPS)</div>
                                    <div class="setting-desc">Sangat disarankan tetap aktif.</div>
                                </div>
                                {!! $switch('block_mock_location', $s['block_mock_location']) !!}
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Batas jam kerja (jendela check-out)</div>
                                    <div class="setting-desc">Check-out lewat tengah malam tetap masuk ke tanggal check-in (mis. masuk 2 Okt 08:00, pulang 3 Okt 01:00 = 17 jam di tanggal 2 Okt). Lewat batas ini presensi ditandai <em>lupa check-out</em>.</div>
                                </div>
                                <div class="setting-control">
                                    <div class="input-group input-group-sm" style="width: 120px">
                                        <input name="max_work_hours" type="number" min="12" max="36" class="form-control text-end" value="{{ $s['max_work_hours'] }}">
                                        <span class="input-group-text">jam</span>
                                    </div>
                                </div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Check-out wajib di lokasi</div>
                                    <div class="setting-desc">Bila mati, check-out boleh dari mana saja (lokasi tetap dicatat).</div>
                                </div>
                                {!! $switch('require_checkout_in_location', $s['require_checkout_in_location']) !!}
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </div>

                {{-- Offline --}}
                <div class="tab-pane fade {{ $tab === 'offline' ? 'show active' : '' }}" id="tab-offline" role="tabpanel">
                    <form class="card" method="POST" action="{{ route('settings.project.update', 'offline') }}" data-ajax-form>
                        @csrf @method('PUT')
                        <div class="card-header"><div><h2 class="card-title">Mode offline</h2><p class="card-subtitle">Presensi tersimpan di HP saat tidak ada sinyal, lalu terkirim otomatis</p></div></div>
                        <div class="card-body pt-2">
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Izinkan presensi offline</div>
                                    <div class="setting-desc">Waktu presensi memakai jam terpercaya yang disinkronkan dengan server, bukan jam HP.</div>
                                </div>
                                {!! $switch('allow_offline', $s['allow_offline']) !!}
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Batas umur data offline</div>
                                    <div class="setting-desc">Data yang terkirim lebih lambat dari batas ini ditolak dan perlu diinput manual oleh admin.</div>
                                </div>
                                <div class="setting-control">
                                    <div class="input-group input-group-sm" style="width: 120px">
                                        <input name="offline_max_hours" type="number" min="1" max="720" class="form-control text-end" value="{{ $s['offline_max_hours'] }}">
                                        <span class="input-group-text">jam</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </div>

                {{-- Laporan --}}
                <div class="tab-pane fade {{ $tab === 'report' ? 'show active' : '' }}" id="tab-report" role="tabpanel">
                    <form class="card" method="POST" action="{{ route('settings.project.update', 'report') }}" data-ajax-form>
                        @csrf @method('PUT')
                        <div class="card-header"><div><h2 class="card-title">Laporan</h2><p class="card-subtitle">Format Laporan Absensi Harian (PDF)</p></div></div>
                        <div class="card-body pt-2">
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Kota pada tanda tangan</div>
                                    <div class="setting-desc">Contoh: "<em>Surabaya</em>, 26 Maret 2026"</div>
                                </div>
                                <div class="setting-control"><input name="report_city" class="form-control form-control-sm" style="width: 180px" value="{{ $s['report_city'] ?? $project->city }}"></div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Urutan tanggal</div>
                                    <div class="setting-desc">Laporan acuan memakai urutan terbaru di atas.</div>
                                </div>
                                <div class="setting-control">
                                    <select name="report_sort" class="form-select form-select-sm">
                                        <option value="desc" @selected($s['report_sort'] === 'desc')>Terbaru di atas</option>
                                        <option value="asc" @selected($s['report_sort'] === 'asc')>Terlama di atas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-text">
                                    <div class="setting-title">Tampilkan foto CI / CO</div>
                                    <div class="setting-desc">Mematikan foto membuat PDF jauh lebih kecil.</div>
                                </div>
                                {!! $switch('report_show_photo', $s['report_show_photo']) !!}
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </div>

                {{-- Penandatangan --}}
                <div class="tab-pane fade {{ $tab === 'signatories' ? 'show active' : '' }}" id="tab-signatories" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <div><h2 class="card-title">Penandatangan laporan</h2><p class="card-subtitle">Maksimal 4 kolom tanda tangan aktif, diurutkan dari kiri</p></div>
                            <button class="btn btn-sm btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                        </div>
                        <div class="card-body">
                            @if ($signatories->isEmpty())
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="bi bi-pen"></i></div>
                                    <h6>Belum ada penandatangan</h6>
                                    <p>Laporan akan dicetak tanpa kolom tanda tangan.</p>
                                </div>
                            @else
                                <div class="row g-3">
                                    @foreach ($signatories as $sig)
                                        <div class="col-md-6 col-xxl-3">
                                            <div class="border rounded-4 p-3 h-100 d-flex flex-column text-center {{ $sig->is_active ? '' : 'opacity-50' }}" style="border-color: var(--line) !important">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="chip">#{{ $sig->sort_order }}</span>
                                                    <div class="dropdown">
                                                        <button class="btn btn-soft btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Aksi"><i class="bi bi-three-dots"></i></button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <button class="dropdown-item" data-edit="{{ json_encode($sig->only(['id', 'label', 'employee_id', 'name', 'title', 'organization', 'sort_order', 'is_active'])) }}"><i class="bi bi-pencil"></i>Ubah</button>
                                                            <button class="dropdown-item text-danger" data-delete="{{ route('signatories.destroy', $sig) }}" data-name="{{ $sig->name }}"><i class="bi bi-trash"></i>Hapus</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="fs-7 text-muted">{{ $sig->label }}</div>
                                                <div class="fs-8 text-muted mt-1" style="white-space: pre-line">{{ $sig->organization }}</div>
                                                <div class="flex-grow-1" style="min-height: 3.5rem"></div>
                                                <div class="fw-bold text-ink text-decoration-underline">{{ $sig->name }}</div>
                                                <div class="fs-7 text-muted">{{ $sig->title }}</div>
                                                @unless ($sig->is_active)<div class="mt-2"><span class="chip soft-slate">Nonaktif</span></div>@endunless
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalSignatory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formSignatory" method="POST" action="{{ route('signatories.store') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Penandatangan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Label</label>
                            <input name="label" class="form-control" list="labelOptions" placeholder="Dibuat">
                            <datalist id="labelOptions"><option>Dibuat</option><option>Diperiksa</option><option>Mengetahui</option><option>Disetujui</option></datalist>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Urutan</label>
                            <input name="sort_order" type="number" min="1" max="10" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ambil dari karyawan (opsional)</label>
                            <select name="employee_id" class="form-select select2" data-placeholder="Pilih karyawan atau isi manual" data-allow-clear="true">
                                <option value=""></option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e['id'] }}" data-name="{{ $e['name'] }}" data-title="{{ $e['position'] }}">{{ $e['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Nama (dengan gelar)</label>
                            <input name="name" class="form-control">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Jabatan</label>
                            <input name="title" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Organisasi / perusahaan</label>
                            <textarea name="organization" class="form-control" rows="2" placeholder="mis. PT. A - PT. B (KSO)"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="swSigActive" data-bool checked>
                                <label class="form-check-label fw-semibold" for="swSigActive">Tampilkan di laporan</label>
                            </div>
                        </div>
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
