<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectSettingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SignatoryController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'show'])->name('login');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
Route::get('branding/logo', [AppSettingController::class, 'logo'])->name('branding.logo');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::put('account/password', [AccountController::class, 'password'])->name('account.password');
    Route::post('projects/{project}/switch', [ProjectController::class, 'switch'])->name('projects.switch');

    // Monitoring presensi
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('attendances/data', [AttendanceController::class, 'data'])->name('attendances.data');
    Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');
    Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    Route::get('attendances/{attendance}/photo/{side}', [AttendanceController::class, 'photo'])->name('attendances.photo');

    // Persetujuan
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/leaves', [ApprovalController::class, 'leaves'])->name('approvals.leaves');
    Route::get('approvals/offsite', [ApprovalController::class, 'offsite'])->name('approvals.offsite');
    Route::post('approvals/leaves/{leave}', [ApprovalController::class, 'decideLeave'])->name('approvals.leaves.decide');
    Route::post('approvals/offsite/{attendance}', [ApprovalController::class, 'decideOffsite'])->name('approvals.offsite.decide');
    Route::get('approvals/leaves/{leave}/attachment', [ApprovalController::class, 'attachment'])->name('approvals.leaves.attachment');

    // Laporan
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/history', [ReportController::class, 'history'])->name('reports.history');
    Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    Route::delete('reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    // Data proyek
    Route::get('employees/data', [EmployeeController::class, 'data'])->name('employees.data');
    Route::get('employees/search', [EmployeeController::class, 'search'])->name('employees.search');
    Route::post('employees/attach', [EmployeeController::class, 'attach'])->name('employees.attach');
    Route::post('employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword'])->name('employees.reset-password');
    Route::post('employees/{employee}/reset-device', [EmployeeController::class, 'resetDevice'])->name('employees.reset-device');
    Route::delete('employees/{employee}/assignment', [EmployeeController::class, 'detach'])->name('employees.detach');
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'show', 'update']);

    Route::resource('shifts', ShiftController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
    Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);

    // Pengaturan
    Route::get('settings/project', [ProjectSettingController::class, 'edit'])->name('settings.project');
    Route::put('settings/project/{group}', [ProjectSettingController::class, 'update'])->name('settings.project.update');
    Route::resource('signatories', SignatoryController::class)->only(['store', 'update', 'destroy']);

    Route::resource('positions', PositionController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update']);
    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->only(['index', 'store', 'show', 'update']);

    Route::get('settings/app', [AppSettingController::class, 'edit'])->name('settings.app');
    Route::post('settings/app', [AppSettingController::class, 'update'])->name('settings.app.update');
});
