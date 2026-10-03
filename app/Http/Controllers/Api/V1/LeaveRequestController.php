<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['project_id' => ['required', 'integer']]);
        $project = $this->memberProject($request, $request->query('project_id'));

        $items = LeaveRequest::query()
            ->with('employee')
            ->where('employee_id', $this->employee($request)->id)
            ->where('project_id', $project->id)
            ->latest('start_date')
            ->limit(100)
            ->get()
            ->map(fn (LeaveRequest $l) => self::present($l));

        return $this->ok($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'type' => ['required', 'in:'.implode(',', LeaveRequest::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $project = $this->memberProject($request, $data['project_id']);
        $employee = $this->employee($request);

        $overlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('project_id', $project->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            return $this->fail('Sudah ada pengajuan pada rentang tanggal tsb.', 422, 'overlap');
        }

        $leave = LeaveRequest::create([
            'project_id' => $project->id,
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'attachment' => $request->file('attachment')?->store("leave-requests/{$project->id}"),
            'status' => 'pending',
        ]);

        return $this->ok(self::present($leave), 'Pengajuan berhasil dikirim.', 201);
    }

    public static function present(LeaveRequest $l): array
    {
        return [
            'id' => $l->id,
            'project_id' => $l->project_id,
            'employee' => $l->employee?->display_name,
            'type' => $l->type,
            'start_date' => $l->start_date->toDateString(),
            'end_date' => $l->end_date->toDateString(),
            'reason' => $l->reason,
            'has_attachment' => (bool) $l->attachment,
            'status' => $l->status,
            'approval_note' => $l->approval_note,
            'approved_at' => $l->approved_at?->toIso8601String(),
            'created_at' => $l->created_at?->toIso8601String(),
        ];
    }
}
