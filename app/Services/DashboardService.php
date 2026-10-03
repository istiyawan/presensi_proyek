<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\ProjectEmployee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private CalendarService $calendar) {}

    public function summary(Project $project, CarbonImmutable $date): array
    {
        $day = $date->toDateString();
        $members = ProjectEmployee::query()->where('project_id', $project->id)->activeOn($day)->count();
        $rows = Attendance::query()->where('project_id', $project->id)->whereDate('work_date', $day)->get();

        $present = $rows->whereNotNull('check_in_at');
        $leave = $rows->whereIn('status', ['izin', 'sakit', 'cuti'])->count();
        $absent = $rows->where('status', Attendance::STATUS_ALPHA)->count();
        $off = $rows->where('status', Attendance::STATUS_LIBUR)->count();

        return [
            'members' => $members,
            'present' => $present->count(),
            'late' => $present->where('status', Attendance::STATUS_TERLAMBAT)->count(),
            'offsite' => $present->where('check_in_mode', Attendance::MODE_OFFSITE)->count(),
            'offline' => $present->where('check_in_offline', true)->count(),
            'checked_out' => $present->whereNotNull('check_out_at')->count(),
            'leave' => $leave,
            'absent' => $absent,
            'not_yet' => max(0, $members - $present->count() - $leave - $absent - $off),
            'rate' => $members ? round($present->count() / $members * 100) : 0,
            'avg_duration' => (int) round($present->whereNotNull('duration_minutes')->avg('duration_minutes') ?? 0),
            'is_working_day' => $this->calendar->isWorkingDay($project, $date),
            'holiday' => $this->calendar->holiday($project, $date)?->name,
        ];
    }

    /**
     * Tren harian per status untuk grafik batang bertumpuk.
     *
     * @return list<array{date: string, label: string, hadir: int, leave: int, terlambat: int, alpha: int}>
     */
    public function trend(Project $project, CarbonImmutable $end, int $days = 14): array
    {
        $start = $end->subDays($days - 1);

        $counts = Attendance::query()
            ->selectRaw('work_date, status, COUNT(*) as total')
            ->where('project_id', $project->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('work_date', 'status')
            ->get()
            ->groupBy(fn ($r) => CarbonImmutable::parse($r->work_date)->toDateString());

        $result = [];
        foreach (CarbonPeriod::create($start, $end) as $d) {
            $byStatus = ($counts[$d->toDateString()] ?? collect())->pluck('total', 'status');
            $result[] = [
                'date' => $d->toDateString(),
                'label' => $d->translatedFormat('j M'),
                'weekday' => $d->translatedFormat('l'),
                'hadir' => (int) ($byStatus['hadir'] ?? 0),
                'leave' => (int) (($byStatus['izin'] ?? 0) + ($byStatus['sakit'] ?? 0) + ($byStatus['cuti'] ?? 0)),
                'terlambat' => (int) ($byStatus['terlambat'] ?? 0),
                'alpha' => (int) ($byStatus['alpha'] ?? 0),
            ];
        }

        return $result;
    }

    /** Aktivitas check-in/out terbaru pada tanggal tsb. */
    public function activities(Project $project, CarbonImmutable $date, int $limit = 7): Collection
    {
        return Attendance::query()
            ->with('employee.position')
            ->where('project_id', $project->id)
            ->whereDate('work_date', $date->toDateString())
            ->whereNotNull('check_in_at')
            ->get()
            ->flatMap(fn (Attendance $a) => array_filter([
                ['type' => 'in', 'at' => $a->check_in_at, 'mode' => $a->check_in_mode, 'offline' => $a->check_in_offline, 'a' => $a],
                $a->check_out_at ? ['type' => 'out', 'at' => $a->check_out_at, 'mode' => $a->check_out_mode, 'offline' => $a->check_out_offline, 'a' => $a] : null,
            ]))
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }

    /** Titik check-in hari tsb untuk peta. */
    public function mapPoints(Project $project, CarbonImmutable $date): array
    {
        return Attendance::query()
            ->with('employee')
            ->where('project_id', $project->id)
            ->whereDate('work_date', $date->toDateString())
            ->whereNotNull('check_in_lat')
            ->get()
            ->map(fn (Attendance $a) => [
                'name' => $a->employee->display_name,
                'lat' => $a->check_in_lat,
                'lng' => $a->check_in_lng,
                'time' => $a->check_in_at->setTimezone($project->timezone)->format('H:i'),
                'offsite' => $a->check_in_mode === Attendance::MODE_OFFSITE,
            ])
            ->all();
    }

    public function pending(Project $project): array
    {
        return [
            'leave' => LeaveRequest::where('project_id', $project->id)->where('status', 'pending')->count(),
            'offsite' => Attendance::where('project_id', $project->id)->where('offsite_approval', 'pending')->count(),
            'missing_checkout' => Attendance::where('project_id', $project->id)
                ->whereJsonContains('flags', Attendance::FLAG_MISSING_CHECKOUT)
                ->where('work_date', '>=', now()->subDays(30)->toDateString())
                ->count(),
        ];
    }

    public function latestDateWithData(Project $project): ?string
    {
        return Attendance::where('project_id', $project->id)->whereNotNull('check_in_at')->max('work_date');
    }
}
