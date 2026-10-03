<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminContext;
use App\Models\Project;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;

abstract class AdminController extends Controller
{
    /** Proyek aktif (dipilih di topbar). */
    protected function project(): Project
    {
        $project = AdminContext::project();
        abort_unless($project, 404, 'Belum ada proyek. Buat proyek terlebih dahulu.');

        return $project;
    }

    /** Proyek aktif, wajib hak kelola (super admin / admin proyek). */
    protected function managedProject(): Project
    {
        $project = $this->project();
        abort_unless(app(ProjectAccess::class)->canManage(auth()->user(), $project), 403, 'Anda hanya memiliki akses lihat untuk proyek ini.');

        return $project;
    }

    protected function superAdminOnly(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Khusus super admin.');
    }

    protected function saved(string $message = 'Data berhasil disimpan.', mixed $data = null): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function failed(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
