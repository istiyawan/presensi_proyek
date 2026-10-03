@extends('layouts.app')

@section('content')
    <x-page-header title="Pengaturan Aplikasi" eyebrow="Branding dan keamanan"
                   subtitle="Berlaku untuk seluruh proyek: web admin, aplikasi mobile, dan laporan." />

    <form id="formApp" method="POST" action="{{ route('settings.app.update') }}" enctype="multipart/form-data" data-ajax-form>
        @csrf
        <input type="hidden" name="remove_logo" value="0">
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-header"><div><h2 class="card-title">Identitas</h2><p class="card-subtitle">Nama &amp; logo yang tampil di aplikasi</p></div></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-4 align-items-center mb-4">
                            <div class="logo-drop" id="logoPreview">
                                @if (! empty($s['logo']))
                                    <img src="{{ route('branding.logo') }}?v={{ md5($s['logo']) }}" alt="Logo">
                                @else
                                    <span>{{ \App\Support\Brand::initials($s['app_name']) }}</span>
                                @endif
                            </div>
                            <div>
                                <div class="fw-semibold text-ink mb-1">Logo</div>
                                <div class="fs-8 text-muted mb-2">PNG/JPG/WebP persegi, maks. 1 MB. Kosong = inisial nama aplikasi.</div>
                                <label class="btn btn-sm btn-soft mb-0"><i class="bi bi-upload me-1"></i>Pilih gambar<input type="file" name="logo" accept="image/*" hidden></label>
                                @if (! empty($s['logo']))
                                    <button type="button" class="btn btn-sm btn-soft text-danger" id="btnRemoveLogo">Hapus logo</button>
                                @endif
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Nama aplikasi</label>
                                <input name="app_name" class="form-control" value="{{ $s['app_name'] }}">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Nama perusahaan / KSO</label>
                                <input name="company_name" class="form-control" value="{{ $s['company_name'] }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h2 class="card-title">Keamanan aplikasi mobile</h2></div></div>
                    <div class="card-body pt-2">
                        <div class="setting-row">
                            <div class="setting-text">
                                <div class="setting-title">Satu akun, satu perangkat</div>
                                <div class="setting-desc">Mencegah titip absen. Admin dapat mereset perangkat di menu Karyawan.</div>
                            </div>
                            <div class="setting-control form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="device_binding" value="1" data-bool @checked($s['device_binding'])>
                            </div>
                        </div>
                        <div class="setting-row">
                            <div class="setting-text">
                                <div class="setting-title">Versi minimum aplikasi</div>
                                <div class="setting-desc">Aplikasi di bawah versi ini diminta memperbarui.</div>
                            </div>
                            <div class="setting-control"><input name="min_app_version" class="form-control form-control-sm text-end" style="width: 110px" value="{{ $s['min_app_version'] }}"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card">
                    <div class="card-header"><div><h2 class="card-title">Warna utama</h2><p class="card-subtitle">Tombol, menu aktif, dan aksen</p></div></div>
                    <div class="card-body">
                        <div class="d-flex gap-2 align-items-center mb-3">
                            <input type="color" class="form-control form-control-color color-input" id="colorPicker" value="{{ $s['primary_color'] }}">
                            <input name="primary_color" class="form-control text-uppercase tabular" value="{{ $s['primary_color'] }}" maxlength="7">
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-4" id="swatches">
                            @foreach (['#1e5aa8', '#0f766e', '#7c3aed', '#be123c', '#c2410c', '#0f172a', '#047857', '#1d4ed8'] as $c)
                                <button type="button" class="btn p-0 rounded-circle border-0" style="width:1.9rem;height:1.9rem;background:{{ $c }}" data-color="{{ $c }}" aria-label="{{ $c }}"></button>
                            @endforeach
                        </div>
                        <div class="form-section-title">Pratinjau</div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-primary btn-sm">Tombol utama</button>
                            <span class="chip soft-brand">Chip</span>
                            <span class="text-brand fw-semibold fs-7">Tautan</span>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent py-3">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2 me-1"></i>Simpan pengaturan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
