<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attendance */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tz = $this->project?->timezone ?? config('app.timezone');

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->display_name,
                'position' => $this->employee->position?->name,
            ]),
            'work_date' => $this->work_date->toDateString(),
            'shift' => $this->whenLoaded('shift', fn () => $this->shift?->name),
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $this->duration_label,
            'is_holiday_work' => $this->is_holiday_work,
            'offsite_approval' => $this->offsite_approval,
            'flags' => $this->flags ?? [],
            'check_in' => $this->side('check_in', $tz),
            'check_out' => $this->side('check_out', $tz),
        ];
    }

    private function side(string $p, string $tz): ?array
    {
        if (! $this->{"{$p}_at"}) {
            return null;
        }

        $side = $p === 'check_in' ? 'in' : 'out';

        $local = $this->{"{$p}_at"}->setTimezone($tz);

        return [
            'uuid' => $this->{"{$p}_uuid"},
            'time' => $local->toIso8601String(),
            // true bila terjadi di hari berikutnya dari tanggal kerja (pulang lewat tengah malam)
            'next_day' => $local->toDateString() > $this->work_date->toDateString(),
            'latitude' => $this->{"{$p}_lat"},
            'longitude' => $this->{"{$p}_lng"},
            'accuracy' => $this->{"{$p}_accuracy"},
            'distance_m' => $this->{"{$p}_distance_m"},
            'mode' => $this->{"{$p}_mode"},
            'note' => $this->{"{$p}_note"},
            'offline' => $this->{"{$p}_offline"},
            'photo_url' => route('api.v1.attendance.photo', ['attendance' => $this->id, 'side' => $side]),
            'thumb_url' => route('api.v1.attendance.photo', ['attendance' => $this->id, 'side' => $side, 'thumb' => 1]),
        ];
    }
}
