<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\ProjectEmployee;
use Carbon\CarbonImmutable;

/**
 * Tutup hari: karyawan tanpa presensi → alpha / libur. Aman dijalankan berulang.
 * Bila data offline untuk tanggal tsb masuk belakangan, AttendanceService
 * menimpa baris alpha/libur ini.
 *
 * Penandaan lupa check-out TIDAK dilakukan di sini (shift bisa masih berjalan
 * lewat tengah malam) — lihat flagMissingCheckouts().
 */
class DailyCloseService
{
    public function __construct(private CalendarService $calendar, private SettingService $settings) {}

    /** @return array{alpha: int, libur: int} */
    public function close(Project $project, CarbonImmutable $date): array
    {
        $stats = ['alpha' => 0, 'libur' => 0];
        $workDate = $date->toDateString();
        $isWorkingDay = $this->calendar->isWorkingDay($project, $date);

        $assignments = ProjectEmployee::query()
            ->where('project_id', $project->id)
            ->activeOn($workDate)
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->get();

        foreach ($assignments as $assignment) {
            $exists = Attendance::query()
                ->where('employee_id', $assignment->employee_id)
                ->where('project_id', $project->id)
                ->whereDate('work_date', $workDate)
                ->exists();

            if ($exists) {
                continue;
            }

            $status = $isWorkingDay ? Attendance::STATUS_ALPHA : Attendance::STATUS_LIBUR;
            Attendance::create([
                'employee_id' => $assignment->employee_id,
                'project_id' => $project->id,
                'shift_id' => $assignment->shift_id,
                'work_date' => $workDate,
                'status' => $status,
            ]);
            $stats[$status]++;
        }

        return $stats;
    }

    /**
     * Tandai presensi tanpa check-out yang sudah melewati jendela max_work_hours
     * (mis. masuk 2 Okt 08:00 → ditandai 3 Okt 04:00 dengan default 20 jam).
     */
    public function flagMissingCheckouts(Project $project, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $limit = $now->subHours((int) $this->settings->project($project, 'max_work_hours'))
            ->setTimezone(config('app.timezone'));
        $count = 0;

        Attendance::query()
            ->where('project_id', $project->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->where('check_in_at', '<', $limit)
            ->where('work_date', '>=', $now->subDays(45)->toDateString())
            ->each(function (Attendance $attendance) use (&$count) {
                if (in_array(Attendance::FLAG_MISSING_CHECKOUT, $attendance->flags ?? [], true)) {
                    return;
                }
                $attendance->addFlag(Attendance::FLAG_MISSING_CHECKOUT);
                $attendance->save();
                $count++;
            });

        return $count;
    }
}
