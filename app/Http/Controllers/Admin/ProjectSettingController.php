<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProjectEmployee;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectSettingController extends AdminController
{
    public function edit(Request $request, SettingService $settings): View
    {
        $project = $this->managedProject();

        return view('settings.project', [
            'page' => 'project-settings',
            'title' => 'Pengaturan Proyek',
            'tab' => $request->query('tab', 'general'),
            'project' => $project,
            's' => $settings->project($project),
            'timezones' => ProjectController::TIMEZONES,
            'signatories' => $project->signatories()->with('employee')->get(),
            'employees' => ProjectEmployee::with('employee.position')->where('project_id', $project->id)->get()
                ->reject(fn ($a) => $a->employee->trashed())
                ->map(fn ($a) => [
                    'id' => $a->employee_id,
                    'name' => $a->employee->display_name,
                    'position' => $a->employee->position?->name,
                ])->sortBy('name')->values(),
        ]);
    }

    public function update(Request $request, string $group, SettingService $settings): JsonResponse
    {
        $project = $this->managedProject();

        if ($group === 'general') {
            $project->update($request->validate(ProjectController::rules($project)));

            return $this->saved('Informasi proyek disimpan.');
        }

        $rules = match ($group) {
            'period' => [
                'period_start_day' => ['required', 'integer', 'between:1,31'],
                'period_end_day' => ['required', 'integer', 'between:1,31'],
                'period_end_next_month' => ['required', 'boolean'],
            ],
            'workdays' => [
                'working_days' => ['required', 'array', 'min:1'],
                'working_days.*' => ['integer', 'between:1,7'],
                'use_national_holidays' => ['required', 'boolean'],
            ],
            'location' => [
                'allow_offsite' => ['required', 'boolean'],
                'offsite_scope' => ['required', 'in:all,selected'],
                'offsite_requires_approval' => ['required', 'boolean'],
                'offsite_requires_note' => ['required', 'boolean'],
                'max_gps_accuracy_m' => ['required', 'integer', 'between:5,500'],
                'block_mock_location' => ['required', 'boolean'],
                'require_checkout_in_location' => ['required', 'boolean'],
                'max_work_hours' => ['required', 'integer', 'between:12,36'],
                // Kosong = tanpa batas
                'backdate_max_days' => ['nullable', 'integer', 'between:0,365'],
            ],
            'offline' => [
                'allow_offline' => ['required', 'boolean'],
                'offline_max_hours' => ['required', 'integer', 'between:1,720'],
            ],
            'report' => [
                'report_city' => ['nullable', 'string', 'max:100'],
                'report_sort' => ['required', 'in:asc,desc'],
                'report_show_photo' => ['required', 'boolean'],
            ],
            default => abort(404),
        };

        $data = $request->validate($rules, [
            'working_days.required' => 'Pilih minimal satu hari kerja.',
        ], [
            'period_start_day' => 'tanggal mulai periode', 'period_end_day' => 'tanggal akhir periode',
            'max_gps_accuracy_m' => 'batas akurasi GPS', 'offline_max_hours' => 'batas umur data offline',
            'max_work_hours' => 'batas jam kerja', 'backdate_max_days' => 'batas tanggal mundur',
            'report_city' => 'kota laporan',
        ]);

        // Simpan tipe yang benar (bukan string "1"/"0")
        foreach ($rules as $key => $r) {
            if (str_contains($key, '.') || ! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }
            if (in_array('boolean', $r, true)) {
                $data[$key] = (bool) $data[$key];
            } elseif (in_array('integer', $r, true)) {
                $data[$key] = (int) $data[$key];
            }
        }
        if (isset($data['working_days'])) {
            $data['working_days'] = array_values(array_unique(array_map('intval', $data['working_days'])));
            sort($data['working_days']);
        }

        $settings->setProject($project, $data);

        return $this->saved('Pengaturan disimpan.');
    }
}
