<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-03-10 07:55:00');
    }

    public function test_onsite_check_in_and_check_out(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        Sanctum::actingAs($employee->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'hadir')
            ->assertJsonPath('data.check_in.mode', 'onsite')
            ->assertJsonPath('data.offsite_approval', 'none')
            ->assertJsonPath('data.work_date', '2026-03-10');

        $attendance = Attendance::first();
        Storage::disk('local')->assertExists([$attendance->check_in_photo, $attendance->check_in_thumb]);

        $this->travelTo('2026-03-10 17:23:00');
        $this->post('/api/v1/attendance/check-out', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', 568)
            ->assertJsonPath('data.duration_label', '9 jam 28 menit');
    }

    public function test_outside_radius_is_rejected_when_offsite_not_allowed(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['latitude' => $this->latNorth(500)]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'outside_radius')
            ->assertJsonPath('data.distance_m', 500);
    }

    public function test_offsite_is_only_flagged_by_default(): void
    {
        $project = $this->makeProject(['allow_offsite' => true]);
        Sanctum::actingAs($this->makeEmployee($project, ['allow_offsite' => true])->user);

        $payload = $this->attendancePayload($project, ['latitude' => $this->latNorth(2000)]);
        $this->post('/api/v1/attendance/check-in', $payload, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('reason', 'note_required');

        $payload = $this->attendancePayload($project, ['latitude' => $this->latNorth(2000), 'note' => 'Survei titik BM-12']);
        $this->post('/api/v1/attendance/check-in', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.check_in.mode', 'offsite')
            ->assertJsonPath('data.offsite_approval', 'none')
            ->assertJsonPath('data.status', 'hadir');
    }

    public function test_offsite_needs_approval_when_enabled(): void
    {
        $project = $this->makeProject(['allow_offsite' => true, 'offsite_scope' => 'all', 'offsite_requires_approval' => true]);
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, [
            'latitude' => $this->latNorth(2000), 'note' => 'Rapat di kantor PPK',
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.offsite_approval', 'pending');
    }

    public function test_employee_not_selected_cannot_check_in_offsite(): void
    {
        $project = $this->makeProject(['allow_offsite' => true, 'offsite_scope' => 'selected']);
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, [
            'latitude' => $this->latNorth(2000), 'note' => 'x',
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('reason', 'outside_radius');
    }

    public function test_mock_location_is_rejected(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['is_mock' => 'true']), ['Accept' => 'application/json'])
            ->assertStatus(403)->assertJsonPath('reason', 'mock_location');
    }

    public function test_low_gps_accuracy_is_rejected_online(): void
    {
        $project = $this->makeProject(['max_gps_accuracy_m' => 30]);
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['accuracy' => 80]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('reason', 'low_accuracy');
    }

    public function test_second_check_in_is_rejected_but_same_uuid_is_idempotent(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);
        $payload = $this->attendancePayload($project);

        $first = $this->post('/api/v1/attendance/check-in', $payload, ['Accept' => 'application/json'])->assertCreated();
        $payload['photo'] = $this->attendancePayload($project)['photo'];
        $this->post('/api/v1/attendance/check-in', $payload, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('reason', 'already_checked_in');
        $this->assertSame(1, Attendance::count());
    }

    public function test_late_status_follows_shift_setting(): void
    {
        $project = $this->makeProject(shift: ['late_enabled' => true, 'late_tolerance_min' => 15]);
        $onTime = $this->makeEmployee($project);
        $late = $this->makeEmployee($project);

        $this->travelTo('2026-03-10 08:15:00');
        Sanctum::actingAs($onTime->user);
        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertJsonPath('data.status', 'hadir');

        $this->travelTo('2026-03-10 10:30:00');
        Sanctum::actingAs($late->user);
        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertJsonPath('data.status', 'terlambat')
            ->assertJsonPath('data.late_minutes', 150);
    }

    public function test_late_disabled_keeps_status_hadir_like_reference_report(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);
        $this->travelTo('2026-03-26 10:30:00');

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertJsonPath('data.status', 'hadir');
    }

    public function test_check_out_without_check_in_is_rejected(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-out', $this->attendancePayload($project), ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('reason', 'no_checkin');
    }

    public function test_back_dated_check_in_and_check_out_use_the_chosen_date(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-08']), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.work_date', '2026-03-08')
            ->assertJsonPath('data.check_in.time', '2026-03-08T07:55:00+07:00')
            ->assertJsonPath('data.flags', ['backdated']);

        $this->travelTo('2026-03-10 17:23:00');
        $this->post('/api/v1/attendance/check-out', $this->attendancePayload($project, ['work_date' => '2026-03-08']), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.work_date', '2026-03-08')
            ->assertJsonPath('data.check_out.time', '2026-03-08T17:23:00+07:00')
            ->assertJsonPath('data.duration_minutes', 568);

        $this->assertSame(1, Attendance::count());
    }

    public function test_work_date_of_today_is_not_flagged_as_back_dated(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-10']), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.work_date', '2026-03-10')
            ->assertJsonPath('data.flags', []);
    }

    public function test_back_date_is_unlimited_by_default(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-01-05']), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.work_date', '2026-01-05');

        $this->getJson("/api/v1/projects/{$project->id}/config")
            ->assertOk()
            ->assertJsonPath('data.rules.backdate_max_days', null);
    }

    public function test_future_work_date_is_rejected(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-11']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('reason', 'invalid_work_date');

        $this->assertSame(0, Attendance::count());
    }

    public function test_work_date_older_than_the_project_limit_is_rejected(): void
    {
        $project = $this->makeProject(['backdate_max_days' => 3]);
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-06']), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'backdate_not_allowed')
            ->assertJsonPath('data.max_days', 3);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-07']), ['Accept' => 'application/json'])
            ->assertCreated();
    }

    public function test_second_check_in_for_the_same_back_date_is_rejected(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-08']), ['Accept' => 'application/json'])
            ->assertCreated();
        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '2026-03-08']), ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('reason', 'already_checked_in');
    }

    public function test_malformed_work_date_fails_validation(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project, ['work_date' => '08-03-2026']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('work_date');
    }

    public function test_employee_cannot_use_other_project(): void
    {
        $projectA = $this->makeProject();
        $projectB = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($projectA)->user);

        $this->post('/api/v1/attendance/check-in', $this->attendancePayload($projectB), ['Accept' => 'application/json'])
            ->assertStatus(403);
        $this->getJson("/api/v1/projects/{$projectB->id}/config")->assertStatus(403);
    }

    public function test_photo_only_visible_to_owner_and_team_leader(): void
    {
        $project = $this->makeProject();
        $owner = $this->makeEmployee($project);
        $other = $this->makeEmployee($project);
        $leader = $this->makeEmployee($project, ['is_team_leader' => true]);

        Sanctum::actingAs($owner->user);
        $id = $this->post('/api/v1/attendance/check-in', $this->attendancePayload($project), ['Accept' => 'application/json'])->json('data.id');

        $this->get("/api/v1/attendance/{$id}/photo/in?thumb=1")->assertOk();
        Sanctum::actingAs($leader->user);
        $this->get("/api/v1/attendance/{$id}/photo/in")->assertOk();
        Sanctum::actingAs($other->user);
        $this->getJson("/api/v1/attendance/{$id}/photo/in")->assertStatus(403);
    }
}
