<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    public function test_login_returns_token_and_profile(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);

        $this->postJson('/api/v1/auth/login', [
            'username' => $employee->user->username,
            'password' => 'password',
            'device_id' => 'device-A',
            'platform' => 'android',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.projects.0.id', $project->id)
            ->assertJsonStructure(['data' => ['token', 'profile' => ['user', 'employee', 'projects']]]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $employee = $this->makeEmployee($this->makeProject());

        $this->postJson('/api/v1/auth/login', [
            'username' => $employee->user->username, 'password' => 'salah', 'device_id' => 'device-A',
        ])->assertStatus(401)->assertJsonPath('reason', 'invalid_credentials');
    }

    public function test_device_binding_blocks_second_device(): void
    {
        $employee = $this->makeEmployee($this->makeProject());
        $login = fn (string $device) => $this->postJson('/api/v1/auth/login', [
            'username' => $employee->user->username, 'password' => 'password', 'device_id' => $device,
        ]);

        $login('device-A')->assertOk();
        $login('device-A')->assertOk(); // perangkat yang sama boleh login ulang
        $login('device-B')->assertStatus(403)->assertJsonPath('reason', 'device_mismatch');
    }

    public function test_inactive_user_token_is_rejected(): void
    {
        $employee = $this->makeEmployee($this->makeProject());
        $token = $employee->user->createToken('device-A')->plainTextToken;
        $employee->user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(403)->assertJsonPath('reason', 'inactive');
    }
}
