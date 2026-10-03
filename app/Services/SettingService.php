<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Project;
use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi & proyek: default dari config/presensi.php,
 * di-override oleh tabel app_settings / project_settings, lalu di-cache.
 */
class SettingService
{
    public function app(?string $key = null, mixed $default = null): mixed
    {
        // Hanya override dari DB yang di-cache; default digabung saat dibaca agar
        // default baru (setelah update aplikasi) langsung berlaku.
        $all = array_merge(
            config('presensi.app'),
            Cache::rememberForever('settings.app', fn () => AppSetting::query()->pluck('value', 'key')->all()),
        );

        return $key === null ? $all : ($all[$key] ?? $default);
    }

    public function setApp(array $values): void
    {
        foreach ($values as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings.app');
    }

    public function project(Project|int $project, ?string $key = null, mixed $default = null): mixed
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        $all = array_merge(
            config('presensi.project'),
            Cache::rememberForever("settings.project.{$projectId}", fn () => ProjectSetting::query()
                ->where('project_id', $projectId)->pluck('value', 'key')->all()),
        );

        return $key === null ? $all : ($all[$key] ?? $default);
    }

    public function setProject(Project|int $project, array $values): void
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        foreach ($values as $key => $value) {
            ProjectSetting::updateOrCreate(['project_id' => $projectId, 'key' => $key], ['value' => $value]);
        }
        Cache::forget("settings.project.{$projectId}");
    }
}
