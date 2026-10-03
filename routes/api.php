<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('time', [MeController::class, 'time'])->name('time');
    Route::get('app/info', [MeController::class, 'appInfo'])->name('app.info');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me');
        Route::post('me/password', [AuthController::class, 'changePassword'])->name('me.password');

        Route::get('projects/{project}/config', [ProjectController::class, 'config'])->whereNumber('project')->name('projects.config');
        Route::get('projects/{project}/periods', [ProjectController::class, 'periods'])->whereNumber('project')->name('projects.periods');

        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('today', [AttendanceController::class, 'today'])->name('today');
            Route::get('history', [AttendanceController::class, 'history'])->name('history');
            Route::middleware('throttle:attendance')->group(function () {
                Route::post('check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
                Route::post('check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
                Route::post('sync', [AttendanceController::class, 'sync'])->name('sync');
            });
            Route::get('{attendance}', [AttendanceController::class, 'show'])->whereNumber('attendance')->name('show');
            Route::get('{attendance}/photo/{side}', [AttendanceController::class, 'photo'])->whereNumber('attendance')->name('photo');
        });

        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');

        Route::get('team/today', [TeamController::class, 'today'])->name('team.today');
        Route::get('approvals', [TeamController::class, 'approvals'])->name('approvals.index');
        Route::patch('approvals/leave/{leave}', [TeamController::class, 'decideLeave'])->name('approvals.leave');
        Route::patch('approvals/offsite/{attendance}', [TeamController::class, 'decideOffsite'])->name('approvals.offsite');
    });
});
