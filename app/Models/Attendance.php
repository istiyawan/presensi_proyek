<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Attendance extends Model
{
    use LogsActivity;

    public const STATUS_HADIR = 'hadir';

    public const STATUS_TERLAMBAT = 'terlambat';

    public const STATUS_ALPHA = 'alpha';

    public const STATUS_LIBUR = 'libur';

    public const MODE_ONSITE = 'onsite';

    public const MODE_OFFSITE = 'offsite';

    public const FLAG_TIME_SUSPICIOUS = 'time_suspicious';

    public const FLAG_LOW_ACCURACY = 'low_accuracy';

    public const FLAG_MISSING_CHECKOUT = 'missing_checkout';

    /** Tanggal presensi dipilih mundur oleh karyawan (bukan tanggal saat dikirim). */
    public const FLAG_BACKDATED = 'backdated';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $casts = [
            'work_date' => 'date:Y-m-d',
            'late_minutes' => 'integer',
            'duration_minutes' => 'integer',
            'is_holiday_work' => 'boolean',
            'is_manual' => 'boolean',
            'offsite_approved_at' => 'datetime',
            'flags' => 'array',
        ];

        foreach (['check_in', 'check_out'] as $p) {
            $casts += [
                "{$p}_at" => 'datetime',
                "{$p}_lat" => 'float',
                "{$p}_lng" => 'float',
                "{$p}_accuracy" => 'float',
                "{$p}_distance_m" => 'integer',
                "{$p}_offline" => 'boolean',
                "{$p}_device_time" => 'datetime',
                "{$p}_received_at" => 'datetime',
                "{$p}_is_mock" => 'boolean',
                "{$p}_device" => 'array',
            ];
        }

        return $casts;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['work_date', 'status', 'check_in_at', 'check_out_at', 'offsite_approval', 'note', 'is_manual',
                'check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng', 'check_in_photo', 'check_out_photo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Termasuk karyawan yang diarsipkan agar riwayat presensi tetap terbaca. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function addFlag(string $flag): void
    {
        $flags = $this->flags ?? [];
        if (! in_array($flag, $flags, true)) {
            $flags[] = $flag;
        }
        $this->flags = $flags;
    }

    public function removeFlag(string $flag): void
    {
        $this->flags = array_values(array_diff($this->flags ?? [], [$flag])) ?: null;
    }

    /** "9 jam 28 menit" — format sesuai laporan acuan. */
    public function getDurationLabelAttribute(): ?string
    {
        if ($this->duration_minutes === null) {
            return null;
        }

        return intdiv($this->duration_minutes, 60).' jam '.($this->duration_minutes % 60).' menit';
    }
}
