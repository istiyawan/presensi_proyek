<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Holiday;
use App\Services\PeriodService;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends ApiController
{
    /**
     * Konfigurasi yang di-cache aplikasi agar validasi lokasi tetap jalan saat offline.
     */
    public function config(Request $request, int $project, SettingService $settings): JsonResponse
    {
        $project = $this->memberProject($request, $project);
        $assignment = $this->employee($request)->assignmentFor($project->id);
        $shift = $assignment?->shift ?? $project->defaultShift();
        $s = $settings->project($project);

        $offsiteAllowed = $s['allow_offsite'] && ($assignment?->allow_offsite ?? $s['offsite_scope'] === 'all');

        $holidays = $s['use_national_holidays']
            ? Holiday::query()
                ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $project->id))
                ->whereYear('date', '>=', now()->year)
                ->orderBy('date')
                ->get(['date', 'name'])
                ->map(fn ($h) => ['date' => $h->date->toDateString(), 'name' => $h->name])
            : [];

        return $this->ok([
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'timezone' => $project->timezone,
            ],
            'locations' => $project->locations()->where('is_active', true)->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'latitude' => $l->latitude,
                    'longitude' => $l->longitude,
                    'radius_m' => $l->radius_m,
                ]),
            'shift' => $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'late_enabled' => $shift->late_enabled,
                'late_tolerance_min' => $shift->late_tolerance_min,
                'checkin_open_time' => $shift->checkin_open_time,
                'checkin_close_time' => $shift->checkin_close_time,
            ] : null,
            'rules' => [
                'offsite_allowed' => $offsiteAllowed,
                'offsite_requires_note' => (bool) $s['offsite_requires_note'],
                'offsite_requires_approval' => (bool) $s['offsite_requires_approval'],
                'max_gps_accuracy_m' => (int) $s['max_gps_accuracy_m'],
                'block_mock_location' => (bool) $s['block_mock_location'],
                'require_checkout_in_location' => (bool) $s['require_checkout_in_location'],
                'max_work_hours' => (int) $s['max_work_hours'],
                'backdate_max_days' => $s['backdate_max_days'] === null ? null : (int) $s['backdate_max_days'],
                'allow_offline' => (bool) $s['allow_offline'],
                'offline_max_hours' => (int) $s['offline_max_hours'],
                'working_days' => array_map('intval', $s['working_days']),
            ],
            'holidays' => $holidays,
            'is_team_leader' => (bool) $assignment?->is_team_leader,
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    public function periods(Request $request, int $project, PeriodService $periods): JsonResponse
    {
        $project = $this->memberProject($request, $project);
        $today = CarbonImmutable::now($project->timezone);
        $current = $periods->current($project, $today);

        $list = collect($periods->periods($project, $today))
            ->reverse()
            ->values()
            ->map(fn ($p) => [
                'index' => $p['index'],
                'label' => $p['label'],
                'start' => $p['start']->toDateString(),
                'end' => $p['end']->toDateString(),
                'is_current' => $current && $current['index'] === $p['index'],
            ]);

        return $this->ok($list);
    }
}
