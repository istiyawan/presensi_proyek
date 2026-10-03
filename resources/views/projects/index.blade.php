@extends('layouts.app')

@section('content')
    <x-page-header :title="$title" :eyebrow="$appSettings['company_name'] ?? null"
                   :subtitle="$multiProject
                       ? 'Semua proyek dalam aplikasi ini. Setiap proyek punya lokasi, shift, karyawan, dan pengaturan sendiri.'
                       : 'Aplikasi ini dipasang untuk 1 proyek. Proyek lain menggunakan instalasi (server & aplikasi) tersendiri.'">
        @if ($canCreate)
            <x-slot:actions>
                <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Proyek Baru</button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="row g-3">
        @forelse ($projects as $p)
            <div class="col-12 col-md-6 col-xxl-4">
                <div @class(['card project-card h-100', 'is-inactive' => ! $p->is_active, 'is-current' => $currentProject?->id === $p->id])>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex gap-1 flex-wrap">
                                <span class="chip soft-brand">{{ $p->code }}</span>
                                <span class="chip">{{ \App\Support\Brand::timezoneLabel($p->timezone) }}</span>
                                @unless ($p->is_active)<span class="chip soft-slate">Nonaktif</span>@endunless
                                @if ($multiProject && $currentProject?->id === $p->id)<span class="chip soft-success"><i class="bi bi-check2"></i>Sedang dibuka</span>@endif
                            </div>
                            <button class="btn btn-soft btn-icon btn-sm" aria-label="Ubah"
                                    data-edit="{{ json_encode($p->only(['id', 'code', 'name', 'client_name', 'city', 'address', 'timezone', 'is_active']) + [
                                        'contract_start_date' => $p->contract_start_date?->toDateString(),
                                        'contract_end_date' => $p->contract_end_date?->toDateString(),
                                    ]) }}"><i class="bi bi-pencil"></i></button>
                        </div>
                        <h3 class="h6 fw-bold text-ink mb-1">{{ $p->name }}</h3>
                        <p class="fs-7 text-muted mb-3">
                            <i class="bi bi-geo-alt me-1"></i>{{ $p->city ?: 'Kota belum diisi' }}
                            @if ($p->contract_start_date)
                                · Kontrak {{ $p->contract_start_date->translatedFormat('M Y') }}{{ $p->contract_end_date ? ' – '.$p->contract_end_date->translatedFormat('M Y') : '' }}
                            @endif
                        </p>
                        <div class="project-meta mb-3">
                            <div><strong>{{ $p->active_employees_count }}</strong><small>Karyawan</small></div>
                            <div><strong>{{ $p->locations_count }}</strong><small>Titik lokasi</small></div>
                            <div><strong>{{ $p->shifts_count }}</strong><small>Shift</small></div>
                        </div>
                        @if ($multiProject)
                            <form method="POST" action="{{ route('projects.switch', $p) }}" class="mt-auto">
                                @csrf
                                <button class="btn {{ $currentProject?->id === $p->id ? 'btn-soft' : 'btn-brand-soft' }} w-100" type="submit">
                                    {{ $currentProject?->id === $p->id ? 'Ke dashboard' : 'Buka proyek' }}<i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </form>
                        @else
                            <a href="{{ route('dashboard') }}" class="btn btn-soft w-100 mt-auto">Ke dashboard<i class="bi bi-arrow-right ms-1"></i></a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body"><div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-buildings"></i></div>
                    <h6>Belum ada proyek</h6><p>Klik "Proyek Baru" untuk mengisi data proyek.</p>
                </div></div></div>
            </div>
        @endforelse
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalProject" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="formProject" method="POST" action="{{ route('projects.store') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Proyek</h5>
                        <p class="modal-subtitle">Shift "Jam Kerja Standard" dibuat otomatis untuk proyek baru.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Kode</label>
                            <input name="code" class="form-control text-uppercase" placeholder="KDR">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Nama proyek</label>
                            <input name="name" class="form-control" placeholder="Nama resmi proyek">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pemberi kerja / klien</label>
                            <input name="client_name" class="form-control" placeholder="opsional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kota</label>
                            <input name="city" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mulai kontrak</label>
                            <input name="contract_start_date" class="form-control datepicker">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Akhir kontrak</label>
                            <input name="contract_end_date" class="form-control datepicker">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Zona waktu</label>
                            <select name="timezone" class="form-select">
                                @foreach ($timezones as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="swProjectActive" data-bool checked>
                                <label class="form-check-label fw-semibold" for="swProjectActive">Proyek aktif</label>
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
