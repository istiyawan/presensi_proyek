<?php

namespace App\Http\Controllers\Admin;

use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $project = $this->project();
        $today = CarbonImmutable::now($project->timezone)->startOfDay();
        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->query('date'), $project->timezone)->startOfDay()
            : $today;

        $latest = $dashboard->latestDateWithData($project);

        return view('dashboard.index', [
            'page' => 'dashboard',
            'title' => 'Dashboard',
            'date' => $date,
            'isToday' => $date->equalTo($today),
            'latestDate' => $latest ? CarbonImmutable::parse($latest)->toDateString() : null,
            'summary' => $dashboard->summary($project, $date),
            'trend' => $dashboard->trend($project, $date),
            'activities' => $dashboard->activities($project, $date),
            'points' => $dashboard->mapPoints($project, $date),
            'locations' => $project->locations()->where('is_active', true)->get(['name', 'latitude', 'longitude', 'radius_m']),
            'pending' => $dashboard->pending($project),
        ]);
    }
}
