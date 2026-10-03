@extends('layouts.app')

@section('content')
    <x-page-header title="Shift & Jam Kerja" :eyebrow="$currentProject->name"
                   subtitle="Atur jam kerja, jendela check-in, dan aturan keterlambatan per shift.">
        <x-slot:actions>
            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah Shift</button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        @foreach ($shifts as $shift)
            <div class="col-12 col-md-6 col-xxl-4">
                <div class="card h-100 {{ $shift->is_active ? '' : 'opacity-75' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @if ($shift->is_default)<span class="chip soft-brand"><i class="bi bi-star-fill"></i>Default</span>@endif
                                    @unless ($shift->is_active)<span class="chip soft-slate">Nonaktif</span>@endunless
                                </div>
                                <h3 class="h6 fw-bold mb-0 text-ink">{{ $shift->name }}</h3>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-soft btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Aksi"><i class="bi bi-three-dots"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @php
                                        $editData = $shift->only(['id', 'name', 'late_enabled', 'late_tolerance_min', 'is_default', 'is_active']) + [
                                            'start_time' => substr($shift->start_time, 0, 5),
                                            'end_time' => substr($shift->end_time, 0, 5),
                                            'checkin_open_time' => $shift->checkin_open_time ? substr($shift->checkin_open_time, 0, 5) : '',
                                            'checkin_close_time' => $shift->checkin_close_time ? substr($shift->checkin_close_time, 0, 5) : '',
                                        ];
                                    @endphp
                                    <button class="dropdown-item" data-edit="{{ json_encode($editData) }}"><i class="bi bi-pencil"></i>Ubah</button>
                                    <button class="dropdown-item text-danger" data-delete="{{ route('shifts.destroy', $shift) }}" data-name="{{ $shift->name }}"><i class="bi bi-trash"></i>Hapus</button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-3" style="background:#f8fafc">
                            <div class="text-center">
                                <div class="fs-8 text-muted fw-semibold">MASUK</div>
                                <div class="fs-4 fw-bold text-ink tabular">{{ substr($shift->start_time, 0, 5) }}</div>
                            </div>
                            <div class="flex-grow-1 position-relative" style="height:2px;background:repeating-linear-gradient(90deg,#cbd5e1 0 6px,transparent 6px 10px)">
                                <i class="bi bi-clock position-absolute top-50 start-50 translate-middle bg-white px-2 text-muted"></i>
                            </div>
                            <div class="text-center">
                                <div class="fs-8 text-muted fw-semibold">PULANG</div>
                                <div class="fs-4 fw-bold text-ink tabular">{{ substr($shift->end_time, 0, 5) }}</div>
                            </div>
                        </div>

                        <dl class="detail-list">
                            <dt>Keterlambatan</dt>
                            <dd>
                                @if ($shift->late_enabled)
                                    <span class="badge-status soft-warning">Aktif</span>
                                    <span class="fs-8 text-muted ms-1">toleransi {{ $shift->late_tolerance_min }} menit</span>
                                @else
                                    <span class="badge-status soft-slate">Tidak dihitung</span>
                                @endif
                            </dd>
                            <dt>Jendela check-in</dt>
                            <dd>
                                @if ($shift->checkin_open_time || $shift->checkin_close_time)
                                    {{ $shift->checkin_open_time ? substr($shift->checkin_open_time, 0, 5) : '00:00' }} – {{ $shift->checkin_close_time ? substr($shift->checkin_close_time, 0, 5) : '23:59' }}
                                @else
                                    <span class="text-muted fw-normal">Kapan saja</span>
                                @endif
                            </dd>
                            <dt>Dipakai</dt>
                            <dd>{{ $usage[$shift->id] ?? 0 }} karyawan</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert-soft info mt-3">
        <i class="bi bi-info-circle"></i>
        <div>Bila <strong>keterlambatan tidak dihitung</strong>, semua karyawan yang check-in berstatus <em>Hadir</em> — sama seperti format laporan acuan. Karyawan tanpa shift khusus memakai shift <strong>default</strong>.</div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalShift" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formShift" method="POST" action="{{ route('shifts.store') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama shift</label>
                        <input name="name" class="form-control" placeholder="mis. Jam Kerja Standard">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Jam masuk</label>
                            <input name="start_time" class="form-control timepicker" placeholder="08:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Jam pulang</label>
                            <input name="end_time" class="form-control timepicker" placeholder="17:00">
                        </div>
                    </div>

                    <div class="form-section mt-4">
                        <div class="form-section-title">Keterlambatan</div>
                        <div class="setting-row pt-0">
                            <div class="setting-text">
                                <div class="setting-title">Hitung status terlambat</div>
                                <div class="setting-desc">Check-in setelah jam masuk + toleransi ditandai <em>Terlambat</em>.</div>
                            </div>
                            <div class="setting-control form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="late_enabled" value="1" data-bool>
                            </div>
                        </div>
                        <div class="row g-3" id="lateFields">
                            <div class="col-6">
                                <label class="form-label">Toleransi</label>
                                <div class="input-group">
                                    <input name="late_tolerance_min" type="number" min="0" max="240" class="form-control" value="0">
                                    <span class="input-group-text">menit</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Jendela check-in (opsional)</div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Dibuka</label>
                                <input name="checkin_open_time" class="form-control timepicker" placeholder="Kapan saja">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Ditutup</label>
                                <input name="checkin_close_time" class="form-control timepicker" placeholder="Kapan saja">
                            </div>
                        </div>
                        <div class="form-text mt-2">Berlaku untuk presensi online. Data offline tetap diterima sesuai waktu pengambilannya.</div>
                    </div>

                    <div class="d-flex gap-4">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_default" value="1" id="swDefault" data-bool>
                            <label class="form-check-label fw-semibold" for="swDefault">Shift default</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="swShiftActive" data-bool checked>
                            <label class="form-check-label fw-semibold" for="swShiftActive">Aktif</label>
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
