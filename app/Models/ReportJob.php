<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid', 'project_id', 'user_id', 'type', 'format', 'params', 'title', 'status',
    'file_path', 'file_size', 'error', 'started_at', 'finished_at',
])]
class ReportJob extends Model
{
    public const TYPES = [
        'individual' => 'Individual',
        'combined' => 'Semua karyawan',
        'recap' => 'Rekap',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFilenameAttribute(): string
    {
        return preg_replace('/[^\w\s\-.,()]+/u', '', $this->title).'.'.$this->format;
    }
}
