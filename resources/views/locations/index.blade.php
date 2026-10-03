@extends('layouts.app')

@section('content')
    <x-page-header title="Titik Lokasi" :eyebrow="$currentProject->name"
                   subtitle="Presensi <em>Dalam Lokasi</em> hanya sah di dalam radius salah satu titik aktif.">
        <x-slot:actions>
            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah Titik</button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">{{ $locations->count() }} titik lokasi</h2>
                        <p class="card-subtitle">Klik untuk melihat di peta</p>
                    </div>
                </div>
                <div class="card-body pt-2">
                    @forelse ($locations as $loc)
                        <div class="setting-row align-items-start location-item" role="button" data-focus="{{ $loc->id }}">
                            <div class="d-flex gap-3 min-w-0">
                                <span class="stat-icon {{ $loc->is_active ? 'soft-brand' : 'soft-slate' }} m-0 flex-shrink-0" style="width:2.4rem;height:2.4rem;border-radius:.7rem;display:grid;place-items:center"><i class="bi bi-geo-alt-fill"></i></span>
                                <div class="min-w-0">
                                    <div class="setting-title text-truncate">{{ $loc->name }}</div>
                                    <div class="setting-desc tabular">{{ number_format($loc->latitude, 6) }}, {{ number_format($loc->longitude, 6) }}</div>
                                    <div class="d-flex gap-1 mt-2">
                                        <span class="chip soft-brand">Radius {{ $loc->radius_m }} m</span>
                                        @unless ($loc->is_active)<span class="chip soft-slate">Nonaktif</span>@endunless
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-soft btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Aksi"><i class="bi bi-three-dots"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <button class="dropdown-item" data-edit="{{ json_encode($loc->only(['id', 'name', 'latitude', 'longitude', 'radius_m', 'is_active'])) }}"><i class="bi bi-pencil"></i>Ubah</button>
                                    <button class="dropdown-item text-danger" data-delete="{{ route('locations.destroy', $loc) }}" data-name="{{ $loc->name }}"><i class="bi bi-trash"></i>Hapus</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-geo-alt"></i></div>
                            <h6>Belum ada titik lokasi</h6>
                            <p>Karyawan belum bisa presensi sebelum titik lokasi dibuat.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-body">
                    <div id="locationsMap" class="map-box" style="height: 520px"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalLocation" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form class="modal-content" id="formLocation" method="POST" action="{{ route('locations.store') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Titik Lokasi</h5>
                        <p class="modal-subtitle">Klik peta atau seret penanda untuk memilih titik. Atur radius dengan slider.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div id="pickerMap" class="map-box" style="height: 420px"></div>
                        </div>
                        <div class="col-lg-5">
                            <div class="mb-3">
                                <label class="form-label">Nama lokasi</label>
                                <input name="name" class="form-control" placeholder="mis. Kantor Proyek / Bendung">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tempel koordinat</label>
                                <div class="input-group">
                                    <input id="pasteCoord" class="form-control" placeholder="-7.3154, 112.6855">
                                    <button class="btn btn-soft" type="button" id="btnPaste">Terapkan</button>
                                </div>
                                <div class="form-text">Salin dari Google Maps (klik kanan → koordinat).</div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Latitude</label>
                                    <input name="latitude" class="form-control tabular" inputmode="decimal">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Longitude</label>
                                    <input name="longitude" class="form-control tabular" inputmode="decimal">
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-end">
                                    <label class="form-label mb-0">Radius</label>
                                    <div class="input-group input-group-sm" style="width: 120px">
                                        <input name="radius_m" type="number" min="10" max="5000" class="form-control text-end" value="100">
                                        <span class="input-group-text">m</span>
                                    </div>
                                </div>
                                <input type="range" class="form-range mt-2" id="radiusRange" min="10" max="1000" step="10" value="100">
                                <div class="d-flex justify-content-between fs-8 text-muted"><span>10 m</span><span>1 km</span></div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="swLocActive" data-bool checked>
                                <label class="form-check-label fw-semibold" for="swLocActive">Aktif</label>
                            </div>
                            <div class="alert-soft warning mt-3">
                                <i class="bi bi-lightbulb"></i>
                                <div>Akurasi GPS HP biasanya 5–30 m. Radius terlalu kecil (&lt; 50 m) bisa membuat karyawan sulit presensi.</div>
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

@push('scripts')
    <script>window.Locations = {{ Js::from($locations) }};</script>
@endpush
