<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return $this->ok(self::profile($request->user()));
    }

    public function time(): JsonResponse
    {
        return $this->ok([
            'server_time' => now()->toIso8601String(),
            'timestamp_ms' => (int) now()->getPreciseTimestamp(3),
        ]);
    }

    public function appInfo(SettingService $settings): JsonResponse
    {
        return $this->ok([
            'app_name' => $settings->app('app_name'),
            'company_name' => $settings->app('company_name'),
            'primary_color' => $settings->app('primary_color'),
            'min_app_version' => $settings->app('min_app_version'),
        ]);
    }

    public static function profile(User $user): array
    {
        $employee = $user->employee->load(['position', 'projects']);
        $today = now()->toDateString();

        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
            'employee' => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->display_name,
                'full_name' => $employee->full_name,
                'position' => $employee->position?->name,
                'phone' => $employee->phone,
            ],
            'projects' => $employee->projects
                ->filter(fn ($p) => $p->is_active
                    && $p->pivot->start_date->toDateString() <= $today
                    && ($p->pivot->end_date === null || $p->pivot->end_date->toDateString() >= $today))
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'is_team_leader' => $p->pivot->is_team_leader,
                ])
                ->values(),
        ];
    }
}
