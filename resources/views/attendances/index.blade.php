@extends('layouts.app')

@section('content')
    <x-page-header title="Monitoring Presensi" :eyebrow="$currentProject->name"
                   subtitle="Pantau check-in &amp; check-out, lihat foto dan lokasi, serta koreksi data bila diperlukan.">
        <x-slot:actions>
            @if ($canManageProject)
                <button class="btn btn-primary" id="btnManual"><i class="bi bi-plus-lg me-1"></i>Presensi Manual</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-3">
        <div class="card-body">
            <form class="filter-bar filter-grid" id="filterForm" onsubmit="return false">
                <div>
                    <label class="form-label">Periode</label>
                    <select class="form-select" id="periodSelect">
                        <option value="">Rentang bebas</option>
                        @foreach ($periods as $p)
                            <option value="{{ $p['start']->toDateString() }}|{{ $p['end']->toDateString() }}">
                                {{ $p['label'] }} · {{ $p['start']->translatedFormat('j M') }} – {{ $p['end']->translatedFormat('j M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Tanggal</label>
                    <input type="text" class="form-control" id="rangeInput" data-from="{{ $from }}" data-to="{{ $to }}" placeholder="Pilih rentang">
                </div>
                <div>
                    <label class="form-label">Karyawan</label>
                    <select class="form-select select2" name="employee_id" data-placeholder="Semua karyawan" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($employees as $e)
                            <option value="{{ $e['id'] }}">{{ $e['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua</option>
                        @foreach (['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'cuti' => 'Cuti', 'alpha' => 'Alpha', 'libur' => 'Libur'] as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Lokasi</label>
                    <select class="form-select" name="mode">
                        <option value="">Semua</option>
                        <option value="onsite">Dalam lokasi</option>
                        <option value="offsite">Luar lokasi</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Penanda</label>
                    <select class="form-select" name="flag">
                        <option value="">Semua</option>
                        @foreach (['missing_checkout' => 'Lupa check-out', 'backdated' => 'Tanggal mundur', 'offline' => 'Dikirim offline', 'manual' => 'Koreksi manual', 'time_suspicious' => 'Waktu meragukan', 'low_accuracy' => 'GPS kurang akurat', 'holiday' => 'Masuk di hari libur'] as $k => $v)
                            <option value="{{ $k }}" @selected($flag === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn btn-soft" id="btnReset" data-bs-toggle="tooltip" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover w-100" id="tableAttendances">
                <thead>
                <tr>
                    {{-- Kolom sama dengan laporan PDF (reports/pdf/individual) --}}
                    <th>Tanggal</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Shift</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Koordinat Check-in</th>
                    <th>Koordinat Check-out</th>
                    <th>Status</th>
                    <th>Durasi</th>
                    <th>Foto</th>
                    <th class="text-end"></th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    {{-- Panel detail --}}
    <div class="offcanvas offcanvas-end offcanvas-detail" tabindex="-1" id="detailPanel" aria-labelledby="detailTitle">
        <div class="offcanvas-header">
            <div class="person min-w-0" id="detailHead"></div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body" id="detailBody">
            <div class="text-center py-5"><span class="spinner-border text-secondary"></span></div>
        </div>
    </div>

    {{-- Koreksi / presensi manual --}}
    <div class="modal fade" id="modalCorrection" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formCorrection" method="POST" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Koreksi Presensi</h5>
                        <p class="modal-subtitle" id="correctionSubtitle"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 manual-only">
                        <div class="col-12">
                            <label class="form-label">Karyawan</label>
                            <div class="select2-wrap">
                                <select name="employee_id" class="form-select select2" data-placeholder="Pilih karyawan">
                                    <option value=""></option>
                                    @foreach ($employees as $e)
                                        <option value="{{ $e['id'] }}">{{ $e['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-12">
                            <label class="form-label">Tanggal</label>
                            <input name="work_date" class="form-control datepicker">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach (['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'cuti' => 'Cuti', 'alpha' => 'Alpha', 'libur' => 'Libur'] as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 time-field">
                            <label class="form-label">Jam check-in</label>
                            <input name="check_in_time" class="form-control timepicker" placeholder="--:--">
                        </div>
                        <div class="col-6 time-field">
                            <label class="form-label">Jam check-out</label>
                            <input name="check_out_time" class="form-control timepicker" placeholder="--:--">
                        </div>
                        <div class="col-12 time-field">
                            <div class="form-text mt-0"><i class="bi bi-moon-stars me-1"></i>Jam check-out lebih kecil dari check-in (mis. 08:00 → 01:00) dianggap pulang keesokan harinya.</div>
                        </div>
                        @foreach (['check_in' => 'check-in', 'check_out' => 'check-out'] as $p => $label)
                            <div class="col-12 time-field">
                                <label class="form-label">Koordinat {{ $label }} <span class="text-muted fw-normal">(opsional)</span></label>
                                <div class="row g-2">
                                    <div class="col-6"><input name="{{ $p }}_lat" class="form-control tabular" inputmode="decimal" placeholder="Latitude, mis. -6.200000"></div>
                                    <div class="col-6"><input name="{{ $p }}_lng" class="form-control tabular" inputmode="decimal" placeholder="Longitude, mis. 106.816666"></div>
                                </div>
                            </div>
                            <div class="col-12 time-field">
                                <label class="form-label">Foto {{ $label }} <span class="text-muted fw-normal">(opsional)</span></label>
                                <input type="file" name="{{ $p }}_photo" class="form-control" accept="image/*">
                                <div class="form-text photo-current" data-side="{{ $p }}"></div>
                            </div>
                        @endforeach
                        <div class="col-12 time-field">
                            <div class="form-text mt-0"><i class="bi bi-geo-alt me-1"></i>Jarak &amp; status dalam/luar lokasi dihitung ulang dari koordinat. Foto baru menggantikan foto lama.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alasan <span class="text-danger correction-only" style="display:none">*</span><span class="text-muted fw-normal manual-only">(opsional)</span></label>
                            <textarea name="note" class="form-control" rows="2" placeholder="mis. HP rusak, presensi dicatat manual oleh TL"></textarea>
                            <div class="form-text">Perubahan tercatat di audit log beserta nama Anda.</div>
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
