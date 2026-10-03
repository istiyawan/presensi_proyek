<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'key', 'value'])]
class ProjectSetting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
