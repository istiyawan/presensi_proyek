<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'name', 'client_name', 'city', 'address',
    'contract_start_date', 'contract_end_date', 'timezone', 'is_active',
])]
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(ProjectSetting::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProjectLocation::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_employee')
            ->using(ProjectEmployee::class)
            ->withPivot(['id', 'shift_id', 'start_date', 'end_date', 'allow_offsite', 'is_team_leader'])
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(ReportSignatory::class)->orderBy('sort_order');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function defaultShift(): ?Shift
    {
        return $this->shifts()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }
}
