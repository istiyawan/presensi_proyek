<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('reports.pdf._styles')
</head>
<body>
<div class="footer-meta">{{ $project->code }} · {{ $project->name }} · dicetak {{ $printedAt }}</div>

@foreach ($sections as $section)
    <div class="report-head">
        <h1>Laporan Absensi Harian</h1>
        <p>Periode: {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</p>
        <p>Tipe Laporan: Individual</p>
    </div>

    <table class="grid">
        <colgroup>
            <col style="width: 7.3%"><col style="width: 12.5%"><col style="width: 10%"><col style="width: 6.3%">
            <col style="width: 7.6%"><col style="width: 7.6%"><col style="width: 9.7%"><col style="width: 9.7%">
            <col style="width: 7.6%"><col style="width: 7.6%">@if ($withPhotos)<col style="width: 14.1%">@endif
        </colgroup>
        <thead>
        <tr>
            <th>Tanggal</th><th>Nama</th><th>Jabatan</th><th>Shift</th>
            <th class="c">Check-in</th><th class="c">Check-out</th>
            <th class="c">Koordinat Check-in</th><th class="c">Koordinat Check-out</th>
            <th>Status</th><th>Durasi</th>@if ($withPhotos)<th>Foto</th>@endif
        </tr>
        </thead>
        <tbody>
        @forelse ($section['rows'] as $row)
            <tr style="height: {{ $withPhotos ? '60px' : 'auto' }}">
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['position'] }}</td>
                <td>{{ $row['shift'] }}</td>
                <td class="c">{{ $row['check_in'] ?? '-' }}</td>
                <td class="c">{{ $row['check_out'] ?? '-' }}</td>
                <td class="c">{{ $row['coord_in'] ?? '-' }}</td>
                <td class="c">{{ $row['coord_out'] ?? '-' }}</td>
                <td>
                    {{ $row['status'] }}
                    @foreach ($row['notes'] as $note)<span class="note">{{ $note }}</span>@endforeach
                </td>
                <td>{{ $row['duration'] ?? '-' }}</td>
                @if ($withPhotos)
                    <td class="photos">
                        @foreach (['photo_in' => 'CI', 'photo_out' => 'CO'] as $key => $label)
                            <span class="photo">
                                @if ($row[$key])<img src="{{ $row[$key] }}" alt="">@else<div class="ph"></div>@endif
                                <span>{{ $label }}</span>
                            </span>
                        @endforeach
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $withPhotos ? 11 : 10 }}" class="c muted">Tidak ada data pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    @include('reports.pdf._signatures')

    @unless ($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach
</body>
</html>
