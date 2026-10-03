<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'nik', 'full_name', 'title_prefix', 'title_suffix',
    'position_id', 'phone', 'photo', 'is_active',
])]
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_employee')
            ->using(ProjectEmployee::class)
            ->withPivot(['id', 'shift_id', 'start_date', 'end_date', 'allow_offsite', 'is_team_leader'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** Nama lengkap dengan gelar, mis. "Edi Santoso, S.T., M.T." */
    public function getDisplayNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->title_prefix, $this->full_name])))
            .($this->title_suffix ? ', '.$this->title_suffix : '');
    }

    /** Penugasan aktif di proyek pada tanggal tertentu. */
    public function assignmentFor(int $projectId, ?string $date = null): ?ProjectEmployee
    {
        $date ??= now()->toDateString();

        return ProjectEmployee::query()
            ->where('employee_id', $this->id)
            ->where('project_id', $projectId)
            ->activeOn($date)
            ->first();
    }
}
