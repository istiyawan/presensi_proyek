<?php

namespace App\Http\Middleware;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Services\ProjectAccess;
use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan user boleh masuk web admin, lalu tentukan proyek aktif
 * (disimpan di session) dan bagikan ke semua view.
 */
class AdminContext
{
    public function __construct(private ProjectAccess $access, private SettingService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->access->canAccessAdmin($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['username' => 'Akun ini tidak memiliki akses ke web admin.']);
        }

        $projects = $this->access->projects($user);
        $current = $projects->firstWhere('id', (int) $request->session()->get('project_id'))
            ?? $projects->firstWhere('is_active', true)
            ?? $projects->first();

        if ($current) {
            $request->session()->put('project_id', $current->id);
        }

        $request->attributes->set('project', $current);
        app()->instance('current.project', $current);

        View::share([
            'currentProject' => $current,
            'accessibleProjects' => $projects,
            'multiProject' => (bool) config('presensi.multi_project'),
            'canManageProject' => $current ? $this->access->canManage($user, $current) : false,
            'appSettings' => $this->settings->app(),
            'pendingApprovals' => $current ? $this->pendingApprovals($current) : 0,
        ]);

        return $next($request);
    }

    private function pendingApprovals(Project $project): int
    {
        return LeaveRequest::where('project_id', $project->id)->where('status', 'pending')->count()
            + Attendance::where('project_id', $project->id)->where('offsite_approval', 'pending')->count();
    }

    public static function project(): ?Project
    {
        return app()->bound('current.project') ? app('current.project') : null;
    }
}
