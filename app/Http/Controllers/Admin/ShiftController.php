<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProjectEmployee;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShiftController extends AdminController
{
    public function index(): View
    {
        $project = $this->managedProject();

        $shifts = $project->shifts()->orderByDesc('is_default')->orderBy('start_time')->get();
        $usage = ProjectEmployee::where('project_id', $project->id)
            ->selectRaw('shift_id, COUNT(*) as total')->groupBy('shift_id')->pluck('total', 'shift_id');

        return view('shifts.index', [
            'page' => 'shifts',
            'title' => 'Shift & Jam Kerja',
            'shifts' => $shifts,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $data = $this->validated($request);

        DB::transaction(function () use ($project, $data) {
            $shift = $project->shifts()->create($data);
            $this->ensureSingleDefault($shift);
        });

        return $this->saved('Shift ditambahkan.');
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->ownShift($shift);
        $data = $this->validated($request);

        DB::transaction(function () use ($shift, $data) {
            $shift->update($data);
            $this->ensureSingleDefault($shift);
        });

        return $this->saved('Shift diperbarui.');
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $project = $this->ownShift($shift);

        if (ProjectEmployee::where('shift_id', $shift->id)->exists() || $shift->is_default) {
            return $this->failed('Shift default atau yang masih dipakai karyawan tidak bisa dihapus. Nonaktifkan saja.');
        }
        if ($project->shifts()->count() <= 1) {
            return $this->failed('Proyek minimal memiliki satu shift.');
        }

        $shift->delete();

        return $this->saved('Shift dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'late_enabled' => ['boolean'],
            'late_tolerance_min' => ['nullable', 'integer', 'min:0', 'max:240'],
            'checkin_open_time' => ['nullable', 'date_format:H:i'],
            'checkin_close_time' => ['nullable', 'date_format:H:i'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ], [], ['checkin_open_time' => 'jam buka check-in', 'checkin_close_time' => 'jam tutup check-in', 'late_tolerance_min' => 'toleransi']);

        $data['late_tolerance_min'] = (int) ($data['late_tolerance_min'] ?? 0);

        return $data;
    }

    private function ensureSingleDefault(Shift $shift): void
    {
        $siblings = Shift::where('project_id', $shift->project_id)->whereKeyNot($shift->id);

        if ($shift->is_default) {
            $siblings->update(['is_default' => false]);
        } elseif (! (clone $siblings)->where('is_default', true)->exists()) {
            $shift->update(['is_default' => true]);
        }
    }

    private function ownShift(Shift $shift)
    {
        $project = $this->managedProject();
        abort_unless($shift->project_id === $project->id, 404);

        return $project;
    }
}
