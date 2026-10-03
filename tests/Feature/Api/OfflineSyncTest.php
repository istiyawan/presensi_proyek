<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Services\DailyCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function item(string $type, $project, string $capturedAt, array $extra = []): array
    {
        return $this->attendancePayload($project, $extra + [
            'type' => $type,
            'is_offline' => 'true',
            'captured_at' => $capturedAt,
            'device' => json_encode(['model' => 'Uji']),
        ]);
    }

    public function test_offline_batch_uses_captured_time_and_overwrites_alpha(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);

        // Hari ditutup scheduler sebelum data offline masuk → alpha
        $this->travelTo('2026-03-11 00:30:00');
        app(DailyCloseService::class)->close($project, CarbonImmutable::parse('2026-03-10'));
        $this->assertSame('alpha', Attendance::first()->status);

        $this->travelTo('2026-03-11 09:00:00');
        Sanctum::actingAs($employee->user);

        $response = $this->post('/api/v1/attendance/sync', ['items' => [
            $this->item('check_in', $project, '2026-03-10T07:58:00+07:00'),
            $this->item('check_out', $project, '2026-03-10T17:05:00+07:00'),
        ]], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('data.0.success', true)->assertJsonPath('data.1.success', true);

        $attendance = Attendance::sole();
        $this->assertSame('hadir', $attendance->status);
        $this->assertTrue($attendance->check_in_offline);
        $this->assertSame('2026-03-10 07:58:00', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(547, $attendance->duration_minutes);
    }

    public function test_resending_same_batch_does_not_duplicate(): void
    {
        $project = $this->makeProject();
        Sanctum::actingAs($this->makeEmployee($project)->user);
        $this->travelTo('2026-03-10 12:00:00');

        $item = $this->item('check_in', $project, '2026-03-10T08:00:00+07:00');
        $this->post('/api/v1/attendance/sync', ['items' => [$item]], ['Accept' => 'application/json'])->assertJsonPath('data.0.success', true);

        $item['photo'] = $this->attendancePayload($project)['photo'];
        $this->post('/api/v1/attendance/sync', ['items' => [$item]], ['Accept' => 'application/json'])->assertJsonPath('data.0.success', true);

        $this->assertSame(1, Attendance::count());
    }

    public function test_expired_and_future_offline_data_rejected_per_item(): void
    {
        $project = $this->makeProject(['offline_max_hours' => 72]);
        Sanctum::actingAs($this->makeEmployee($project)->user);
        $this->travelTo('2026-03-10 12:00:00');

        $this->post('/api/v1/attendance/sync', ['items' => [
            $this->item('check_in', $project, '2026-03-05T08:00:00+07:00'),
            $this->item('check_in', $project, '2026-03-10T14:00:00+07:00'),
            $this->item('check_in', $project, '2026-03-10T08:00:00+07:00'),
        ]], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'offline_expired')
            ->assertJsonPath('data.1.reason', 'invalid_time')
            ->assertJsonPath('data.2.success', true);
    }

    public function test_offline_low_accuracy_is_flagged_not_rejected(): void
    {
        $project = $this->makeProject(['max_gps_accuracy_m' => 30]);
        Sanctum::actingAs($this->makeEmployee($project)->user);
        $this->travelTo('2026-03-10 12:00:00');

        $this->post('/api/v1/attendance/sync', ['items' => [
            $this->item('check_in', $project, '2026-03-10T08:00:00+07:00', ['accuracy' => 90, 'flags' => json_encode(['time_suspicious'])]),
        ]], ['Accept' => 'application/json'])->assertJsonPath('data.0.success', true);

        $this->assertEqualsCanonicalizing(['time_suspicious', 'low_accuracy'], Attendance::sole()->flags);
    }

    public function test_offline_rejected_when_disabled(): void
    {
        $project = $this->makeProject(['allow_offline' => false]);
        Sanctum::actingAs($this->makeEmployee($project)->user);
        $this->travelTo('2026-03-10 12:00:00');

        $this->post('/api/v1/attendance/sync', ['items' => [
            $this->item('check_in', $project, '2026-03-10T08:00:00+07:00'),
        ]], ['Accept' => 'application/json'])->assertJsonPath('data.0.reason', 'offline_not_allowed');
    }
}
