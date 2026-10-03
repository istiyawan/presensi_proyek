<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\ProjectEmployee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Menyiapkan data laporan absensi (format mengikuti "ABSENSI BULAN 1.pdf").
 */
class ReportService
{
    public const STATUS_LABELS = [
        'hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit',
        'cuti' => 'Cuti', 'alpha' => 'Alpha', 'libur' => 'Libur',
    ];

    /** Kode singkat untuk rekap matriks. */
    public const STATUS_CODES = [
        'hadir' => 'H', 'terlambat' => 'T', 'izin' => 'I', 'sakit' => 'S', 'cuti' => 'C', 'alpha' => 'A', 'libur' => 'L',
    ];

    public function __construct(
        private PeriodService $periods,
        private SettingService $settings,
        private CalendarService $calendar,
    ) {}

    /**
     * Rentang laporan dari "Bulan ke-N" atau tanggal bebas.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, label: ?string}
     */
    public function range(Project $project, array $params): array
    {
        if (! empty($params['period'])) {
            $p = $this->periods->period($project, (int) $params['period']);

            return ['from' => $p['start'], 'to' => $p['end'], 'label' => $p['label']];
        }

        return [
            'from' => CarbonImmutable::parse($params['from'], $project->timezone)->startOfDay(),
            'to' => CarbonImmutable::parse($params['to'], $project->timezone)->startOfDay(),
            'label' => null,
        ];
    }

    /**
     * Penugasan yang aktif di sebagian rentang, urut Team Leader lalu urutan penugasan
     * (sama seperti urutan di laporan acuan).
     *
     * @return Collection<int, ProjectEmployee>
     */
    public function assignments(Project $project, CarbonImmutable $from, CarbonImmutable $to, ?array $employeeIds = null): Collection
    {
        return ProjectEmployee::query()
            ->with(['employee.position', 'shift'])
            ->where('project_id', $project->id)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from->toDateString()))
            ->when($employeeIds, fn ($q) => $q->whereIn('employee_id', $employeeIds))
            ->whereHas('employee')
            ->orderByDesc('is_team_leader')
            ->orderBy('id')
            ->get();
    }

    /**
     * Baris laporan individual satu karyawan.
     *
     * @return list<array<string, mixed>>
     */
    public function individualRows(Project $project, ProjectEmployee $assignment, CarbonImmutable $from, CarbonImmutable $to, bool $withPhotos): array
    {
        $tz = $project->timezone;
        $s = $this->settings->project($project);
        $dates = $this->dates($project, $assignment, $from, $to);
        if (! $dates) {
            return [];
        }

        $attendances = Attendance::query()
            ->with('shift')
            ->where('project_id', $project->id)
            ->where('employee_id', $assignment->employee_id)
            ->whereBetween('work_date', [reset($dates), end($dates)])
            ->get()
            ->keyBy(fn ($a) => $a->work_date->toDateString());

        if ($s['report_sort'] === 'desc') {
            $dates = array_reverse($dates);
        }

        $employee = $assignment->employee;
        $defaultShift = $assignment->shift ?? $project->defaultShift();

        return array_map(function (string $date) use ($attendances, $employee, $defaultShift, $tz, $withPhotos) {
            $a = $attendances->get($date);
            $time = function (string $p) use ($a, $tz, $date) {
                if (! $a?->{"{$p}_at"}) {
                    return null;
                }
                $local = $a->{"{$p}_at"}->setTimezone($tz);

                return $local->format('H:i:s').($local->toDateString() > $date ? ' (+1)' : '');
            };
            $coord = fn (string $p) => $a?->{"{$p}_lat"} !== null
                ? number_format($a->{"{$p}_lat"}, 4, '.', '').', '.number_format($a->{"{$p}_lng"}, 4, '.', '')
                : null;

            $notes = [];
            if ($a?->check_in_mode === Attendance::MODE_OFFSITE || $a?->check_out_mode === Attendance::MODE_OFFSITE) {
                $notes[] = 'Luar lokasi';
            }
            if ($a?->is_holiday_work) {
                $notes[] = 'Hari libur';
            }
            if (in_array(Attendance::FLAG_MISSING_CHECKOUT, $a?->flags ?? [], true)) {
                $notes[] = 'Lupa check-out';
            }

            return [
                'date' => CarbonImmutable::parse($date)->format('d/m/Y'),
                'name' => $employee->display_name,
                'position' => $employee->position?->name,
                'shift' => ($a?->shift ?? $defaultShift)?->name,
                'check_in' => $time('check_in'),
                'check_out' => $time('check_out'),
                'coord_in' => $coord('check_in'),
                'coord_out' => $coord('check_out'),
                'status' => $a ? self::STATUS_LABELS[$a->status] : '-',
                'status_code' => $a?->status,
                'notes' => $notes,
                'duration' => $a?->duration_label,
                'photo_in' => $withPhotos ? $this->photoDataUri($a?->check_in_thumb) : null,
                'photo_out' => $withPhotos ? $this->photoDataUri($a?->check_out_thumb) : null,
            ];
        }, $dates);
    }

    /**
     * Rekap matriks karyawan × tanggal.
     *
     * @return array{dates: list<array>, rows: list<array>, totals: array<string, int>}
     */
    public function recap(Project $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $assignments = $this->assignments($project, $from, $to);
        $holidayDates = [];
        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $d = CarbonImmutable::parse($d, $project->timezone);
            $off = ! $this->calendar->isWorkingDay($project, $d);
            $dates[] = [
                'date' => $d->toDateString(),
                'day' => $d->format('j'),
                'dow' => ['', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'][$d->isoWeekday()],
                'off' => $off,
                'sunday' => $d->isSunday(),
            ];
            if ($off) {
                $holidayDates[] = $d->toDateString();
            }
        }

        $attendances = Attendance::query()
            ->where('project_id', $project->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $today = CarbonImmutable::now($project->timezone)->toDateString();
        $rows = [];
        foreach ($assignments as $i => $assignment) {
            $byDate = ($attendances[$assignment->employee_id] ?? collect())->keyBy(fn ($a) => $a->work_date->toDateString());
            $counts = array_fill_keys(array_values(self::STATUS_CODES), 0);
            $cells = [];
            $minutes = 0;
            $offsite = 0;

            foreach ($dates as $d) {
                $assigned = $assignment->start_date->toDateString() <= $d['date']
                    && ($assignment->end_date === null || $assignment->end_date->toDateString() >= $d['date']);
                $a = $byDate->get($d['date']);

                if (! $assigned) {
                    $cells[] = ['code' => '', 'status' => null, 'offsite' => false];

                    continue;
                }
                if (! $a) {
                    $cells[] = ['code' => $d['date'] > $today ? '' : '-', 'status' => null, 'offsite' => false];

                    continue;
                }

                $code = self::STATUS_CODES[$a->status];
                $isOffsite = $a->check_in_mode === Attendance::MODE_OFFSITE || $a->check_out_mode === Attendance::MODE_OFFSITE;
                $counts[$code]++;
                $minutes += (int) $a->duration_minutes;
                $offsite += $isOffsite ? 1 : 0;
                $cells[] = ['code' => $code, 'status' => $a->status, 'offsite' => $isOffsite];
            }

            $rows[] = [
                'no' => $i + 1,
                'name' => $assignment->employee->display_name,
                'nik' => $assignment->employee->nik,
                'position' => $assignment->employee->position?->name,
                'cells' => $cells,
                'counts' => $counts,
                'present' => $counts['H'] + $counts['T'],
                'offsite' => $offsite,
                'minutes' => $minutes,
                'hours_label' => intdiv($minutes, 60).' j '.($minutes % 60).' m',
            ];
        }

        return ['dates' => $dates, 'rows' => $rows];
    }

    /** Data kaki laporan: kota, tanggal, penandatangan. */
    public function footer(Project $project, CarbonImmutable $to): array
    {
        $signDate = $to->min(CarbonImmutable::now($project->timezone));

        return [
            'city' => $this->settings->project($project, 'report_city') ?: $project->city,
            'date' => $signDate->translatedFormat('j F Y'),
            'signatories' => $project->signatories()->where('is_active', true)->get(),
        ];
    }

    /** @return list<string> tanggal (Y-m-d) dalam rentang ∩ masa tugas ∩ s/d hari ini */
    private function dates(Project $project, ProjectEmployee $assignment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->max(CarbonImmutable::parse($assignment->start_date, $project->timezone));
        $end = $to->min(CarbonImmutable::now($project->timezone)->startOfDay());
        if ($assignment->end_date) {
            $end = $end->min(CarbonImmutable::parse($assignment->end_date, $project->timezone));
        }
        if ($start->gt($end)) {
            return [];
        }

        return array_map(fn ($d) => $d->toDateString(), iterator_to_array(CarbonPeriod::create($start, $end)));
    }

    private function photoDataUri(?string $path): ?string
    {
        $disk = Storage::disk(config('presensi.photo.disk'));
        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($disk->get($path));
    }
}
