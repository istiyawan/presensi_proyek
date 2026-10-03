<?php

namespace App\Http\Controllers\Admin;

use App\Models\ReportSignatory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SignatoryController extends AdminController
{
    public const MAX_ACTIVE = 4;

    public function store(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $data = $this->validated($request);

        if ($data['is_active'] && $project->signatories()->where('is_active', true)->count() >= self::MAX_ACTIVE) {
            return $this->failed('Maksimal '.self::MAX_ACTIVE.' penandatangan aktif dalam satu laporan.');
        }

        $project->signatories()->create($data);

        return $this->saved('Penandatangan ditambahkan.');
    }

    public function update(Request $request, ReportSignatory $signatory): JsonResponse
    {
        $project = $this->own($signatory);
        $data = $this->validated($request);

        $activeOthers = $project->signatories()->where('is_active', true)->whereKeyNot($signatory->id)->count();
        if ($data['is_active'] && $activeOthers >= self::MAX_ACTIVE) {
            return $this->failed('Maksimal '.self::MAX_ACTIVE.' penandatangan aktif dalam satu laporan.');
        }

        $signatory->update($data);

        return $this->saved('Penandatangan diperbarui.');
    }

    public function destroy(ReportSignatory $signatory): JsonResponse
    {
        $this->own($signatory);
        $signatory->delete();

        return $this->saved('Penandatangan dihapus.');
    }

    private function validated(Request $request): array
    {
        $project = $this->managedProject();

        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'employee_id' => ['nullable', Rule::exists('project_employee', 'employee_id')->where('project_id', $project->id)],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'between:1,10'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => false];
    }

    private function own(ReportSignatory $signatory)
    {
        $project = $this->managedProject();
        abort_unless($signatory->project_id === $project->id, 404);

        return $project;
    }
}
