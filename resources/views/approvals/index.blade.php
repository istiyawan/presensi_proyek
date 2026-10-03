@extends('layouts.app')

@section('content')
    <x-page-header title="Persetujuan" :eyebrow="$currentProject->name"
                   subtitle="Proses pengajuan izin, sakit, dan cuti{{ $offsiteApprovalEnabled ? ', serta presensi di luar lokasi' : '' }}.">
    </x-page-header>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <ul class="nav nav-pills-soft" role="tablist">
            <li class="nav-item">
                <button class="nav-link {{ $tab === 'leave' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#paneLeave" type="button">
                    <i class="bi bi-envelope-paper me-1"></i>Izin / Sakit / Cuti
                    @if ($counts['leave'])<span class="badge rounded-pill bg-danger ms-1">{{ $counts['leave'] }}</span>@endif
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $tab === 'offsite' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#paneOffsite" type="button">
                    <i class="bi bi-geo me-1"></i>Luar Lokasi
                    @if ($counts['offsite'])<span class="badge rounded-pill bg-danger ms-1">{{ $counts['offsite'] }}</span>@endif
                </button>
            </li>
        </ul>
        <select class="form-select w-auto" id="statusSelect">
            <option value="pending">Menunggu</option>
            <option value="approved">Disetujui</option>
            <option value="rejected">Ditolak</option>
            <option value="all">Semua</option>
        </select>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade {{ $tab === 'leave' ? 'show active' : '' }}" id="paneLeave">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover w-100" id="tableLeaves">
                        <thead><tr><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th>Alasan</th><th>Status</th><th class="text-end"></th></tr></thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade {{ $tab === 'offsite' ? 'show active' : '' }}" id="paneOffsite">
            @unless ($offsiteApprovalEnabled)
                <div class="alert-soft info mb-3">
                    <i class="bi bi-info-circle"></i>
                    <div>Persetujuan luar lokasi <strong>tidak aktif</strong> — presensi luar lokasi langsung tercatat dan hanya ditandai.
                        Daftar di bawah untuk peninjauan. <a href="{{ route('settings.project', ['tab' => 'location']) }}" class="fw-semibold">Ubah pengaturan →</a></div>
                </div>
            @endunless
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover w-100" id="tableOffsite">
                        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Check-in</th><th>Check-out</th><th>Keterangan</th><th>Status</th><th class="text-end"></th></tr></thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
