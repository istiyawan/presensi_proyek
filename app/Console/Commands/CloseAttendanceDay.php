<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\DailyCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:close-day {date? : Tanggal (Y-m-d), default kemarin} {--project= : ID proyek}')]
#[Description('Tandai alpha/libur untuk karyawan tanpa presensi pada satu hari')]
class CloseAttendanceDay extends Command
{
    public function handle(DailyCloseService $service): int
    {
        $projects = Project::query()
            ->where('is_active', true)
            ->when($this->option('project'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($projects as $project) {
            $date = $this->argument('date')
                ? CarbonImmutable::parse($this->argument('date'), $project->timezone)
                : CarbonImmutable::now($project->timezone)->subDay();

            $stats = $service->close($project, $date);

            $this->info(sprintf(
                '[%s] %s → alpha: %d, libur: %d',
                $project->code, $date->toDateString(), $stats['alpha'], $stats['libur'],
            ));
        }

        return self::SUCCESS;
    }
}
