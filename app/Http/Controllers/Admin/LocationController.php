<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProjectLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends AdminController
{
    public function index(): View
    {
        $project = $this->managedProject();

        return view('locations.index', [
            'page' => 'locations',
            'title' => 'Titik Lokasi',
            'locations' => $project->locations()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->managedProject()->locations()->create($this->validated($request));

        return $this->saved('Titik lokasi ditambahkan.');
    }

    public function update(Request $request, ProjectLocation $location): JsonResponse
    {
        $this->own($location);
        $data = $this->validated($request);

        if (! $data['is_active'] && $this->isLastActive($location)) {
            return $this->failed('Minimal harus ada satu titik lokasi aktif.');
        }

        $location->update($data);

        return $this->saved('Titik lokasi diperbarui.');
    }

    public function destroy(ProjectLocation $location): JsonResponse
    {
        $this->own($location);

        if ($this->isLastActive($location)) {
            return $this->failed('Minimal harus ada satu titik lokasi aktif agar karyawan bisa presensi.');
        }

        $location->delete();

        return $this->saved('Titik lokasi dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => false];
    }

    private function isLastActive(ProjectLocation $location): bool
    {
        return $location->is_active
            && ! ProjectLocation::where('project_id', $location->project_id)->whereKeyNot($location->id)->where('is_active', true)->exists();
    }

    private function own(ProjectLocation $location): void
    {
        abort_unless($location->project_id === $this->managedProject()->id, 404);
    }
}
