<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaveService
{
    public function approve(LeaveRequest $leave, User $approver, ?string $note = null): LeaveRequest
    {
        $this->assertPending($leave);

        return DB::transaction(function () use ($leave, $approver, $note) {
            $leave->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);

            // Tandai hari-hari izin; hari yang sudah ada check-in tidak diubah
            foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $date) {
                $attendance = Attendance::firstOrNew([
                    'employee_id' => $leave->employee_id,
                    'project_id' => $leave->project_id,
                    'work_date' => $date->toDateString(),
                ]);

                if ($attendance->check_in_at) {
                    continue;
                }

                $attendance->status = $leave->type;
                $attendance->leave_request_id = $leave->id;
                $attendance->save();
            }

            return $leave;
        });
    }

    public function reject(LeaveRequest $leave, User $approver, ?string $note = null): LeaveRequest
    {
        $this->assertPending($leave);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_note' => $note,
        ]);

        return $leave;
    }

    private function assertPending(LeaveRequest $leave): void
    {
        if ($leave->status !== 'pending') {
            throw new InvalidArgumentException('Pengajuan ini sudah diproses.');
        }
    }
}
