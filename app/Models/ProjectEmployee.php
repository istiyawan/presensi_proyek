<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectEmployee extends Pivot
{
    protected $table = 'project_employee';

    public $incrementing = true;

    protected $fillable = [
        'project_id', 'employee_id', 'shift_id', 'start_date', 'end_date',
        'allow_offsite', 'is_team_leader',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'allow_offsite' => 'boolean',
            'is_team_leader' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }
}
