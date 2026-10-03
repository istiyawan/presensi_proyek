<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ReportJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
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
            default => $this->individualPdf($project, $job, $base),
        };

        $path = "reports/{$project->id}/{$job->uuid}.{$job->format}";
        Storage::put($path, $content);

        return $path;
    }

    private function individualPdf(Project $project, ReportJob $job, array $base): string
    {
        $withPhotos = (bool) ($job->params['photos'] ?? $this->settings->project($project, 'report_show_photo'));
        $ids = $job->type === 'individual' ? [(int) $job->params['employee_id']] : null;

        $sections = $this->reports->assignments($project, $base['from'], $base['to'], $ids)
            ->map(fn ($assignment) => [
                'employee' => $assignment->employee,
                'rows' => $this->reports->individualRows($project, $assignment, $base['from'], $base['to'], $withPhotos),
            ])
            ->filter(fn ($s) => $job->type === 'individual' || $s['rows'])
            ->values();

        return $this->pdf('reports.pdf.individual', $base + ['sections' => $sections, 'withPhotos' => $withPhotos]);
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
        $this->detailSheet($book->createSheet(), $project, $base);
        $book->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'rkp');
        (new Xlsx($book))->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content;
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

    private function detailSheet(Worksheet $sheet, Project $project, array $base): void
    {
        $sheet->setTitle('Detail');
        $sheet->fromArray(['Tanggal', 'NIK', 'Nama', 'Jabatan', 'Shift', 'Check-in', 'Check-out', 'Koordinat check-in',
            'Koordinat check-out', 'Status', 'Keterangan', 'Durasi', 'Durasi (menit)'], null, 'A1');
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F0FB']],
        ]);

        $r = 2;
        foreach ($this->reports->assignments($project, $base['from'], $base['to']) as $assignment) {
            foreach ($this->reports->individualRows($project, $assignment, $base['from'], $base['to'], false) as $row) {
                $sheet->fromArray([
                    $row['date'], $assignment->employee->nik, $row['name'], $row['position'], $row['shift'],
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
