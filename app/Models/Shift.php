<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'name', 'start_time', 'end_time', 'late_enabled', 'late_tolerance_min',
    'checkin_open_time', 'checkin_close_time', 'is_default', 'is_active',
])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'late_enabled' => 'boolean',
            'late_tolerance_min' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
