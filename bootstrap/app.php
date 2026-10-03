<?php

use App\Exceptions\AttendanceException;
use App\Http\Middleware\AdminContext;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$apiError = fn (string $message, int $status, ?string $reason = null, mixed $errors = null, mixed $data = null) => response()->json([
    'success' => false,
    'message' => $message,
    'reason' => $reason,
    'data' => $data,
    'errors' => $errors,
], $status);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => AdminContext::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) use ($apiError): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (AttendanceException $e) => $apiError($e->getMessage(), $e->status, $e->reason, data: $e->context ?: null));

        $exceptions->render(function (ValidationException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($e->validator->errors()->first(), 422, 'validation', $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('Sesi berakhir, silakan login kembali.', 401, 'unauthenticated');
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                $message = $e->getMessage() ?: match ($e->getStatusCode()) {
                    403 => 'Akses ditolak.',
                    404 => 'Data tidak ditemukan.',
                    429 => 'Terlalu banyak permintaan, coba lagi sebentar.',
                    default => 'Terjadi kesalahan.',
                };
                if ($e instanceof NotFoundHttpException) {
                    $message = 'Data tidak ditemukan.';
                }

                return $apiError($message, $e->getStatusCode(), 'http_'.$e->getStatusCode());
            }
        });
    })->create();
