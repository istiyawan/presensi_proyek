<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('reports.pdf._styles')
    <style>
        @page { margin: 16mm 9mm 14mm 9mm; }
        body { font-size: 6.6pt; }
        table.grid th, table.grid td { padding: 2px 2px; }
        table.grid th { text-align: center; vertical-align: middle; }
        table.grid tbody tr:nth-child(even) td { background: #fff; }
        td.d { text-align: center; width: 15px; }
        td.off, th.off { background: #eeeeee !important; }
        td.s-terlambat { background: #fff3d6 !important; }
        td.s-alpha { background: #fbe0e0 !important; }
        td.s-izin, td.s-sakit, td.s-cuti { background: #e3eefb !important; }
        td.tot { text-align: center; font-weight: bold; background: #f7f7f7 !important; }
        .legend { margin-top: 3mm; font-size: 7pt; color: #333; }
        .legend b { font-weight: bold; }
    </style>
</head>
<body>
<div class="footer-meta">{{ $project->code }} · {{ $project->name }} · dicetak {{ $printedAt }}</div>

<div class="report-head" style="margin-bottom: 5mm">
    <h1>Rekap Absensi Karyawan</h1>
    <p>Periode: {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}{{ $label ? ' ('.$label.')' : '' }}</p>
    <div class="meta">{{ $project->name }}</div>
</div>

<table class="grid">
    <thead>
    <tr>
        <th rowspan="2" style="width: 14px">No</th>
        <th rowspan="2" style="width: 105px">Nama</th>
        @foreach ($recap['dates'] as $d)
            <th class="{{ $d['off'] ? 'off' : '' }}" style="width: 15px">{{ $d['day'] }}</th>
        @endforeach
        <th rowspan="2">H</th><th rowspan="2">T</th><th rowspan="2">I</th><th rowspan="2">S</th>
        <th rowspan="2">C</th><th rowspan="2">A</th><th rowspan="2">L</th>
        <th rowspan="2" style="width: 26px">Luar lokasi</th>
        <th rowspan="2" style="width: 42px">Total jam</th>
    </tr>
    <tr>
        @foreach ($recap['dates'] as $d)
            <th class="{{ $d['off'] ? 'off' : '' }}" style="font-size: 5.6pt">{{ $d['dow'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($recap['rows'] as $row)
        <tr>
            <td class="c">{{ $row['no'] }}</td>
            <td>{{ $row['name'] }}<br><span class="muted">{{ $row['position'] }}</span></td>
            @foreach ($row['cells'] as $i => $cell)
                <td class="d {{ $recap['dates'][$i]['off'] && ! $cell['status'] ? 'off' : '' }} {{ $cell['status'] ? 's-'.$cell['status'] : '' }}">{{ $cell['code'] }}{{ $cell['offsite'] ? '*' : '' }}</td>
            @endforeach
            @foreach (['H', 'T', 'I', 'S', 'C', 'A', 'L'] as $code)
                <td class="tot">{{ $row['counts'][$code] }}</td>
            @endforeach
            <td class="tot">{{ $row['offsite'] }}</td>
            <td class="tot" style="white-space: nowrap">{{ $row['hours_label'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="legend">
    <b>H</b> Hadir · <b>T</b> Terlambat · <b>I</b> Izin · <b>S</b> Sakit · <b>C</b> Cuti · <b>A</b> Alpha · <b>L</b> Libur ·
    <b>-</b> belum ada data · <b>*</b> presensi di luar lokasi · kolom abu-abu = hari libur / bukan hari kerja
</div>

@include('reports.pdf._signatures')
</body>
</html>
