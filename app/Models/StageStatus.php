<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageStatus extends Model
{
    protected $fillable = [
        'project_task_id',
        'stage_id',
        'status',
        'entered_at',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
        ];
    }

    public function projectTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}