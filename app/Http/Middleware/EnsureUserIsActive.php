<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tolak token milik user yang sudah dinonaktifkan admin. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan.',
                'reason' => 'inactive',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
