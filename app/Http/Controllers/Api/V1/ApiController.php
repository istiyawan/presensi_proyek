<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Format respons seragam: { success, message, data, errors }.
 */
abstract class ApiController extends Controller
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'reason' => null,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    protected function fail(string $message, int $status = 422, ?string $reason = null, mixed $errors = null, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'reason' => $reason,
            'data' => $data,
            'errors' => $errors,
        ], $status);
    }

    protected function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->is_active, 403, 'Akun ini tidak terhubung dengan data karyawan aktif.');

        return $employee;
    }

    /** Proyek tempat karyawan pernah/sedang ditugaskan. */
    protected function memberProject(Request $request, int|string|null $projectId): Project
    {
        $employee = $this->employee($request);

        $assigned = ProjectEmployee::query()
            ->where('employee_id', $employee->id)
            ->where('project_id', $projectId)
            ->exists();
        abort_unless($assigned, 403, 'Anda tidak terdaftar di proyek ini.');

        return Project::findOrFail($projectId);
    }

    /** ID proyek tempat user menjadi Team Leader (aktif hari ini). */
    protected function leaderProjectIds(Request $request): array
    {
        return ProjectEmployee::query()
            ->where('employee_id', $this->employee($request)->id)
            ->where('is_team_leader', true)
            ->activeOn(now()->toDateString())
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
