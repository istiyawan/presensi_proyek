@extends('layouts.app')

@php
    $types = [
        'individual' => ['icon' => 'person-vcard', 'label' => 'Individual', 'desc' => 'Satu karyawan, format Laporan Absensi Harian, PDF / Excel'],
        'combined' => ['icon' => 'people', 'label' => 'Semua karyawan', 'desc' => 'Laporan tiap karyawan dalam satu berkas, PDF / Excel'],
        'recap' => ['icon' => 'grid-3x3', 'label' => 'Rekap', 'desc' => 'Matriks karyawan × tanggal, PDF / Excel'],
    ];
@endphp

@section('content')
    <x-page-header title="Laporan Absensi" :eyebrow="$currentProject->name"
                   subtitle="Format mengikuti Laporan Absensi Harian proyek. Pilih per periode (Bulan ke-N) atau rentang tanggal bebas." />

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <form class="card" id="formReport" method="POST" action="{{ route('reports.store') }}">
                @csrf
                <div class="card-header"><div><h2 class="card-title">Buat laporan</h2><p class="card-subtitle">Laporan kecil langsung siap; laporan besar diproses di latar belakang</p></div></div>
                <div class="card-body">
                    <div class="form-section">
                        <div class="form-section-title">Jenis laporan</div>
                        <div class="d-grid gap-2" data-error-for="type">
                            @foreach ($types as $key => $t)
                                <div>
                                    <input type="radio" class="btn-check" name="type" value="{{ $key }}" id="type-{{ $key }}" @checked($key === 'combined')>
                                    <label class="report-type" for="type-{{ $key }}">
                                        <span class="report-type-icon"><i class="bi bi-{{ $t['icon'] }}"></i></span>
                                        <span class="min-w-0"><strong>{{ $t['label'] }}</strong><small>{{ $t['desc'] }}</small></span>
                                        <i class="bi bi-check-circle-fill report-type-check"></i>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Periode</div>
                        <ul class="nav nav-pills-soft mb-3" role="tablist">
                            <li class="nav-item"><button type="button" class="nav-link active" data-range="period">Bulan ke-N</button></li>
                            <li class="nav-item"><button type="button" class="nav-link" data-range="custom">Rentang tanggal</button></li>
                        </ul>
                        <input type="hidden" name="range_mode" value="period">
                        <div data-range-pane="period">
                            <select name="period" class="form-select">
                                @foreach ($periods as $p)
                                    <option value="{{ $p['index'] }}" @selected($current && $current['index'] === $p['index'])>
                                        {{ $p['label'] }} · {{ $p['start']->translatedFormat('j M Y') }} – {{ $p['end']->translatedFormat('j M Y') }}{{ $current && $current['index'] === $p['index'] ? ' (berjalan)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div data-range-pane="custom" hidden>
                            <input type="text" class="form-control" id="reportRange" placeholder="Pilih tanggal mulai & selesai">
                            <input type="hidden" name="from"><input type="hidden" name="to">
                            <div class="form-text">Maksimal 93 hari.</div>
                        </div>
                    </div>

                    <div class="form-section" data-for-type="individual" hidden>
                        <div class="form-section-title">Karyawan</div>
                        <div class="select2-wrap">
                            <select name="employee_id" class="form-select select2" data-placeholder="Pilih karyawan">
                                <option value=""></option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e->id }}">{{ $e->display_name }}{{ $e->position ? ' — '.$e->position->name : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-section mb-0">
                        <div class="form-section-title">Opsi</div>
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="format" value="pdf" id="fmt-pdf" checked>
                                <label class="btn btn-soft" for="fmt-pdf"><i class="bi bi-filetype-pdf text-danger me-1"></i>PDF</label>
                                <input type="radio" class="btn-check" name="format" value="xlsx" id="fmt-xlsx">
                                <label class="btn btn-soft" for="fmt-xlsx"><i class="bi bi-filetype-xlsx text-success me-1"></i>Excel</label>
                            </div>
                            <div class="form-text" id="formatHint"></div>
                        </div>
                        <div class="setting-row py-0" data-for-type="individual combined" data-photo-option>
                            <div class="setting-text">
                                <div class="setting-title">Sertakan foto CI / CO</div>
                                <div class="setting-desc">Berlaku untuk PDF &amp; Excel. Tanpa foto, berkas jauh lebih kecil &amp; cepat dibuat.</div>
                            </div>
                            <div class="setting-control form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="photos" value="1" @checked($showPhoto)>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent py-3">
                    <button type="submit" class="btn btn-primary w-100 btn-lg fs-6"><i class="bi bi-file-earmark-arrow-down me-1"></i>Buat laporan</button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div><h2 class="card-title">Riwayat laporan</h2><p class="card-subtitle">Berkas disimpan {{ config('presensi.report_retention_days') }} hari</p></div>
                    <button class="btn btn-soft btn-sm" id="btnRefresh" aria-label="Muat ulang"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div class="card-body pt-1" id="historyList">
                    <div class="text-center py-5"><span class="spinner-border text-secondary"></span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
