<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class LeaveAndTeamTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    public function test_leave_request_flow_with_team_leader_approval(): void
    {
        $this->travelTo('2026-03-10 09:00:00');
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        $leader = $this->makeEmployee($project, ['is_team_leader' => true]);

        Sanctum::actingAs($employee->user);
        $id = $this->postJson('/api/v1/leave-requests', [
            'project_id' => $project->id, 'type' => 'sakit',
            'start_date' => '2026-03-11', 'end_date' => '2026-03-12', 'reason' => 'Demam',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('reason', null)->json('data.id');
        $this->getJson("/api/v1/leave-requests?project_id={$project->id}")->assertJsonPath('data.0.employee', 'Karyawan Uji');

        $this->postJson('/api/v1/leave-requests', [
            'project_id' => $project->id, 'type' => 'izin',
            'start_date' => '2026-03-12', 'end_date' => '2026-03-12', 'reason' => 'x',
        ])->assertStatus(422)->assertJsonPath('reason', 'overlap');

        // Karyawan biasa tidak bisa approve
        $this->patchJson("/api/v1/approvals/leave/{$id}", ['action' => 'approve'])->assertStatus(403);

        Sanctum::actingAs($leader->user);
        $this->getJson('/api/v1/approvals')->assertJsonCount(1, 'data.leave_requests');
        $this->patchJson("/api/v1/approvals/leave/{$id}", ['action' => 'approve'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->patchJson("/api/v1/approvals/leave/{$id}", ['action' => 'approve'])->assertStatus(409);

        $this->assertSame(['sakit', 'sakit'], Attendance::where('employee_id', $employee->id)->orderBy('work_date')->pluck('status')->all());
        $this->assertSame('approved', LeaveRequest::find($id)->status);
    }

    public function test_team_today_summary(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-03-10 08:00:00');
        $project = $this->makeProject(['allow_offsite' => true, 'offsite_scope' => 'all']);
        $leader = $this->makeEmployee($project, ['is_team_leader' => true]);
        $member = $this->makeEmployee($project);

        Sanctum::actingAs($member->user);
        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, [
            'latitude' => $this->latNorth(1500), 'note' => 'Survei',
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->getJson("/api/v1/team/today?project_id={$project->id}")->assertStatus(403);

        Sanctum::actingAs($leader->user);
        $this->getJson("/api/v1/team/today?project_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.summary', ['total' => 2, 'checked_in' => 1, 'offsite' => 1, 'late' => 0]);
    }

    public function test_history_defaults_to_current_period(): void
    {
        $this->travelTo('2026-03-20 09:00:00');
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        Attendance::create(['employee_id' => $employee->id, 'project_id' => $project->id, 'work_date' => '2026-03-02', 'status' => 'alpha']);
        Attendance::create(['employee_id' => $employee->id, 'project_id' => $project->id, 'work_date' => '2026-02-20', 'status' => 'alpha']);

        Sanctum::actingAs($employee->user);
        $this->getJson("/api/v1/attendance/history?project_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.from', '2026-02-25')
            ->assertJsonPath('data.to', '2026-03-26')
            ->assertJsonCount(1, 'data.items');

        $this->getJson("/api/v1/projects/{$project->id}/periods")
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Bulan ke-1')
            ->assertJsonPath('data.0.is_current', true);
    }
}
