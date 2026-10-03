<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AttendanceException;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\ProjectEmployee;
use App\Services\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Menu Team Leader. */
class TeamController extends ApiController
{
    public function today(Request $request): JsonResponse
    {
        $project = $this->leaderProject($request, $request->query('project_id'));
        $date = CarbonImmutable::now($project->timezone)->toDateString();

        $attendances = Attendance::query()
            ->with(['project', 'shift'])
            ->where('project_id', $project->id)
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employee_id');

        $members = ProjectEmployee::query()
            ->with('employee.position')
            ->where('project_id', $project->id)
            ->activeOn($date)
            ->get()
            ->sortBy('employee.full_name')
            ->values()
            ->map(fn (ProjectEmployee $m) => [
                'employee_id' => $m->employee_id,
                'name' => $m->employee->display_name,
                'position' => $m->employee->position?->name,
                'attendance' => ($a = $attendances->get($m->employee_id)) ? new AttendanceResource($a) : null,
            ]);

        return $this->ok([
            'date' => $date,
            'summary' => [
                'total' => $members->count(),
                'checked_in' => $attendances->whereNotNull('check_in_at')->count(),
                'offsite' => $attendances->where('check_in_mode', Attendance::MODE_OFFSITE)->count(),
                'late' => $attendances->where('status', Attendance::STATUS_TERLAMBAT)->count(),
            ],
            'members' => $members,
        ]);
    }

    public function approvals(Request $request): JsonResponse
    {
        $projectIds = $this->leaderProjectIds($request);

        $leaves = LeaveRequest::query()
            ->with('employee')
            ->whereIn('project_id', $projectIds)
            ->where('status', 'pending')
            ->oldest()
            ->get()
            ->map(fn ($l) => LeaveRequestController::present($l));

        $offsite = Attendance::query()
            ->with(['project', 'shift', 'employee.position'])
            ->whereIn('project_id', $projectIds)
            ->where('offsite_approval', 'pending')
            ->oldest('work_date')
            ->get();

        return $this->ok([
            'leave_requests' => $leaves,
            'offsite_attendances' => AttendanceResource::collection($offsite),
        ]);
    }

    public function decideLeave(Request $request, LeaveRequest $leave, LeaveService $service): JsonResponse
    {
        $data = $this->decision($request);
        $this->leaderProject($request, $leave->project_id);

        try {
            $data['action'] === 'approve'
                ? $service->approve($leave, $request->user(), $data['note'] ?? null)
                : $service->reject($leave, $request->user(), $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 409, 'already_processed');
        }

        return $this->ok(LeaveRequestController::present($leave->fresh()), 'Pengajuan diproses.');
    }

    public function decideOffsite(Request $request, Attendance $attendance): JsonResponse
    {
        $data = $this->decision($request);
        $this->leaderProject($request, $attendance->project_id);

        if ($attendance->offsite_approval !== 'pending') {
            throw new AttendanceException('Presensi ini tidak menunggu persetujuan.', 'already_processed', 409);
        }

        $attendance->update([
            'offsite_approval' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'offsite_approved_by' => $request->user()->id,
            'offsite_approved_at' => now(),
            'note' => $data['note'] ?? $attendance->note,
        ]);

        return $this->ok(new AttendanceResource($attendance->load(['project', 'shift'])), 'Presensi luar lokasi diproses.');
    }

    private function decision(Request $request): array
    {
        return $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function leaderProject(Request $request, int|string|null $projectId)
    {
        abort_unless(in_array((int) $projectId, $this->leaderProjectIds($request), true), 403, 'Khusus Team Leader proyek ini.');

        return $this->memberProject($request, $projectId);
    }
}
