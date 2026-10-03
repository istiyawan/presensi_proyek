<?php

namespace App\Http\Controllers\Admin;

use App\Models\Holiday;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Libur nasional/cuti bersama bersifat global (project_id null, dikelola super admin);
 * libur khusus proyek dikelola admin proyek.
 */
class HolidayController extends AdminController
{
    public function index(Request $request, SettingService $settings): View
    {
        $project = $this->managedProject();
        $year = (int) $request->query('year', now($project->timezone)->year);

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $project->id))
            ->orderBy('date')
            ->get();

        return view('holidays.index', [
            'page' => 'holidays',
            'title' => 'Hari Libur',
            'year' => $year,
            'holidays' => $holidays,
            'nationalEnabled' => (bool) $settings->project($project, 'use_national_holidays'),
            'workingDays' => array_map('intval', $settings->project($project, 'working_days')),
            'canImport' => is_file(database_path("data/holidays/{$year}.json")),
            'isSuper' => auth()->user()->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $data = $this->validated($request);
        $data['project_id'] = $data['type'] === Holiday::TYPE_PROJECT ? $project->id : null;

        Holiday::create($data);

        return $this->saved('Hari libur ditambahkan.');
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $project = $this->authorizeHoliday($holiday);
        $data = $this->validated($request);
        $data['project_id'] = $data['type'] === Holiday::TYPE_PROJECT ? $project->id : null;

        $holiday->update($data);

        return $this->saved('Hari libur diperbarui.');
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $this->authorizeHoliday($holiday);
        $holiday->delete();

        return $this->saved('Hari libur dihapus.');
    }

    public function import(Request $request): JsonResponse
    {
        $this->managedProject();
        $year = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];

        if (! is_file(database_path("data/holidays/{$year}.json"))) {
            return $this->failed("Data libur nasional {$year} belum tersedia. Tambahkan manual.");
        }

        Artisan::call('holidays:import', ['year' => $year]);

        return $this->saved("Libur nasional & cuti bersama {$year} diimpor. Periksa kembali dengan SKB resmi.");
    }

    private function validated(Request $request): array
    {
        $types = auth()->user()->isSuperAdmin()
            ? [Holiday::TYPE_NATIONAL, Holiday::TYPE_CUTI_BERSAMA, Holiday::TYPE_PROJECT]
            : [Holiday::TYPE_PROJECT];

        return $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($types)],
        ], ['type.in' => 'Libur nasional hanya dapat dikelola super admin.']);
    }

    private function authorizeHoliday(Holiday $holiday)
    {
        $project = $this->managedProject();

        if ($holiday->project_id === null) {
            $this->superAdminOnly();
        } else {
            abort_unless($holiday->project_id === $project->id, 404);
        }

        return $project;
    }
}
