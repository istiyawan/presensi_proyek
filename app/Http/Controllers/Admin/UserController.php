<?php

namespace App\Http\Controllers\Admin;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/** Akun web admin (super admin & admin proyek). Akun karyawan/TL dikelola di menu Karyawan. */
class UserController extends AdminController
{
    private const ROLES = [User::ROLE_SUPER_ADMIN, User::ROLE_PROJECT_ADMIN];

    public function index(): View
    {
        $this->superAdminOnly();

        return view('users.index', [
            'page' => 'users',
            'title' => 'Pengguna Admin',
            'projects' => Project::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function data(): JsonResponse
    {
        $this->superAdminOnly();

        $query = User::query()
            ->with(['roles', 'managedProjects:id,code'])
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES));

        return DataTables::eloquent($query)
            ->addColumn('role', fn (User $u) => $u->hasRole(User::ROLE_SUPER_ADMIN) ? User::ROLE_SUPER_ADMIN : User::ROLE_PROJECT_ADMIN)
            // ->all(): array biasa; Collection akan di-escape DataTables menjadi string JSON
            ->addColumn('projects', fn (User $u) => $u->managedProjects->pluck('code')->all())
            ->addColumn('last_login', fn (User $u) => $u->last_login_at?->diffForHumans())
            ->addColumn('is_me', fn (User $u) => $u->id === auth()->id())
            ->only(['id', 'name', 'username', 'email', 'is_active', 'role', 'projects', 'last_login', 'is_me'])
            ->toJson();
    }

    public function show(User $user): JsonResponse
    {
        $this->superAdminOnly();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->hasRole(User::ROLE_SUPER_ADMIN) ? User::ROLE_SUPER_ADMIN : User::ROLE_PROJECT_ADMIN,
            'project_ids' => $user->managedProjects()->pluck('projects.id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->superAdminOnly();
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncAccess($user, $data);
        });

        return $this->saved('Pengguna admin ditambahkan.');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->superAdminOnly();
        $data = $this->validated($request, $user);

        if ($user->id === auth()->id() && (! ($data['is_active'] ?? true) || $data['role'] !== User::ROLE_SUPER_ADMIN)) {
            return $this->failed('Anda tidak bisa menonaktifkan atau menurunkan peran akun sendiri.');
        }

        DB::transaction(function () use ($user, $data) {
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $this->syncAccess($user, $data);
        });

        return $this->saved('Pengguna admin diperbarui.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['required', Rule::in(self::ROLES)],
            'project_ids' => config('presensi.multi_project') ? ['nullable', 'required_if:role,project_admin', 'array'] : ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'username.regex' => 'Username hanya huruf kecil, angka, titik, strip, atau garis bawah.',
            'project_ids.required_if' => 'Pilih minimal satu proyek untuk admin proyek.',
        ]);

        // Mode 1 proyek: admin proyek otomatis mengelola satu-satunya proyek
        if (! config('presensi.multi_project')) {
            $data['project_ids'] = Project::pluck('id')->all();
        }

        return $data;
    }

    /** Ganti peran admin tanpa menyentuh peran karyawan/TL yang mungkin juga dimiliki. */
    private function syncAccess(User $user, array $data): void
    {
        foreach (self::ROLES as $role) {
            $user->removeRole($role);
        }
        $user->assignRole($data['role']);
        $user->managedProjects()->sync($data['role'] === User::ROLE_PROJECT_ADMIN ? ($data['project_ids'] ?? []) : []);
    }
}
