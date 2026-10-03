<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectEmployee;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private SettingService $settings,
        private GeoService $geo,
        private CalendarService $calendar,
        private PhotoService $photos,
    ) {}

    /**
     * @param  array{uuid: string, latitude: float, longitude: float, accuracy?: ?float, is_mock?: bool,
     *               note?: ?string, is_offline?: bool, captured_at?: ?string, device_time?: ?string,
     *               device?: ?array, flags?: ?array}  $data
     */
    public function checkIn(Employee $employee, Project $project, array $data, UploadedFile $photo): Attendance
    {
        // Kirim ulang dengan uuid yang sama → kembalikan data yang sudah ada (idempotent)
        if ($existing = Attendance::where('check_in_uuid', $data['uuid'])->first()) {
            return $existing;
        }

        $s = $this->settings->project($project);
        $time = $this->resolveTime($project, $data, $s);
        $workDate = $time->toDateString();

        $assignment = $this->assignment($employee, $project, $workDate);
        $shift = $assignment->shift ?? $project->defaultShift();

        if (! $this->isOffline($data)) {
            $this->assertCheckinWindow($shift, $time);
        }
        $location = $this->evaluateLocation($project, $assignment, $data, $s);

        return DB::transaction(function () use ($employee, $project, $data, $photo, $time, $workDate, $shift, $location, $s) {
            // Shift hari sebelumnya masih terbuka (mis. masuk 08:00 kemarin, belum check-out).
            // Karyawan harus memilih: check-out dulu, atau tandai lupa check-out.
            if ($open = $this->openAttendance($employee, $project, $time, before: $workDate)) {
                if (empty($data['abandon_previous'])) {
                    $openIn = $open->check_in_at->setTimezone($project->timezone);
                    throw new AttendanceException(
                        'Anda belum check-out dari presensi '.$openIn->translatedFormat('j M Y').' (masuk '.$openIn->format('H:i').'). '
                        .'Lakukan check-out terlebih dahulu, atau pilih "Lupa check-out" untuk memulai presensi baru.',
                        'open_previous_shift',
                        409,
                        ['id' => $open->id, 'work_date' => $open->work_date->toDateString(), 'check_in_time' => $openIn->toIso8601String()],
                    );
                }
                $open->addFlag(Attendance::FLAG_MISSING_CHECKOUT);
                $open->save();
            }

            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('project_id', $project->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            if ($attendance?->check_in_at) {
                throw new AttendanceException('Anda sudah check-in hari ini.', 'already_checked_in', 409);
            }

            // Baris alpha/libur dari scheduler di-overwrite oleh presensi yang masuk belakangan (offline)
            $attendance ??= new Attendance([
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'work_date' => $workDate,
            ]);

            $stored = $this->photos->storeAttendancePhoto($photo, $project->id, $data['uuid'], 'in');

            $attendance->fill($this->sideAttributes('check_in', $data, $time, $location, $stored));
            $attendance->shift_id = $shift?->id;
            $attendance->leave_request_id = null;

            [$status, $lateMinutes] = $this->statusFor($shift, $time);
            $attendance->status = $status;
            $attendance->late_minutes = $lateMinutes;
            $attendance->is_holiday_work = ! $this->calendar->isWorkingDay($project, $time);

            $attendance->offsite_approval = $location['mode'] === Attendance::MODE_OFFSITE && $s['offsite_requires_approval']
                ? 'pending'
                : 'none';

            $this->applyFlags($attendance, $data, $s);
            $attendance->save();

            return $attendance;
        });
    }

    public function checkOut(Employee $employee, Project $project, array $data, UploadedFile $photo): Attendance
    {
        if ($existing = Attendance::where('check_out_uuid', $data['uuid'])->first()) {
            return $existing;
        }

        $s = $this->settings->project($project);
        $time = $this->resolveTime($project, $data, $s);

        return DB::transaction(function () use ($employee, $project, $data, $photo, $time, $s) {
            // Check-in terbaru dalam jendela max_work_hours — check-out lewat tengah malam
            // tetap menutup presensi tanggal check-in (mis. 2 Okt 08:00 → 3 Okt 01:00).
            $attendance = $this->windowQuery($employee, $project, $time, (int) $s['max_work_hours'])
                ->orderByDesc('check_in_at')
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                throw new AttendanceException(
                    "Tidak ada check-in dalam {$s['max_work_hours']} jam terakhir. Bila lupa check-out, hubungi admin untuk koreksi.",
                    'no_checkin',
                    409,
                );
            }
            if ($attendance->check_out_at) {
                throw new AttendanceException('Anda sudah check-out hari ini.', 'already_checked_out', 409);
            }

            $assignment = $this->assignment($employee, $project, $attendance->work_date->toDateString());
            $location = $s['require_checkout_in_location']
                ? $this->evaluateLocation($project, $assignment, $data, $s)
                : $this->describeLocation($project, $data, allowOutside: true);

            $stored = $this->photos->storeAttendancePhoto($photo, $project->id, $data['uuid'], 'out');

            $attendance->fill($this->sideAttributes('check_out', $data, $time, $location, $stored));
            $attendance->duration_minutes = (int) $attendance->check_in_at->diffInMinutes($time);
            $attendance->removeFlag(Attendance::FLAG_MISSING_CHECKOUT);

            if ($location['mode'] === Attendance::MODE_OFFSITE && $attendance->offsite_approval === 'none' && $s['offsite_requires_approval']) {
                $attendance->offsite_approval = 'pending';
            }

            $this->applyFlags($attendance, $data, $s);
            $attendance->save();

            return $attendance;
        });
    }

    /**
     * Presensi yang sudah check-in tapi belum check-out dan masih dalam jendela kerja.
     * `before` = hanya tanggal kerja sebelum tanggal ini (shift kemarin yang masih berjalan).
     */
    public function openAttendance(Employee $employee, Project $project, ?CarbonImmutable $time = null, ?string $before = null): ?Attendance
    {
        $time ??= CarbonImmutable::now($project->timezone);
        $hours = (int) $this->settings->project($project, 'max_work_hours');

        return $this->windowQuery($employee, $project, $time, $hours)
            ->whereNull('check_out_at')
            ->when($before, fn ($q) => $q->whereDate('work_date', '<', $before))
            ->orderByDesc('check_in_at')
            ->first();
    }

    private function windowQuery(Employee $employee, Project $project, CarbonImmutable $time, int $hours)
    {
        // Kolom datetime disimpan dalam zona aplikasi
        $appTime = $time->setTimezone(config('app.timezone'));

        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('project_id', $project->id)
            ->whereNotNull('check_in_at')
            ->where('check_in_at', '<=', $appTime)
            ->where('check_in_at', '>=', $appTime->subHours($hours));
    }

    /**
     * Waktu presensi: waktu server untuk online, waktu terpercaya dari aplikasi untuk offline.
     */
    private function resolveTime(Project $project, array $data, array $s): CarbonImmutable
    {
        $now = CarbonImmutable::now($project->timezone);

        if (! $this->isOffline($data)) {
            return $now;
        }

        if (! $s['allow_offline']) {
            throw new AttendanceException('Presensi offline tidak diizinkan di proyek ini.', 'offline_not_allowed');
        }
        if (empty($data['captured_at'])) {
            throw new AttendanceException('Waktu presensi offline tidak valid.', 'invalid_time');
        }

        $captured = CarbonImmutable::parse($data['captured_at'])->setTimezone($project->timezone);

        if ($captured->gt($now->addSeconds(config('presensi.future_tolerance_seconds')))) {
            throw new AttendanceException('Waktu presensi offline berada di masa depan.', 'invalid_time');
        }
        if ($captured->lt($now->subHours((int) $s['offline_max_hours']))) {
            throw new AttendanceException(
                "Data offline lebih dari {$s['offline_max_hours']} jam, tidak dapat diterima. Hubungi admin.",
                'offline_expired',
            );
        }

        return $captured;
    }

    private function assignment(Employee $employee, Project $project, string $date): ProjectEmployee
    {
        $assignment = $employee->assignmentFor($project->id, $date);

        if (! $assignment || ! $project->is_active || ! $employee->is_active) {
            throw new AttendanceException('Anda tidak terdaftar aktif di proyek ini.', 'not_assigned', 403);
        }

        return $assignment->loadMissing('shift');
    }

    private function assertCheckinWindow(?Shift $shift, CarbonImmutable $time): void
    {
        if (! $shift) {
            return;
        }
        $clock = $time->format('H:i:s');

        if ($shift->checkin_open_time && $clock < $shift->checkin_open_time) {
            throw new AttendanceException(
                'Check-in baru dibuka pukul '.substr($shift->checkin_open_time, 0, 5).'.',
                'checkin_not_open',
            );
        }
        if ($shift->checkin_close_time && $clock > $shift->checkin_close_time) {
            throw new AttendanceException(
                'Check-in sudah ditutup pukul '.substr($shift->checkin_close_time, 0, 5).'.',
                'checkin_closed',
            );
        }
    }

    /**
     * Validasi lokasi: dalam radius → onsite; di luar → offsite bila diizinkan, selain itu ditolak.
     *
     * @return array{mode: string, location_id: ?int, distance: ?int}
     */
    private function evaluateLocation(Project $project, ProjectEmployee $assignment, array $data, array $s): array
    {
        $offline = $this->isOffline($data);

        if (! empty($data['is_mock']) && $s['block_mock_location']) {
            throw new AttendanceException('Terdeteksi lokasi palsu (Fake GPS). Matikan aplikasi lokasi palsu.', 'mock_location', 403);
        }

        $accuracy = $data['accuracy'] ?? null;
        if (! $offline && $accuracy !== null && $accuracy > $s['max_gps_accuracy_m']) {
            throw new AttendanceException(
                'Akurasi GPS terlalu rendah ('.round($accuracy).' m). Pindah ke area terbuka dan coba lagi.',
                'low_accuracy',
                context: ['accuracy' => (float) $accuracy, 'max' => (int) $s['max_gps_accuracy_m']],
            );
        }

        $result = $this->describeLocation($project, $data, allowOutside: $this->offsiteAllowed($assignment, $s));

        if ($result['mode'] === Attendance::MODE_OFFSITE && $s['offsite_requires_note'] && blank($data['note'] ?? null)) {
            throw new AttendanceException('Presensi di luar lokasi wajib mengisi keterangan.', 'note_required');
        }

        return $result;
    }

    private function describeLocation(Project $project, array $data, bool $allowOutside): array
    {
        $nearest = $this->geo->nearest($project, (float) $data['latitude'], (float) $data['longitude']);

        if ($nearest['location'] === null) {
            throw new AttendanceException('Lokasi proyek belum diatur. Hubungi admin.', 'no_project_location');
        }

        $distance = (int) round($nearest['distance']);

        if (! $nearest['inside'] && ! $allowOutside) {
            throw new AttendanceException(
                "Anda berada {$distance} m dari {$nearest['location']->name} (radius {$nearest['location']->radius_m} m).",
                'outside_radius',
                context: ['distance_m' => $distance, 'radius_m' => $nearest['location']->radius_m],
            );
        }

        return [
            'mode' => $nearest['inside'] ? Attendance::MODE_ONSITE : Attendance::MODE_OFFSITE,
            'location_id' => $nearest['location']->id,
            'distance' => $distance,
        ];
    }

    private function offsiteAllowed(ProjectEmployee $assignment, array $s): bool
    {
        if (! $s['allow_offsite']) {
            return false;
        }

        return $assignment->allow_offsite ?? ($s['offsite_scope'] === 'all');
    }

    /** @return array{0: string, 1: int} */
    private function statusFor(?Shift $shift, CarbonImmutable $time): array
    {
        if (! $shift?->late_enabled) {
            return [Attendance::STATUS_HADIR, 0];
        }

        $limit = $time->setTimeFromTimeString($shift->start_time)->addMinutes($shift->late_tolerance_min);
        if ($time->lte($limit)) {
            return [Attendance::STATUS_HADIR, 0];
        }

        $start = $time->setTimeFromTimeString($shift->start_time);

        return [Attendance::STATUS_TERLAMBAT, (int) $start->diffInMinutes($time)];
    }

    private function sideAttributes(string $p, array $data, CarbonImmutable $time, array $location, array $stored): array
    {
        return [
            "{$p}_uuid" => $data['uuid'],
            "{$p}_at" => $time->setTimezone(config('app.timezone')),
            "{$p}_lat" => $data['latitude'],
            "{$p}_lng" => $data['longitude'],
            "{$p}_accuracy" => $data['accuracy'] ?? null,
            "{$p}_location_id" => $location['location_id'],
            "{$p}_distance_m" => $location['distance'],
            "{$p}_mode" => $location['mode'],
            "{$p}_note" => $data['note'] ?? null,
            "{$p}_photo" => $stored['photo'],
            "{$p}_thumb" => $stored['thumb'],
            "{$p}_offline" => $this->isOffline($data),
            "{$p}_device_time" => isset($data['device_time']) ? CarbonImmutable::parse($data['device_time'])->setTimezone(config('app.timezone')) : null,
            "{$p}_received_at" => now(),
            "{$p}_is_mock" => (bool) ($data['is_mock'] ?? false),
            "{$p}_device" => $data['device'] ?? null,
        ];
    }

    private function applyFlags(Attendance $attendance, array $data, array $s): void
    {
        foreach ((array) ($data['flags'] ?? []) as $flag) {
            if ($flag === Attendance::FLAG_TIME_SUSPICIOUS) {
                $attendance->addFlag($flag);
            }
        }

        $accuracy = $data['accuracy'] ?? null;
        if ($accuracy !== null && $accuracy > $s['max_gps_accuracy_m']) {
            $attendance->addFlag(Attendance::FLAG_LOW_ACCURACY);
        }
    }

    private function isOffline(array $data): bool
    {
        return (bool) ($data['is_offline'] ?? false);
    }
}
