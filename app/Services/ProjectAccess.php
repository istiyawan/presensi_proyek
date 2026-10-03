<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectEmployee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Hak akses web admin per proyek:
 * - super_admin   : semua proyek, kelola penuh
 * - project_admin : proyek di project_user, kelola penuh
 * - team_leader   : proyek tempat ia TL, hanya pantau & approval
 */
class ProjectAccess
{
    /** @var array<int, Collection<int, Project>> */
    private array $cache = [];

    /** @return Collection<int, Project> */
    public function projects(User $user): Collection
    {
        return $this->cache[$user->id] ??= $this->query($user);
    }

    public function canView(User $user, Project $project): bool
    {
        return $this->projects($user)->contains('id', $project->id);
    }

    public function canManage(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole(User::ROLE_PROJECT_ADMIN)
            && $user->managedProjects()->whereKey($project->id)->exists();
    }

    public function canAccessAdmin(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([
            User::ROLE_SUPER_ADMIN, User::ROLE_PROJECT_ADMIN, User::ROLE_TEAM_LEADER,
        ]);
    }

    /** @return Collection<int, Project> */
    private function query(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Project::query()->orderByDesc('is_active')->orderBy('name')->get();
        }

        $ids = collect();
        if ($user->hasRole(User::ROLE_PROJECT_ADMIN)) {
            $ids = $ids->merge($user->managedProjects()->pluck('projects.id'));
        }
        if ($user->hasRole(User::ROLE_TEAM_LEADER) && $user->employee) {
            $ids = $ids->merge(ProjectEmployee::query()
                ->where('employee_id', $user->employee->id)
                ->where('is_team_leader', true)
                ->pluck('project_id'));
        }

        return Project::query()->whereIn('id', $ids->unique())->orderByDesc('is_active')->orderBy('name')->get();
    }
}
