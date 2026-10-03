<?php

namespace App\Http\Controllers\Admin;

use App\Http\Middleware\AdminContext;
use App\Models\Position;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends AdminController
{
    public function index(): View
    {
        $this->authorizeManage();

        return view('positions.index', [
            'page' => 'positions',
            'title' => 'Jabatan',
            'positions' => Position::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage();
        Position::create($request->validate(['name' => ['required', 'string', 'max:255', 'unique:positions,name']]));

        return $this->saved('Jabatan ditambahkan.');
    }

    public function update(Request $request, Position $position): JsonResponse
    {
        $this->authorizeManage();
        $position->update($request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($position)]]));

        return $this->saved('Jabatan diperbarui.');
    }

    public function destroy(Position $position): JsonResponse
    {
        $this->authorizeManage();

        if ($position->employees()->exists()) {
            return $this->failed('Jabatan masih dipakai karyawan.');
        }
        $position->delete();

        return $this->saved('Jabatan dihapus.');
    }

    /** Jabatan dipakai lintas proyek: super admin atau admin proyek mana pun. */
    private function authorizeManage(): void
    {
        $user = auth()->user();
        $project = AdminContext::project();

        abort_unless($user->isSuperAdmin() || ($project && app(ProjectAccess::class)->canManage($user, $project)), 403);
    }
}
