<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AttendanceException;
use App\Http\Requests\Api\AttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\CalendarService;
use App\Services\PeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AttendanceController extends ApiController
{
    public function __construct(private AttendanceService $service) {}

    public function today(Request $request, CalendarService $calendar): JsonResponse
    {
        $project = $this->memberProject($request, $request->query('project_id'));
        $now = CarbonImmutable::now($project->timezone);

        $employee = $this->employee($request);
        $attendance = Attendance::query()
            ->with(['project', 'shift'])
            ->where('employee_id', $employee->id)
            ->where('project_id', $project->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();

        // Shift kemarin yang belum check-out (mis. masih bekerja lewat tengah malam):
        // aplikasi menampilkan tombol check-out untuk presensi ini.
        $open = $this->service->openAttendance($employee, $project, $now, before: $now->toDateString())
            ?->load(['project', 'shift']);

        return $this->ok([
            'server_time' => $now->toIso8601String(),
            'work_date' => $now->toDateString(),
            'is_working_day' => $calendar->isWorkingDay($project, $now),
            'holiday' => $calendar->holiday($project, $now)?->name,
            'attendance' => $attendance ? new AttendanceResource($attendance) : null,
            'open_attendance' => $open ? new AttendanceResource($open) : null,
        ]);
    }

    public function checkIn(AttendanceRequest $request): JsonResponse
    {
        return $this->record($request, 'check_in');
    }

    public function checkOut(AttendanceRequest $request): JsonResponse
    {
        return $this->record($request, 'check_out');
    }

    /**
     * Sinkron data offline. Diproses berurutan; tiap item dapat hasil sendiri
     * sehingga aplikasi tahu mana yang sukses, ditolak permanen, atau perlu diulang.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.type' => ['required', 'in:check_in,check_out'],
        ]);

        $employee = $this->employee($request);
        $results = [];

        foreach ($request->input('items') as $i => $item) {
            $item = AttendanceRequest::normalize($item);
            $item['photo'] = $request->file("items.{$i}.photo");
            $item['is_offline'] ??= true;

            $result = ['uuid' => $item['uuid'] ?? null, 'type' => $item['type']];
            $validator = Validator::make($item, AttendanceRequest::itemRules());

            if ($validator->fails()) {
                $results[] = $result + ['success' => false, 'reason' => 'validation', 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                $project = $this->memberProject($request, $item['project_id']);
                $method = $item['type'] === 'check_in' ? 'checkIn' : 'checkOut';
                $attendance = $this->service->{$method}($employee, $project, $validator->validated(), $item['photo']);
                $attendance->load(['project', 'shift']);

                $results[] = $result + ['success' => true, 'data' => new AttendanceResource($attendance)];
            } catch (AttendanceException $e) {
                $results[] = $result + ['success' => false, 'reason' => $e->reason, 'message' => $e->getMessage()];
            } catch (HttpExceptionInterface $e) {
                $results[] = $result + ['success' => false, 'reason' => 'forbidden', 'message' => $e->getMessage()];
            }
        }

        return $this->ok($results);
    }

    public function history(Request $request, PeriodService $periods): JsonResponse
    {
        $request->validate([
            'project_id' => ['required', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $project = $this->memberProject($request, $request->query('project_id'));

        if ($request->filled(['from', 'to'])) {
            $from = CarbonImmutable::parse($request->query('from'));
            $to = CarbonImmutable::parse($request->query('to'));
        } else {
            $current = $periods->current($project) ?? $periods->period($project, 1);
            [$from, $to] = [$current['start'], $current['end']];
        }

        abort_if($from->diffInDays($to) > 93, 422, 'Rentang tanggal maksimal 93 hari.');

        $rows = Attendance::query()
            ->with(['project', 'shift'])
            ->where('employee_id', $this->employee($request)->id)
            ->where('project_id', $project->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('work_date')
            ->get();

        return $this->ok([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => (object) $rows->countBy('status')->all(),
            'items' => AttendanceResource::collection($rows),
        ]);
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorizeView($request, $attendance);

        return $this->ok(new AttendanceResource($attendance->load(['project', 'shift', 'employee.position'])));
    }

    public function photo(Request $request, Attendance $attendance, string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['in', 'out'], true), 404);
        $this->authorizeView($request, $attendance);

        $prefix = $side === 'in' ? 'check_in' : 'check_out';
        $path = $attendance->{$prefix.($request->boolean('thumb') ? '_thumb' : '_photo')};
        $disk = Storage::disk(config('presensi.photo.disk'));
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, headers: ['Cache-Control' => 'private, max-age=86400']);
    }

    private function record(AttendanceRequest $request, string $type): JsonResponse
    {
        $employee = $this->employee($request);
        $project = $this->memberProject($request, $request->input('project_id'));
        $method = $type === 'check_in' ? 'checkIn' : 'checkOut';

        $attendance = $this->service->{$method}($employee, $project, $request->validated(), $request->file('photo'));
        $attendance->load(['project', 'shift']);

        $message = $type === 'check_in' ? 'Check-in berhasil.' : 'Check-out berhasil.';
        if ($attendance->{"{$type}_mode"} === Attendance::MODE_OFFSITE) {
            $message .= ' (Luar Lokasi)';
        }

        return $this->ok(new AttendanceResource($attendance), $message, $attendance->wasRecentlyCreated ? 201 : 200);
    }

    /** Pemilik presensi atau Team Leader proyek tsb. */
    private function authorizeView(Request $request, Attendance $attendance): void
    {
        $employee = $this->employee($request);

        abort_unless(
            $attendance->employee_id === $employee->id
                || in_array($attendance->project_id, $this->leaderProjectIds($request), true),
            403,
        );
    }
}
