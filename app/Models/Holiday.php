<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['date', 'name', 'type', 'project_id'])]
class Holiday extends Model
{
    public const TYPE_NATIONAL = 'national';

    public const TYPE_CUTI_BERSAMA = 'cuti_bersama';

    public const TYPE_PROJECT = 'project';

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
