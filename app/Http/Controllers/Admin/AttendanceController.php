<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\ProjectEmployee;
use App\Services\CalendarService;
use App\Services\GeoService;
use App\Services\PeriodService;
use App\Services\PhotoService;
use App\Services\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends AdminController
{
    private const STATUSES = ['hadir', 'terlambat', 'izin', 'sakit', 'cuti', 'alpha', 'libur'];

    public function index(Request $request, PeriodService $periods): View
    {
        $project = $this->project();
        $current = $periods->current($project) ?? $periods->period($project, 1);

        return view('attendances.index', [
            'page' => 'attendances',
            'title' => 'Monitoring Presensi',
            'from' => $request->query('from', $current['start']->toDateString()),
            'to' => $request->query('to', min($current['end'], CarbonImmutable::now($project->timezone))->toDateString()),
            'flag' => $request->query('flag'),
            'periods' => array_reverse($periods->periods($project)),
            'employees' => ProjectEmployee::with('employee')->where('project_id', $project->id)->get()
                ->map(fn ($a) => ['id' => $a->employee_id, 'name' => $a->employee->display_name])
                ->sortBy('name')->values(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $project = $this->project();
        $tz = $project->timezone;

        $query = Attendance::query()
            ->select('attendances.*')
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->with(['employee.position', 'shift'])
            ->where('attendances.project_id', $project->id);

        $this->applyFilters($query, $request);

        // Format kolom mengikuti laporan PDF (ReportService::individualRows)
        $time = fn ($dt) => $dt?->setTimezone($tz)->format('H:i:s');
        $coord = fn ($a, string $p) => $a->{"{$p}_lat"} !== null
            ? number_format($a->{"{$p}_lat"}, 4, '.', '').', '.number_format($a->{"{$p}_lng"}, 4, '.', '')
            : null;
        $photo = fn ($a, string $side) => $a->{($side === 'in' ? 'check_in' : 'check_out').'_photo'}
            ? route('attendances.photo', [$a, $side])
            : null;

        return DataTables::eloquent($query)
            ->addColumn('date_label', fn ($a) => $a->work_date->translatedFormat('D, j M Y'))
            ->addColumn('name', fn ($a) => $a->employee->display_name)
            ->addColumn('full_name', fn ($a) => $a->employee->full_name)
            ->addColumn('position', fn ($a) => $a->employee->position?->name)
            ->addColumn('shift_name', fn ($a) => $a->shift?->name)
            ->addColumn('in_time', fn ($a) => $time($a->check_in_at))
            ->addColumn('out_time', fn ($a) => $time($a->check_out_at))
            ->addColumn('out_next_day', fn ($a) => $a->check_out_at
                && $a->check_out_at->setTimezone($tz)->toDateString() > $a->work_date->toDateString())
            ->addColumn('coord_in', fn ($a) => $coord($a, 'check_in'))
            ->addColumn('coord_out', fn ($a) => $coord($a, 'check_out'))
            ->addColumn('photo_in', fn ($a) => $photo($a, 'in'))
            ->addColumn('photo_out', fn ($a) => $photo($a, 'out'))
            ->addColumn('duration_label', fn ($a) => $a->duration_label)
            ->filterColumn('name', fn ($q, $kw) => $q->where('employees.full_name', 'like', "%{$kw}%"))
            ->orderColumn('name', 'employees.full_name $1')
            ->orderColumn('work_date', 'attendances.work_date $1, employees.full_name asc')
            ->orderColumn('in_time', 'attendances.check_in_at $1')
            ->orderColumn('out_time', 'attendances.check_out_at $1')
            ->only(['id', 'work_date', 'date_label', 'name', 'full_name', 'position', 'shift_name', 'in_time', 'out_time', 'out_next_day',
                'coord_in', 'coord_out', 'photo_in', 'photo_out', 'check_in_mode', 'check_out_mode',
                'check_in_offline', 'check_out_offline', 'duration_minutes', 'duration_label', 'status', 'late_minutes', 'flags',
                'offsite_approval', 'is_manual', 'is_holiday_work'])
            ->toJson();
    }

    public function show(Attendance $attendance): JsonResponse
    {
        $project = $this->ownAttendance($attendance);
        $attendance->load(['employee.position', 'shift', 'leaveRequest', 'project']);
        $tz = $project->timezone;

        $side = function (string $p) use ($attendance, $tz) {
            if (! $attendance->{"{$p}_at"}) {
                return null;
            }
            $s = $p === 'check_in' ? 'in' : 'out';

            return [
                'time' => $attendance->{"{$p}_at"}->setTimezone($tz)->format('H:i:s'),
                'date' => $attendance->{"{$p}_at"}->setTimezone($tz)->translatedFormat('j M Y'),
                'next_day' => $attendance->{"{$p}_at"}->setTimezone($tz)->toDateString() > $attendance->work_date->toDateString(),
                'lat' => $attendance->{"{$p}_lat"},
                'lng' => $attendance->{"{$p}_lng"},
                'accuracy' => $attendance->{"{$p}_accuracy"},
                'distance' => $attendance->{"{$p}_distance_m"},
                'mode' => $attendance->{"{$p}_mode"},
                'note' => $attendance->{"{$p}_note"},
                'offline' => $attendance->{"{$p}_offline"},
                'is_mock' => $attendance->{"{$p}_is_mock"},
                'device' => $attendance->{"{$p}_device"},
                'device_time' => $attendance->{"{$p}_device_time"}?->setTimezone($tz)->format('d/m/Y H:i:s'),
                'received_at' => $attendance->{"{$p}_received_at"}?->setTimezone($tz)->format('d/m/Y H:i:s'),
                'photo' => $attendance->{"{$p}_photo"} ? route('attendances.photo', [$attendance, $s]) : null,
            ];
        };

        $logs = Activity::query()
            ->with('causer')
            ->where('subject_type', $attendance->getMorphClass())
            ->where('subject_id', $attendance->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'event' => $log->event,
                'by' => $log->causer?->name ?? 'Sistem / aplikasi',
                'at' => $log->created_at->setTimezone($tz)->translatedFormat('j M Y H:i'),
                'changes' => $log->properties->only(['attributes', 'old']),
            ]);

        return response()->json([
            'id' => $attendance->id,
            'employee' => [
                'id' => $attendance->employee_id,
                'name' => $attendance->employee->display_name,
                'full_name' => $attendance->employee->full_name,
                'position' => $attendance->employee->position?->name,
            ],
            'work_date' => $attendance->work_date->toDateString(),
            'date_label' => $attendance->work_date->translatedFormat('l, j F Y'),
            'shift' => $attendance->shift?->only(['name', 'start_time', 'end_time']),
            'status' => $attendance->status,
            'late_minutes' => $attendance->late_minutes,
            'duration_label' => $attendance->duration_label,
            'is_holiday_work' => $attendance->is_holiday_work,
            'is_manual' => $attendance->is_manual,
            'note' => $attendance->note,
            'flags' => $attendance->flags ?? [],
            'offsite_approval' => $attendance->offsite_approval,
            'leave' => $attendance->leaveRequest?->only(['type', 'reason']),
            'check_in' => $side('check_in'),
            'check_out' => $side('check_out'),
            'locations' => $project->locations()->where('is_active', true)->get(['name', 'latitude', 'longitude', 'radius_m']),
            'logs' => $logs,
            'can_edit' => app(ProjectAccess::class)->canManage(auth()->user(), $project),
        ]);
    }

    /** Koreksi manual oleh admin (tercatat di audit log). */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $project = $this->ownAttendance($attendance, manage: true);
        $data = $this->validatedCorrection($request);

        if (isset($data['work_date']) && $data['work_date'] !== $attendance->work_date->toDateString()) {
            $taken = Attendance::where('project_id', $project->id)->where('employee_id', $attendance->employee_id)
                ->whereDate('work_date', $data['work_date'])->whereKeyNot($attendance->id)->exists();
            if ($taken) {
                return $this->failed('Presensi karyawan pada tanggal tsb sudah ada. Koreksi data pada tanggal tersebut.');
            }
        }

        $this->applyCorrection($attendance, $project, $data);
        $attendance->save();

        return $this->saved('Presensi dikoreksi.');
    }

    public function store(Request $request): JsonResponse
    {
        $project = $this->managedProject();
        $data = $this->validatedCorrection($request, creating: true, project: $project);

        $exists = Attendance::where('project_id', $project->id)->where('employee_id', $data['employee_id'])
            ->whereDate('work_date', $data['work_date'])->exists();
        if ($exists) {
            return $this->failed('Presensi karyawan pada tanggal tsb sudah ada. Gunakan koreksi pada data yang ada.');
        }

        $assignment = ProjectEmployee::where('project_id', $project->id)->where('employee_id', $data['employee_id'])->first();
        $attendance = new Attendance([
            'project_id' => $project->id,
            'employee_id' => $data['employee_id'],
            'work_date' => $data['work_date'],
            'shift_id' => $assignment?->shift_id ?? $project->defaultShift()?->id,
        ]);
        $this->applyCorrection($attendance, $project, $data);
        $attendance->save();

        return $this->saved('Presensi manual ditambahkan.');
    }

    public function photo(Attendance $attendance, string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['in', 'out'], true), 404);
        $this->ownAttendance($attendance);

        $path = $attendance->{($side === 'in' ? 'check_in' : 'check_out').(request()->boolean('thumb') ? '_thumb' : '_photo')};
        $disk = Storage::disk(config('presensi.photo.disk'));
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, headers: ['Cache-Control' => 'private, max-age=86400']);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('attendances.work_date', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('attendances.work_date', '<=', $v))
            ->when($request->input('employee_id'), fn ($q, $v) => $q->where('attendances.employee_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('attendances.status', $v))
            ->when($request->input('mode') === 'offsite', fn ($q) => $q->where(fn ($w) => $w
                ->where('check_in_mode', Attendance::MODE_OFFSITE)->orWhere('check_out_mode', Attendance::MODE_OFFSITE)))
            ->when($request->input('mode') === 'onsite', fn ($q) => $q->where('check_in_mode', Attendance::MODE_ONSITE))
            ->when($request->input('flag'), function ($q, $flag) {
                match ($flag) {
                    'offline' => $q->where(fn ($w) => $w->where('check_in_offline', true)->orWhere('check_out_offline', true)),
                    'manual' => $q->where('is_manual', true),
                    'holiday' => $q->where('is_holiday_work', true),
                    default => $q->whereJsonContains('flags', $flag),
                };
            });
    }

    private function validatedCorrection(Request $request, bool $creating = false, ?Project $project = null): array
    {
        $rules = [
            'status' => ['required', Rule::in(self::STATUSES)],
            'check_in_time' => ['nullable', 'required_if:status,hadir,terlambat', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'note' => [$creating ? 'nullable' : 'required', 'string', 'max:500'],
            'work_date' => [$creating ? 'required' : 'sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
        foreach (['check_in', 'check_out'] as $p) {
            $rules["{$p}_lat"] = ['nullable', "required_with:{$p}_lng", 'numeric', 'between:-90,90'];
            $rules["{$p}_lng"] = ['nullable', "required_with:{$p}_lat", 'numeric', 'between:-180,180'];
            $rules["{$p}_photo"] = ['nullable', 'image', 'max:'.config('presensi.photo.max_kb')];
        }
        if ($creating) {
            $rules['employee_id'] = ['required', Rule::exists('project_employee', 'employee_id')->where('project_id', $project->id)];
        }

        return $request->validate($rules, [
            'note.required' => 'Alasan koreksi wajib diisi (tercatat di audit log).',
            'check_in_time.required_if' => 'Jam check-in wajib diisi untuk status hadir/terlambat.',
            '*_lat.required_with' => 'Latitude wajib diisi bila longitude diisi.',
            '*_lng.required_with' => 'Longitude wajib diisi bila latitude diisi.',
        ]);
    }

    private function applyCorrection(Attendance $attendance, Project $project, array $data): void
    {
        $date = CarbonImmutable::parse($data['work_date'] ?? $attendance->work_date->toDateString(), $project->timezone)->startOfDay();
        $at = fn (?string $time) => $time ? $date->setTimeFromTimeString($time)->setTimezone(config('app.timezone')) : null;

        if ($attendance->exists && $date->toDateString() !== $attendance->work_date->toDateString()) {
            $attendance->work_date = $date->toDateString();
            $attendance->is_holiday_work = ! app(CalendarService::class)->isWorkingDay($project, $date);
        }

        $in = $at($data['check_in_time'] ?? null);
        $out = $at($data['check_out_time'] ?? null);
        if ($in && $out && $out->lt($in)) {
            $out = $out->addDay(); // shift melewati tengah malam
        }

        $attendance->status = $data['status'];
        $attendance->check_in_at = $in;
        $attendance->check_out_at = $in ? $out : null;
        $attendance->duration_minutes = $in && $out ? (int) $in->diffInMinutes($out) : null;
        $attendance->late_minutes = $data['status'] === Attendance::STATUS_TERLAMBAT ? $attendance->late_minutes : 0;
        $attendance->note = $data['note'] ?? null;
        $attendance->is_manual = true;
        $attendance->corrected_by = auth()->id();

        if ($out || ! $in) {
            $attendance->removeFlag(Attendance::FLAG_MISSING_CHECKOUT);
        }

        foreach (['check_in' => 'in', 'check_out' => 'out'] as $p => $suffix) {
            // Koordinat hanya diubah bila field dikirim; kosong = hapus koordinat
            if (array_key_exists("{$p}_lat", $data)) {
                $this->applyCoordinate($attendance, $project, $p, $data["{$p}_lat"], $data["{$p}_lng"] ?? null);
            }
            if (($file = $data["{$p}_photo"] ?? null) instanceof UploadedFile) {
                $this->replacePhoto($attendance, $project, $p, $suffix, $file);
            }
        }
    }

    /** Set koordinat lalu hitung ulang jarak & mode (dalam/luar lokasi) terhadap titik proyek terdekat. */
    private function applyCoordinate(Attendance $attendance, Project $project, string $p, mixed $lat, mixed $lng): void
    {
        $nearest = $lat !== null ? app(GeoService::class)->nearest($project, (float) $lat, (float) $lng) : ['location' => null];
        $found = $nearest['location'] !== null;

        $attendance->{"{$p}_lat"} = $lat !== null ? (float) $lat : null;
        $attendance->{"{$p}_lng"} = $lat !== null ? (float) $lng : null;
        $attendance->{"{$p}_location_id"} = $found ? $nearest['location']->id : null;
        $attendance->{"{$p}_distance_m"} = $found ? (int) round($nearest['distance']) : null;
        if ($found) {
            $attendance->{"{$p}_mode"} = $nearest['inside'] ? Attendance::MODE_ONSITE : Attendance::MODE_OFFSITE;
        } elseif ($lat === null) {
            $attendance->{"{$p}_mode"} = null;
        }
    }

    private function replacePhoto(Attendance $attendance, Project $project, string $p, string $suffix, UploadedFile $file): void
    {
        $old = array_filter([$attendance->{"{$p}_photo"}, $attendance->{"{$p}_thumb"}]);
        $stored = app(PhotoService::class)->storeAttendancePhoto($file, $project->id, (string) Str::uuid(), $suffix);

        $attendance->{"{$p}_photo"} = $stored['photo'];
        $attendance->{"{$p}_thumb"} = $stored['thumb'];

        if ($old) {
            Storage::disk(config('presensi.photo.disk'))->delete($old);
        }
    }

    private function ownAttendance(Attendance $attendance, bool $manage = false): Project
    {
        $project = $manage ? $this->managedProject() : $this->project();
        abort_unless($attendance->project_id === $project->id, 404);

        return $project;
    }
}
