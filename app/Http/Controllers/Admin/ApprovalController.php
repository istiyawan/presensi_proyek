<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

/** Super admin, admin proyek, dan Team Leader proyek boleh memproses persetujuan. */
class ApprovalController extends AdminController
{
    public function index(Request $request, SettingService $settings): View
    {
        $project = $this->project();

        return view('approvals.index', [
            'page' => 'approvals',
            'title' => 'Persetujuan',
            'tab' => $request->query('tab', 'leave'),
            'offsiteApprovalEnabled' => (bool) $settings->project($project, 'offsite_requires_approval'),
            'counts' => [
                'leave' => LeaveRequest::where('project_id', $project->id)->where('status', 'pending')->count(),
                'offsite' => Attendance::where('project_id', $project->id)->where('offsite_approval', 'pending')->count(),
            ],
        ]);
    }

    public function leaves(Request $request): JsonResponse
    {
        $project = $this->project();

        $query = LeaveRequest::query()
            ->select('leave_requests.*')
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->with(['employee.position', 'approver'])
            ->where('leave_requests.project_id', $project->id)
            ->when($request->input('status', 'pending') !== 'all', fn ($q) => $q->where('leave_requests.status', $request->input('status', 'pending')));

        return DataTables::eloquent($query)
            ->addColumn('name', fn ($l) => $l->employee->display_name)
            ->addColumn('full_name', fn ($l) => $l->employee->full_name)
            ->addColumn('position', fn ($l) => $l->employee->position?->name)
            ->addColumn('range', fn ($l) => $l->start_date->equalTo($l->end_date)
                ? $l->start_date->translatedFormat('j M Y')
                : $l->start_date->translatedFormat('j M').' – '.$l->end_date->translatedFormat('j M Y'))
            ->addColumn('days', fn ($l) => (int) $l->start_date->diffInDays($l->end_date) + 1)
            ->addColumn('submitted', fn ($l) => $l->created_at->setTimezone($project->timezone)->translatedFormat('j M Y H:i'))
            ->addColumn('approver_name', fn ($l) => $l->approver?->name)
            ->addColumn('attachment_url', fn ($l) => $l->attachment ? route('approvals.leaves.attachment', $l) : null)
            ->filterColumn('name', fn ($q, $kw) => $q->where('employees.full_name', 'like', "%{$kw}%"))
            ->orderColumn('name', 'employees.full_name $1')
            ->only(['id', 'name', 'full_name', 'position', 'type', 'range', 'days', 'reason', 'status', 'submitted', 'approver_name', 'approval_note', 'attachment_url', 'start_date'])
            ->toJson();
    }

    public function offsite(Request $request): JsonResponse
    {
        $project = $this->project();
        $tz = $project->timezone;

        $query = Attendance::query()
            ->select('attendances.*')
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->with('employee.position')
            ->where('attendances.project_id', $project->id)
            ->where(fn ($q) => $q->where('check_in_mode', Attendance::MODE_OFFSITE)->orWhere('check_out_mode', Attendance::MODE_OFFSITE))
            ->when($request->input('status', 'pending') !== 'all', fn ($q) => $q->where('offsite_approval', $request->input('status', 'pending')));

        return DataTables::eloquent($query)
            ->addColumn('name', fn ($a) => $a->employee->display_name)
            ->addColumn('full_name', fn ($a) => $a->employee->full_name)
            ->addColumn('position', fn ($a) => $a->employee->position?->name)
            ->addColumn('date_label', fn ($a) => $a->work_date->translatedFormat('D, j M Y'))
            ->addColumn('in_time', fn ($a) => $a->check_in_at?->setTimezone($tz)->format('H:i'))
            ->addColumn('out_time', fn ($a) => $a->check_out_at?->setTimezone($tz)->format('H:i'))
            ->filterColumn('name', fn ($q, $kw) => $q->where('employees.full_name', 'like', "%{$kw}%"))
            ->orderColumn('name', 'employees.full_name $1')
            ->only(['id', 'name', 'full_name', 'position', 'work_date', 'date_label', 'in_time', 'out_time', 'check_in_mode', 'check_out_mode',
                'check_in_note', 'check_out_note', 'check_in_distance_m', 'check_out_distance_m', 'offsite_approval'])
            ->toJson();
    }

    public function decideLeave(Request $request, LeaveRequest $leave, LeaveService $service): JsonResponse
    {
        abort_unless($leave->project_id === $this->project()->id, 404);
        $data = $this->decision($request);

        try {
            $data['action'] === 'approve'
                ? $service->approve($leave, $request->user(), $data['note'] ?? null)
                : $service->reject($leave, $request->user(), $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage(), 409);
        }

        return $this->saved($data['action'] === 'approve' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    public function decideOffsite(Request $request, Attendance $attendance): JsonResponse
    {
        abort_unless($attendance->project_id === $this->project()->id, 404);
        $data = $this->decision($request);

        if ($attendance->offsite_approval !== 'pending') {
            return $this->failed('Presensi ini tidak menunggu persetujuan.', 409);
        }

        $attendance->update([
            'offsite_approval' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'offsite_approved_by' => $request->user()->id,
            'offsite_approved_at' => now(),
            'note' => $data['note'] ?: $attendance->note,
        ]);

        return $this->saved($data['action'] === 'approve' ? 'Presensi luar lokasi disetujui.' : 'Presensi luar lokasi ditolak.');
    }

    public function attachment(LeaveRequest $leave): StreamedResponse
    {
        abort_unless($leave->project_id === $this->project()->id && $leave->attachment, 404);

        return Storage::disk('local')->response($leave->attachment);
    }

    private function decision(Request $request): array
    {
        return $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'required_if:action,reject', 'string', 'max:500'],
        ], ['note.required_if' => 'Alasan penolakan wajib diisi.']);
    }
}
