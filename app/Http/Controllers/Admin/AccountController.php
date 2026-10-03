<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AccountController extends AdminController
{
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['current_password' => 'password saat ini', 'password' => 'password baru']);

        $request->user()->update(['password' => $data['password']]);

        return $this->saved('Password berhasil diubah.');
    }
}
