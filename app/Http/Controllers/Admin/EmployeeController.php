<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Project;
use App\Models\ProjectEmployee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends AdminController
{
    public function index(): View
    {
        $project = $this->managedProject();
        $today = now($project->timezone)->toDateString();
        $assignments = ProjectEmployee::query()->where('project_id', $project->id);

        return view('employees.index', [
            'page' => 'employees',
            'title' => 'Karyawan',
            'positions' => Position::orderBy('name')->get(),
            'shifts' => $project->shifts()->where('is_active', true)->orderByDesc('is_default')->get(),
            'stats' => [
                'active' => (clone $assignments)->activeOn($today)->count(),
                'leaders' => (clone $assignments)->activeOn($today)->where('is_team_leader', true)->count(),
                'devices' => (clone $assignments)->activeOn($today)
                    ->whereHas('employee.user.devices', fn ($q) => $q->where('is_active', true))->count(),
                'ended' => (clone $assignments)->whereIn('employee_id', Employee::select('id'))
                    ->whereNotNull('end_date')->whereDate('end_date', '<', $today)->count(),
                'archived' => (clone $assignments)->whereIn('employee_id', Employee::onlyTrashed()->select('id'))->count(),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $today = now($project->timezone)->toDateString();
        $status = $request->input('status', 'active');

        $query = ProjectEmployee::query()
            ->select('project_employee.*')
            ->join('employees', 'employees.id', '=', 'project_employee.employee_id')
            ->leftJoin('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('positions', 'positions.id', '=', 'employees.position_id')
            ->with(['employee.position', 'employee.user.devices' => fn ($q) => $q->where('is_active', true), 'shift'])
            ->where('project_employee.project_id', $project->id)
            ->when($status === 'archived',
                fn ($q) => $q->whereNotNull('employees.deleted_at'),
                fn ($q) => $q->whereNull('employees.deleted_at'))
            ->when($status === 'active', fn ($q) => $q->activeOn($today)->where('employees.is_active', true))
            ->when($status === 'ended', fn ($q) => $q->where(fn ($w) => $w
                ->whereDate('project_employee.end_date', '<', $today)->orWhere('employees.is_active', false)));

        return DataTables::eloquent($query)
            ->addColumn('name', fn ($a) => $a->employee->display_name)
            ->addColumn('full_name', fn ($a) => $a->employee->full_name)
            ->addColumn('username', fn ($a) => $a->employee->user?->username)
            ->addColumn('position', fn ($a) => $a->employee->position?->name)
            ->addColumn('phone', fn ($a) => $a->employee->phone)
            ->addColumn('nik', fn ($a) => $a->employee->nik)
            ->addColumn('shift', fn ($a) => $a->shift?->name)
            ->addColumn('start', fn ($a) => $a->start_date?->translatedFormat('j M Y'))
            ->addColumn('end', fn ($a) => $a->end_date?->translatedFormat('j M Y'))
            ->addColumn('device', function ($a) {
                $device = $a->employee->user?->devices->first();

                return $device ? trim(($device->model ?: 'Perangkat').' · '.strtoupper((string) $device->platform), ' ·') : null;
            })
            ->addColumn('is_active', fn ($a) => $a->employee->is_active && ($a->end_date === null || $a->end_date->toDateString() >= now()->toDateString()))
            ->addColumn('archived', fn ($a) => $a->employee->trashed())
            ->addColumn('archived_at', fn ($a) => $a->employee->deleted_at?->translatedFormat('j M Y'))
            ->filterColumn('name', fn ($q, $kw) => $q->where(fn ($w) => $w
                ->where('employees.full_name', 'like', "%{$kw}%")
                ->orWhere('users.username', 'like', "%{$kw}%")
                ->orWhere('employees.nik', 'like', "%{$kw}%")
                ->orWhere('positions.name', 'like', "%{$kw}%")))
            ->orderColumn('name', 'employees.full_name $1')
            ->orderColumn('position', 'positions.name $1')
            ->orderColumn('start', 'project_employee.start_date $1')
            ->toJson();
    }

    /** Pencarian karyawan dari proyek lain (untuk ditambahkan ke proyek ini). */
    public function search(Request $request): JsonResponse
    {
        $project = $this->managedProject();

        $items = Employee::query()
            ->with('position')
            ->where('is_active', true)
            ->whereDoesntHave('projects', fn ($q) => $q->where('projects.id', $project->id))
            ->when($request->input('q'), fn ($q, $kw) => $q->where('full_name', 'like', "%{$kw}%"))
            ->orderBy('full_name')
            ->limit(20)
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'text' => $e->display_name.($e->position ? ' — '.$e->position->name : '')]);

        return response()->json(['results' => $items]);
    }

    public function show(Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        $assignment = $this->assignmentOrFail($project, $employee);
        $employee->load('user');

        return response()->json([
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'title_prefix' => $employee->title_prefix,
            'title_suffix' => $employee->title_suffix,
            'nik' => $employee->nik,
            'phone' => $employee->phone,
            'position_id' => $employee->position_id,
            'is_active' => $employee->is_active,
            'username' => $employee->user?->username,
            'email' => $employee->user?->email,
            'shift_id' => $assignment->shift_id,
            'start_date' => $assignment->start_date?->toDateString(),
            'end_date' => $assignment->end_date?->toDateString(),
            'allow_offsite' => $assignment->allow_offsite === null ? '' : (int) $assignment->allow_offsite,
            'is_team_leader' => $assignment->is_team_leader,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($data, $project) {
            $user = User::create([
                'name' => $data['full_name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);
            $user->assignRole(User::ROLE_EMPLOYEE);

            $employee = Employee::create($this->employeeAttributes($data) + ['user_id' => $user->id]);
            $project->employees()->attach($employee->id, $this->assignmentAttributes($data));
            $this->syncLeaderRole($user);
        });

        return $this->saved('Karyawan ditambahkan ke proyek.');
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        $assignment = $this->assignmentOrFail($project, $employee);
        $data = $this->validated($request, $project, $employee);

        DB::transaction(function () use ($data, $employee, $assignment) {
            $employee->update($this->employeeAttributes($data) + ['is_active' => $data['is_active'] ?? true]);

            $user = $employee->user ?? new User;
            $user->fill([
                'name' => $data['full_name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            if (! $employee->user_id) {
                $employee->update(['user_id' => $user->id]);
                $user->assignRole(User::ROLE_EMPLOYEE);
            }
            if (! $user->is_active) {
                $user->tokens()->delete();
            }

            $assignment->update($this->assignmentAttributes($data));
            $this->syncLeaderRole($user);
        });

        return $this->saved('Data karyawan diperbarui.');
    }

    public function attach(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->whereNull('deleted_at')],
        ] + $this->assignmentRules($project));

        abort_if($project->employees()->whereKey($data['employee_id'])->exists(), 422, 'Karyawan sudah terdaftar di proyek ini.');

        $project->employees()->attach($data['employee_id'], $this->assignmentAttributes($data));
        if ($user = Employee::find($data['employee_id'])->user) {
            $this->syncLeaderRole($user);
        }

        return $this->saved('Karyawan ditambahkan ke proyek.');
    }

    /** Keluarkan dari proyek: bila sudah punya presensi, penugasan diakhiri (riwayat tetap ada). */
    public function detach(Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        $assignment = $this->assignmentOrFail($project, $employee);

        $hasHistory = Attendance::where('project_id', $project->id)->where('employee_id', $employee->id)->exists();
        if ($hasHistory) {
            $assignment->update(['end_date' => now($project->timezone)->subDay()->toDateString(), 'is_team_leader' => false]);
            $message = 'Penugasan diakhiri. Riwayat presensi tetap tersimpan.';
        } else {
            $assignment->delete();
            $message = 'Karyawan dikeluarkan dari proyek.';
        }

        if ($employee->user) {
            $this->syncLeaderRole($employee->user);
        }

        return $this->saved($message);
    }

    /**
     * Hapus karyawan.
     * - Belum punya riwayat presensi/izin → hapus permanen beserta akunnya (mis. salah input).
     * - Sudah punya riwayat → diarsipkan: penugasan diakhiri, akun dinonaktifkan, riwayat tetap utuh.
     * Karyawan yang juga terdaftar di proyek lain hanya boleh dihapus super admin.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        $this->assignmentOrFail($project, $employee);
        $this->ensureCanManageAcrossProjects($project, $employee, 'dihapus');

        if ($employee->attendances()->exists() || $employee->leaveRequests()->exists()) {
            $this->archive($employee);

            return $this->saved('Karyawan diarsipkan. Riwayat presensi tetap tersimpan dan data bisa dipulihkan dari tab Arsip.');
        }

        DB::transaction(function () use ($employee) {
            if ($user = $employee->user) {
                $user->tokens()->delete();
                $user->syncRoles([]);
                $user->delete();
            }
            $employee->forceDelete();
        });

        return $this->saved('Karyawan dihapus permanen.');
    }

    /** Pulihkan dari arsip. Tanggal penugasan tidak diubah otomatis agar hari jeda tidak terhitung alpha. */
    public function restore(Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        abort_unless($employee->trashed(), 422, 'Karyawan tidak sedang diarsipkan.');
        $this->assignmentOrFail($project, $employee);
        $this->ensureCanManageAcrossProjects($project, $employee, 'dipulihkan');

        DB::transaction(function () use ($employee) {
            $employee->restore();
            $employee->update(['is_active' => true]);
            $employee->user?->update(['is_active' => true]);
        });

        return $this->saved('Karyawan dipulihkan. Atur ulang tanggal penugasan bila akan bertugas kembali.', ['id' => $employee->id]);
    }

    public function resetPassword(Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        $this->assignmentOrFail($project, $employee);
        abort_unless($employee->user, 422, 'Karyawan belum memiliki akun.');

        $password = Str::password(10, symbols: false);
        $employee->user->update(['password' => $password]);
        $employee->user->tokens()->delete();

        return $this->saved('Password direset.', ['username' => $employee->user->username, 'password' => $password]);
    }

    public function resetDevice(Employee $employee): JsonResponse
    {
        $project = $this->managedProject();
        $this->assignmentOrFail($project, $employee);
        abort_unless($employee->user, 422, 'Karyawan belum memiliki akun.');

        $employee->user->devices()->update(['is_active' => false]);
        $employee->user->tokens()->delete();

        return $this->saved('Perangkat direset. Karyawan dapat login di HP baru.');
    }

    private function validated(Request $request, Project $project, ?Employee $employee = null): array
    {
        $userId = $employee?->user_id;

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'title_prefix' => ['nullable', 'string', 'max:50'],
            'title_suffix' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'nik')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:30'],
            'position_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$employee ? 'nullable' : 'required', Password::min(8)],
        ] + $this->assignmentRules($project), [
            'username.regex' => 'Username hanya huruf kecil, angka, titik, strip, atau garis bawah.',
        ]);

        // Jabatan: id yang ada, atau nama baru (Select2 tags)
        if (! empty($data['position_id']) && ! ctype_digit((string) $data['position_id'])) {
            $data['position_id'] = Position::firstOrCreate(['name' => trim($data['position_id'])])->id;
        }

        return $data;
    }

    private function assignmentRules(Project $project): array
    {
        return [
            'shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('project_id', $project->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'allow_offsite' => ['nullable', 'in:0,1'],
            'is_team_leader' => ['nullable', 'boolean'],
        ];
    }

    private function employeeAttributes(array $data): array
    {
        return [
            'full_name' => $data['full_name'],
            'title_prefix' => $data['title_prefix'] ?? null,
            'title_suffix' => $data['title_suffix'] ?? null,
            'nik' => $data['nik'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position_id' => $data['position_id'] ?? null,
        ];
    }

    private function assignmentAttributes(array $data): array
    {
        return [
            'shift_id' => $data['shift_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'allow_offsite' => isset($data['allow_offsite']) && $data['allow_offsite'] !== '' ? (bool) $data['allow_offsite'] : null,
            'is_team_leader' => (bool) ($data['is_team_leader'] ?? false),
        ];
    }

    private function assignmentOrFail(Project $project, Employee $employee): ProjectEmployee
    {
        return ProjectEmployee::where('project_id', $project->id)->where('employee_id', $employee->id)->firstOrFail();
    }

    private function ensureCanManageAcrossProjects(Project $project, Employee $employee, string $action): void
    {
        $inOtherProjects = $employee->projects()->where('projects.id', '!=', $project->id)->exists();

        abort_if($inOtherProjects && ! auth()->user()->isSuperAdmin(), 422,
            "Karyawan juga terdaftar di proyek lain sehingga hanya bisa {$action} oleh super admin. Gunakan \"Keluarkan dari proyek\".");
    }

    /** Arsipkan: akhiri semua penugasan, cabut akses aplikasi, lalu soft delete. */
    private function archive(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $assignments = ProjectEmployee::with('project')->where('employee_id', $employee->id)->get();
            foreach ($assignments as $assignment) {
                $yesterday = now($assignment->project->timezone)->subDay()->startOfDay();
                $ended = $assignment->end_date !== null && $assignment->end_date->lte($yesterday);
                $assignment->update([
                    'end_date' => $ended ? $assignment->end_date : max($yesterday, $assignment->start_date)->toDateString(),
                    'is_team_leader' => false,
                ]);
            }

            if ($user = $employee->user) {
                $user->update(['is_active' => false]);
                $user->tokens()->delete();
                $user->devices()->update(['is_active' => false]);
                $this->syncLeaderRole($user);
            }

            $employee->update(['is_active' => false]);
            $employee->delete();
        });
    }

    /** Role team_leader mengikuti ada/tidaknya penugasan TL aktif. */
    private function syncLeaderRole(User $user): void
    {
        $isLeader = ProjectEmployee::query()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_team_leader', true)
            ->exists();

        $isLeader ? $user->assignRole(User::ROLE_TEAM_LEADER) : $user->removeRole(User::ROLE_TEAM_LEADER);
    }
}
