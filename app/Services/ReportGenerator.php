<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ReportJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Membuat file laporan untuk satu ReportJob dan menyimpannya di storage privat.
 */
class ReportGenerator
{
    /** Kolom sama dengan PDF individual (+ Keterangan, di PDF tampil di bawah status). */
    private const DETAIL_HEADERS_INDIVIDUAL = ['Tanggal', 'Nama', 'Jabatan', 'Shift', 'Check-in', 'Check-out',
        'Koordinat Check-in', 'Koordinat Check-out', 'Status', 'Keterangan', 'Durasi'];

    private const HEADER_STYLE = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F0FB']],
    ];

    public function __construct(private ReportService $reports, private SettingService $settings) {}

    public function generate(ReportJob $job): string
    {
        $project = $job->project;
        $range = $this->reports->range($project, $job->params);
        $base = [
            'project' => $project,
            'title' => $job->title,
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'footer' => $this->reports->footer($project, $range['to']),
            'printedAt' => CarbonImmutable::now($project->timezone)->translatedFormat('j M Y H:i'),
        ];

        $content = match ([$job->type, $job->format]) {
            ['recap', 'xlsx'] => $this->recapXlsx($project, $base),
            ['recap', 'pdf'] => $this->pdf('reports.pdf.recap', $base + ['recap' => $this->reports->recap($project, $range['from'], $range['to'])]),
            ['individual', 'xlsx'], ['combined', 'xlsx'] => $this->individualXlsx($project, $job, $base),
            default => $this->individualPdf($project, $job, $base),
        };

        $path = "reports/{$project->id}/{$job->uuid}.{$job->format}";
        Storage::put($path, $content);

        return $path;
    }

    private function individualPdf(Project $project, ReportJob $job, array $base): string
    {
        $withPhotos = (bool) ($job->params['photos'] ?? $this->settings->project($project, 'report_show_photo'));
        $sections = $this->sections($project, $job, $base, $withPhotos);

        return $this->pdf('reports.pdf.individual', $base + ['sections' => $sections, 'withPhotos' => $withPhotos]);
    }

    /**
     * Baris laporan per karyawan; laporan gabungan melewati karyawan tanpa data.
     *
     * @return Collection<int, array{employee: Employee, rows: list<array>}>
     */
    private function sections(Project $project, ?ReportJob $job, array $base, bool $withPhotos): Collection
    {
        $individual = $job?->type === 'individual';
        $ids = $individual ? [(int) $job->params['employee_id']] : null;

        return $this->reports->assignments($project, $base['from'], $base['to'], $ids)
            ->map(fn ($assignment) => [
                'employee' => $assignment->employee,
                'rows' => $this->reports->individualRows($project, $assignment, $base['from'], $base['to'], $withPhotos),
            ])
            ->filter(fn ($s) => $individual || $s['rows'])
            ->values();
    }

    /**
     * Excel laporan individual / semua karyawan: satu sheet per karyawan (format sama dengan PDF);
     * laporan gabungan ditambah sheet "Semua Karyawan" berisi seluruh baris untuk filter / pivot.
     */
    private function individualXlsx(Project $project, ReportJob $job, array $base): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($base['title'])->setCreator($this->settings->app('app_name'));
        $book->removeSheetByIndex(0);

        $sections = $this->sections($project, $job, $base, false);

        if ($job->type === 'combined') {
            $this->detailSheet($book->createSheet(), $sections, 'Semua Karyawan');
        }

        $used = ['semua karyawan'];
        foreach ($sections as $section) {
            $this->employeeSheet($book->createSheet(), $section, $base, $used);
        }

        if ($book->getSheetCount() === 0) {
            $book->createSheet()->setTitle('Laporan')->setCellValue('A1', 'Tidak ada data pada periode ini.');
        }
        $book->setActiveSheetIndex(0);

        return $this->xlsx($book);
    }

    private function employeeSheet(Worksheet $sheet, array $section, array $base, array &$used): void
    {
        $employee = $section['employee'];
        $sheet->setTitle($this->sheetTitle($employee->display_name, $used));

        $sheet->setCellValue('A1', 'Laporan Absensi Harian');
        $sheet->setCellValue('A2', 'Periode: '.$base['from']->format('d/m/Y').' - '.$base['to']->format('d/m/Y').($base['label'] ? " ({$base['label']})" : ''));
        $sheet->setCellValue('A3', 'Karyawan: '.$employee->full_name.($employee->nik ? " (NIK {$employee->nik})" : ''));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->fromArray(self::DETAIL_HEADERS_INDIVIDUAL, null, 'A5');
        $sheet->getStyle('A5:K5')->applyFromArray(self::HEADER_STYLE);

        $r = 6;
        foreach ($section['rows'] as $row) {
            $sheet->fromArray([
                $row['date'], $row['name'], $row['position'], $row['shift'],
                $row['check_in'] ?? '-', $row['check_out'] ?? '-', $row['coord_in'] ?? '-', $row['coord_out'] ?? '-',
                $row['status'], implode(', ', $row['notes']), $row['duration'] ?? '-',
            ], null, "A{$r}", true);
            $r++;
        }
        if (! $section['rows']) {
            $sheet->setCellValue("A{$r}", 'Tidak ada data pada periode ini.');
            $sheet->mergeCells("A{$r}:K{$r}");
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
        }

        $last = $r - 1;
        $sheet->getStyle("A5:K{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("E6:H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range('A', 'K') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $sheet->freezePane('A6');

        $this->signatures($sheet, $base['footer'], $last + 3);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    }

    /** Kolom tanda tangan seperti PDF: 1 orang di kanan, 2–4 orang dibagi rata. */
    private function signatures(Worksheet $sheet, array $footer, int $row): void
    {
        $signatories = $footer['signatories']->values();
        $count = $signatories->count();
        $place = "{$footer['city']}, {$footer['date']}";

        if ($count === 0) {
            $sheet->setCellValue("I{$row}", $place);

            return;
        }

        foreach ($signatories as $i => $sig) {
            $c = Coordinate::stringFromColumnIndex($count === 1 ? 9 : 2 + intdiv($i * 7, $count - 1));
            if ($i === $count - 1) {
                $sheet->setCellValue($c.$row, $place);
            }
            $sheet->setCellValue($c.($row + 1), $sig->label);
            $sheet->setCellValue($c.($row + 2), $sig->organization);
            $sheet->getStyle($c.($row + 2))->getAlignment()->setWrapText(true);
            $sheet->setCellValue($c.($row + 6), $sig->name);
            $sheet->getStyle($c.($row + 6))->getFont()->setBold(true)->setUnderline(true);
            $sheet->setCellValue($c.($row + 7), $sig->title);
        }
    }

    /** Nama sheet Excel: maks. 31 karakter, tanpa []:*?/\ dan unik. */
    private function sheetTitle(string $name, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]+/', ' ', $name)) ?: 'Karyawan', 0, 28);
        $title = $base;
        for ($n = 2; in_array(mb_strtolower($title), $used, true); $n++) {
            $title = mb_substr($base, 0, 27 - strlen((string) $n))." ({$n})";
        }
        $used[] = mb_strtolower($title);

        return $title;
    }

    private function xlsx(Spreadsheet $book): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rkp');
        (new Xlsx($book))->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }

    private function pdf(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->setOption(['dpi' => 96, 'defaultFont' => 'Helvetica', 'isFontSubsettingEnabled' => true])
            ->output();
    }

    private function recapXlsx(Project $project, array $base): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($base['title'])->setCreator($this->settings->app('app_name'));

        $this->recapSheet($book->getActiveSheet(), $project, $base);
        $this->detailSheet($book->createSheet(), $this->sections($project, null, $base, false), 'Detail');
        $book->setActiveSheetIndex(0);

        return $this->xlsx($book);
    }

    private function recapSheet(Worksheet $sheet, Project $project, array $base): void
    {
        $recap = $this->reports->recap($project, $base['from'], $base['to']);
        $sheet->setTitle('Rekap');
        $days = count($recap['dates']);
        $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);
        $lastCol = $col(3 + $days + 9);

        $sheet->setCellValue('A1', 'Rekap Absensi Karyawan — '.$project->name);
        $sheet->setCellValue('A2', 'Periode: '.$base['from']->format('d/m/Y').' - '.$base['to']->format('d/m/Y').($base['label'] ? " ({$base['label']})" : ''));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = array_merge(['No', 'Nama', 'Jabatan'], array_map(fn ($d) => $d['day']."\n".$d['dow'], $recap['dates']),
            ['H', 'T', 'I', 'S', 'C', 'A', 'L', 'Luar lokasi', 'Total jam']);
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F0FB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(30);

        $fills = ['terlambat' => 'FFF3D6', 'alpha' => 'FBE0E0', 'izin' => 'E3EEFB', 'sakit' => 'E3EEFB', 'cuti' => 'E3EEFB'];
        $r = 5;
        foreach ($recap['rows'] as $row) {
            $values = [$row['no'], $row['name'], $row['position']];
            foreach ($row['cells'] as $cell) {
                $values[] = $cell['code'].($cell['offsite'] ? '*' : '');
            }
            foreach (['H', 'T', 'I', 'S', 'C', 'A', 'L'] as $code) {
                $values[] = $row['counts'][$code];
            }
            $values[] = $row['offsite'];
            $values[] = round($row['minutes'] / 60, 2);
            $sheet->fromArray($values, null, "A{$r}", true);

            foreach ($row['cells'] as $i => $cell) {
                $coord = $col(4 + $i).$r;
                $fill = $fills[$cell['status']] ?? ($recap['dates'][$i]['off'] ? 'EEEEEE' : null);
                if ($fill) {
                    $sheet->getStyle($coord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
                }
            }
            $r++;
        }

        $last = $r - 1;
        $sheet->getStyle("A4:{$lastCol}{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D5:{$lastCol}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(24);
        for ($i = 4; $i < 4 + $days; $i++) {
            $sheet->getColumnDimension($col($i))->setWidth(4.2);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(10);
        $sheet->freezePane('D5');

        $sheet->setCellValue('A'.($last + 2), 'Keterangan: H Hadir · T Terlambat · I Izin · S Sakit · C Cuti · A Alpha · L Libur · - belum ada data · * luar lokasi · Total jam dalam desimal');
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    }

    /** Seluruh baris dalam satu tabel datar (bisa difilter / pivot). */
    private function detailSheet(Worksheet $sheet, Collection $sections, string $title): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray(['Tanggal', 'NIK', 'Nama', 'Jabatan', 'Shift', 'Check-in', 'Check-out', 'Koordinat check-in',
            'Koordinat check-out', 'Status', 'Keterangan', 'Durasi', 'Durasi (menit)'], null, 'A1');
        $sheet->getStyle('A1:M1')->applyFromArray(self::HEADER_STYLE);

        $r = 2;
        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                $sheet->fromArray([
                    $row['date'], $section['employee']->nik, $row['name'], $row['position'], $row['shift'],
                    $row['check_in'], $row['check_out'], $row['coord_in'], $row['coord_out'], $row['status'],
                    implode(', ', $row['notes']), $row['duration'],
                    $row['duration'] ? $this->minutes($row['duration']) : null,
                ], null, "A{$r}", true);
                $r++;
            }
        }

        foreach (range('A', 'M') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $sheet->setAutoFilter('A1:M'.max(1, $r - 1));
        $sheet->freezePane('A2');
    }

    private function minutes(string $label): int
    {
        preg_match('/(\d+) jam (\d+) menit/', $label, $m);

        return ((int) ($m[1] ?? 0)) * 60 + (int) ($m[2] ?? 0);
    }
}
