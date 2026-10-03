<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Services\DailyCloseService;
use App\Services\PeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class PeriodAndCalendarTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    public function test_periods_match_reference_report(): void
    {
        $project = $this->makeProject();
        $service = app(PeriodService::class);

        $p1 = $service->period($project, 1);
        $this->assertSame(['2026-02-25', '2026-03-26'], [$p1['start']->toDateString(), $p1['end']->toDateString()]);
        $this->assertSame('Bulan ke-1', $p1['label']);

        // Tumpang tindih 25–26 disengaja (ikut format laporan)
        $p2 = $service->period($project, 2);
        $this->assertSame(['2026-03-25', '2026-04-26'], [$p2['start']->toDateString(), $p2['end']->toDateString()]);

        $this->assertSame(2, $service->current($project, CarbonImmutable::parse('2026-03-26'))['index']);
        $this->assertSame(1, $service->current($project, CarbonImmutable::parse('2026-03-24'))['index']);
    }

    public function test_period_settings_are_configurable(): void
    {
        $project = $this->makeProject(['period_start_day' => 1, 'period_end_day' => 31, 'period_end_next_month' => false]);

        $p = app(PeriodService::class)->period($project, 2);
        $this->assertSame(['2026-03-01', '2026-03-31'], [$p['start']->toDateString(), $p['end']->toDateString()]);
    }

    public function test_daily_close_marks_alpha_and_libur(): void
    {
        $project = $this->makeProject(['use_national_holidays' => true]);
        $absent = $this->makeEmployee($project);
        $present = $this->makeEmployee($project);
        Attendance::create([
            'employee_id' => $present->id, 'project_id' => $project->id, 'work_date' => '2026-03-10',
            'check_in_at' => '2026-03-10 08:00:00', 'status' => 'hadir',
        ]);
        Holiday::create(['date' => '2026-03-19', 'name' => 'Nyepi', 'type' => 'national']);

        $stats = app(DailyCloseService::class)->close($project, CarbonImmutable::parse('2026-03-10'));
        $this->assertSame(['alpha' => 1, 'libur' => 0], $stats);
        // Lupa check-out tidak ditandai saat tutup hari (shift bisa lewat tengah malam)
        $this->assertNull(Attendance::where('employee_id', $present->id)->first()->flags);

        $stats = app(DailyCloseService::class)->close($project, CarbonImmutable::parse('2026-03-19'));
        $this->assertSame(2, $stats['libur']);
    }

    public function test_national_holidays_ignored_unless_enabled(): void
    {
        $project = $this->makeProject();
        $this->makeEmployee($project);
        Holiday::create(['date' => '2026-03-19', 'name' => 'Nyepi', 'type' => 'national']);

        // 7 hari kerja & libur nasional off → tetap hari kerja (alpha)
        $stats = app(DailyCloseService::class)->close($project, CarbonImmutable::parse('2026-03-19'));
        $this->assertSame(1, $stats['alpha']);
    }
}
