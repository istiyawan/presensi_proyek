@extends('layouts.app')

@php
    $hour = now($currentProject->timezone)->hour;
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $s = $summary;
    $fmtDur = fn ($m) => $m ? intdiv($m, 60).' j '.($m % 60).' m' : '–';
@endphp

@section('content')
    <x-page-header :title="$greet.', '.\Illuminate\Support\Str::before(auth()->user()->name, ' ')"
                   :eyebrow="$currentProject->name"
                   :subtitle="'Ringkasan kehadiran <strong class=\'text-ink\'>'.$date->translatedFormat('l, j F Y').'</strong>'.($s['holiday'] ? ' · <span class=\'chip soft-danger\'>'.e($s['holiday']).'</span>' : '')">
        <x-slot:actions>
            @if (! $isToday)
                <a href="{{ route('dashboard') }}" class="btn btn-soft"><i class="bi bi-calendar-check me-1"></i>Hari ini</a>
            @elseif ($latestDate && $latestDate !== $date->toDateString() && $s['present'] === 0)
                <a href="{{ route('dashboard', ['date' => $latestDate]) }}" class="btn btn-brand-soft">
                    <i class="bi bi-clock-history me-1"></i>Data terakhir: {{ \Carbon\Carbon::parse($latestDate)->translatedFormat('j M Y') }}
                </a>
            @endif
            <form method="GET" class="d-flex" id="formDate">
                <input type="text" name="date" class="form-control datepicker" value="{{ $date->toDateString() }}" style="width: 170px" aria-label="Tanggal">
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <x-stat-card hero label="Tingkat kehadiran" :value="$s['rate'].'%'" icon="graph-up-arrow"
                         :foot="$s['present'].' dari '.$s['members'].' karyawan sudah check-in'" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-stat-card label="Hadir" :value="$s['present']" icon="person-check" tone="success"
                         :foot="$s['checked_out'].' sudah check-out'" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-stat-card label="Terlambat" :value="$s['late']" icon="alarm" tone="warning" foot="Sesuai aturan shift" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-stat-card label="Luar lokasi" :value="$s['offsite']" icon="geo" tone="violet"
                         :foot="$s['offline'] ? $s['offline'].' terkirim offline' : 'Di luar radius proyek'" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-stat-card label="Izin / sakit / cuti" :value="$s['leave']" icon="envelope-paper" tone="info" foot="Pengajuan disetujui" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-stat-card label="Belum hadir" :value="$s['not_yet'] + $s['absent']" icon="person-dash" tone="danger"
                         :foot="$s['absent'] ? $s['absent'].' tercatat alpha' : 'Belum check-in'" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xxl-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Tren kehadiran 14 hari</h2>
                        <p class="card-subtitle">Jumlah karyawan per status, s/d {{ $date->translatedFormat('j F Y') }}</p>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Tampilan">
                        <button class="btn btn-soft active" data-view="chart"><i class="bi bi-bar-chart"></i></button>
                        <button class="btn btn-soft" data-view="table"><i class="bi bi-table"></i></button>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="legend mb-3" id="trendLegend"></div>
                    <div id="trendChartWrap" class="chart-box flex-grow-1" style="min-height: 280px">
                        <canvas id="trendChart" aria-label="Grafik tren kehadiran" role="img"></canvas>
                        <div class="chart-tooltip" id="trendTooltip" style="opacity:0"></div>
                    </div>
                    <div id="trendTableWrap" class="table-wrap" hidden>
                        <div class="table-responsive">
                            <table class="table table-premium table-sm mb-0 tabular">
                                <thead><tr><th>Tanggal</th><th class="text-end">Hadir</th><th class="text-end">Izin/Sakit/Cuti</th><th class="text-end">Terlambat</th><th class="text-end">Alpha</th></tr></thead>
                                <tbody>
                                @foreach (array_reverse($trend) as $t)
                                    <tr>
                                        <td>{{ $t['weekday'] }}, {{ $t['label'] }}</td>
                                        <td class="text-end">{{ $t['hadir'] }}</td>
                                        <td class="text-end">{{ $t['leave'] }}</td>
                                        <td class="text-end">{{ $t['terlambat'] }}</td>
                                        <td class="text-end">{{ $t['alpha'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Aktivitas terbaru</h2>
                        <p class="card-subtitle">Check-in &amp; check-out {{ $isToday ? 'hari ini' : 'pada tanggal ini' }}</p>
                    </div>
                    <a href="{{ route('attendances.index', ['from' => $date->toDateString(), 'to' => $date->toDateString()]) }}" class="btn btn-sm btn-brand-soft">Semua</a>
                </div>
                <div class="card-body pt-1">
                    @forelse ($activities as $act)
                        @if ($loop->first)<ul class="activity-list">@endif
                        @php $e = $act['a']->employee; @endphp
                        <li>
                            <span class="avatar avatar-sm" data-avatar="{{ $e->full_name }}"></span>
                            <div class="min-w-0">
                                <div class="person-name text-truncate fs-7">{{ $e->display_name }}</div>
                                <div class="d-flex gap-1 mt-1 flex-wrap">
                                    <span class="chip {{ $act['type'] === 'in' ? 'soft-success' : 'soft-slate' }}">
                                        <i class="bi bi-box-arrow-{{ $act['type'] === 'in' ? 'in-right' : 'right' }}"></i>{{ $act['type'] === 'in' ? 'Check-in' : 'Check-out' }}
                                    </span>
                                    @if ($act['mode'] === 'offsite')<span class="chip soft-warning">Luar lokasi</span>@endif
                                    @if ($act['offline'])<span class="chip soft-slate"><i class="bi bi-wifi-off"></i>Offline</span>@endif
                                </div>
                            </div>
                            <div class="time">{{ $act['at']->setTimezone($currentProject->timezone)->format('H:i') }}<small>{{ $e->position?->name }}</small></div>
                        </li>
                        @if ($loop->last)</ul>@endif
                    @empty
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-activity"></i></div>
                            <h6>Belum ada aktivitas</h6>
                            <p>Check-in karyawan akan muncul di sini.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Peta check-in</h2>
                        <p class="card-subtitle">Lingkaran = radius titik lokasi proyek</p>
                    </div>
                    <div class="legend">
                        <span><span class="swatch" style="background:#0f9d58"></span>Dalam lokasi</span>
                        <span><span class="swatch" style="background:#e79a00"></span>Luar lokasi</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="dashboardMap" class="map-box" style="height: 340px"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title">Perlu tindakan</h2></div>
                <div class="card-body py-2">
                    @php
                        $todo = [
                            ['icon' => 'envelope-paper', 'tone' => 'info', 'label' => 'Pengajuan izin menunggu', 'count' => $pending['leave'], 'url' => route('approvals.index')],
                            ['icon' => 'geo', 'tone' => 'warning', 'label' => 'Presensi luar lokasi menunggu', 'count' => $pending['offsite'], 'url' => route('approvals.index', ['tab' => 'offsite'])],
                            ['icon' => 'door-open', 'tone' => 'danger', 'label' => 'Lupa check-out (30 hari)', 'count' => $pending['missing_checkout'], 'url' => route('attendances.index', ['flag' => 'missing_checkout'])],
                        ];
                    @endphp
                    @foreach ($todo as $t)
                        <a href="{{ $t['url'] }}" class="setting-row text-reset">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <span class="stat-icon soft-{{ $t['tone'] }} m-0" style="width:2.2rem;height:2.2rem;border-radius:.65rem;display:grid;place-items:center"><i class="bi bi-{{ $t['icon'] }}"></i></span>
                                <span class="setting-title fs-7">{{ $t['label'] }}</span>
                            </div>
                            <span class="d-flex align-items-center gap-2">
                                <strong class="fs-5 {{ $t['count'] ? 'text-ink' : 'text-muted' }}">{{ $t['count'] }}</strong>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Rata-rata durasi kerja</h2></div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-2">
                        <span class="fs-2 fw-bold text-ink lh-1">{{ $fmtDur($s['avg_duration']) }}</span>
                    </div>
                    <p class="text-muted fs-8 mb-3 mt-1">Dari karyawan yang sudah check-out pada tanggal ini</p>
                    <div class="progress progress-thin"><div class="progress-bar" style="width: {{ min(100, round($s['avg_duration'] / 600 * 100)) }}%"></div></div>
                    <div class="d-flex justify-content-between fs-8 text-muted mt-1"><span>0 j</span><span>10 j</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.DashboardData = {{ Js::from(["trend" => $trend, "points" => $points, "locations" => $locations]) }};
    </script>
@endpush
