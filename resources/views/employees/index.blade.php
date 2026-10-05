@extends('layouts.app')

@section('content')
    <x-page-header title="Karyawan" :eyebrow="$currentProject->name"
                   subtitle="Kelola data karyawan, akun login aplikasi mobile, dan penugasan di proyek ini.">
        <x-slot:actions>
            <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#modalAttach"><i class="bi bi-person-plus me-1"></i>Dari proyek lain</button>
            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah Karyawan</button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card label="Karyawan aktif" :value="$stats['active']" icon="people" tone="brand" foot="Penugasan berjalan" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Team Leader" :value="$stats['leaders']" icon="star" tone="warning" foot="Bisa approval &amp; pantau tim" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Perangkat terdaftar" :value="$stats['devices']" icon="phone" tone="success" :foot="($stats['active'] - $stats['devices']).' belum login aplikasi'" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Penugasan berakhir" :value="$stats['ended']" icon="person-x" tone="slate" foot="Riwayat tetap tersimpan" /></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Daftar karyawan</h2>
                <p class="card-subtitle">Klik nama untuk mengubah data</p>
            </div>
            <ul class="nav nav-pills-soft" id="statusFilter">
                <li class="nav-item"><a class="nav-link active" href="#" data-status="active">Aktif</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-status="ended">Berakhir / nonaktif</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-status="all">Semua</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-status="archived"><i class="bi bi-archive me-1"></i>Arsip @if ($stats['archived'])<span class="ms-1">({{ $stats['archived'] }})</span>@endif</a></li>
            </ul>
        </div>
        <div class="card-body">
            <table class="table table-hover w-100" id="tableEmployees">
                <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Jabatan</th>
                    <th>Shift</th>
                    <th>Penugasan</th>
                    <th>Akses</th>
                    <th>Perangkat</th>
                    <th>Status</th>
                    <th class="text-end no-sort"></th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    {{-- Tambah / ubah karyawan --}}
    <div class="modal fade" id="modalEmployee" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" id="formEmployee" method="POST" action="{{ route('employees.store') }}" data-ajax-form autocomplete="off">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" data-title-create="Tambah Karyawan" data-title-edit="Ubah Data Karyawan">Tambah Karyawan</h5>
                        <p class="modal-subtitle">Akun login dipakai karyawan di aplikasi mobile.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-section">
                        <div class="form-section-title">Data karyawan</div>
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Gelar depan</label>
                                <input name="title_prefix" class="form-control" placeholder="Dr.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama lengkap <span class="text-danger">*</span></label>
                                <input name="full_name" class="form-control" placeholder="Nama tanpa gelar">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gelar belakang</label>
                                <input name="title_suffix" class="form-control" placeholder="S.T., M.T.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jabatan</label>
                                <div class="select2-wrap">
                                    <select name="position_id" class="form-select select2-tags" data-placeholder="Pilih atau ketik jabatan baru">
                                        <option value=""></option>
                                        @foreach ($positions as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">NIK / No. pegawai</label>
                                <input name="nik" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">No. HP</label>
                                <input name="phone" class="form-control" placeholder="08…">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Akun aplikasi</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input name="username" class="form-control" placeholder="nama.belakang">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input name="email" type="email" class="form-control" placeholder="opsional">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Password <span class="text-danger create-only">*</span></label>
                                <div class="input-group">
                                    <input name="password" type="text" class="form-control" placeholder="min. 8 karakter">
                                    <button class="btn btn-soft" type="button" id="btnGenPassword" data-bs-toggle="tooltip" title="Buat password acak"><i class="bi bi-magic"></i></button>
                                </div>
                                <div class="form-text edit-only">Kosongkan bila tidak diubah.</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Penugasan di {{ $currentProject->code }}</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Shift</label>
                                <select name="shift_id" class="form-select">
                                    @foreach ($shifts as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }} ({{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mulai bertugas <span class="text-danger">*</span></label>
                                <input name="start_date" class="form-control datepicker" placeholder="Pilih tanggal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Selesai bertugas</label>
                                <input name="end_date" class="form-control datepicker" placeholder="Masih bertugas">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Presensi luar lokasi</label>
                                <select name="allow_offsite" class="form-select">
                                    <option value="">Ikuti pengaturan proyek</option>
                                    <option value="1">Diizinkan</option>
                                    <option value="0">Tidak diizinkan</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex flex-column justify-content-end gap-2">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_team_leader" value="1" id="swLeader" data-bool>
                                    <label class="form-check-label fw-semibold" for="swLeader">Team Leader proyek</label>
                                </div>
                                <div class="form-check form-switch m-0 edit-only">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="swActive" data-bool checked>
                                    <label class="form-check-label fw-semibold" for="swActive">Akun aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Tambahkan karyawan dari proyek lain --}}
    <div class="modal fade" id="modalAttach" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formAttach" method="POST" action="{{ route('employees.attach') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Tambah dari Proyek Lain</h5>
                        <p class="modal-subtitle">Karyawan memakai akun yang sama, tanpa membuat akun baru.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Karyawan</label>
                        <div class="select2-wrap">
                            <select name="employee_id" id="selectExisting" class="form-select" data-placeholder="Ketik nama karyawan…"></select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Shift</label>
                            <select name="shift_id" class="form-select">
                                @foreach ($shifts as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Mulai bertugas</label>
                            <input name="start_date" class="form-control datepicker" value="{{ now($currentProject->timezone)->toDateString() }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambahkan</button>
                </div>
            </form>
        </div>
    </div>
@endpush
