<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends ApiController
{
    public function login(Request $request, SettingService $settings): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', 'in:android,ios'],
            'model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Username atau password salah.', 401, 'invalid_credentials');
        }
        if (! $user->is_active || ! $user->employee?->is_active) {
            return $this->fail('Akun tidak aktif atau tidak terhubung dengan data karyawan.', 403, 'inactive');
        }

        if ($settings->app('device_binding')) {
            $bound = $user->devices()->where('is_active', true)->where('device_id', '!=', $data['device_id'])->exists();
            if ($bound) {
                return $this->fail(
                    'Akun ini sudah terdaftar di perangkat lain. Hubungi admin untuk reset perangkat.',
                    403,
                    'device_mismatch',
                );
            }
        }

        UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $data['device_id']],
            [
                'platform' => $data['platform'] ?? null,
                'model' => $data['model'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        $user->tokens()->where('name', $data['device_id'])->delete();
        $token = $user->createToken($data['device_id'])->plainTextToken;
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'profile' => MeController::profile($user),
        ], 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(message: 'Logout berhasil.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return $this->ok(message: 'Password berhasil diubah.');
    }
}
