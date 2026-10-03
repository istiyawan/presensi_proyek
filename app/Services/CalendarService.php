<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Project;
use Carbon\CarbonInterface;

class CalendarService
{
    public function __construct(private SettingService $settings) {}

    public function isWorkingDay(Project $project, CarbonInterface $date): bool
    {
        $workingDays = array_map('intval', (array) $this->settings->project($project, 'working_days'));

        return in_array($date->isoWeekday(), $workingDays, true) && ! $this->holiday($project, $date);
    }

    /** Hari libur yang berlaku untuk proyek pada tanggal tsb, atau null. */
    public function holiday(Project $project, CarbonInterface $date): ?Holiday
    {
        $useNational = (bool) $this->settings->project($project, 'use_national_holidays');

        return Holiday::query()
            ->whereDate('date', $date->toDateString())
            ->where(function ($q) use ($project, $useNational) {
                $q->where('project_id', $project->id);
                if ($useNational) {
                    $q->orWhereNull('project_id');
                }
            })
            ->first();
    }
}
