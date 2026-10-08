<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskItem extends Model
{
    protected $fillable = [
        'project_task_id',
        'title',
        'is_done',
        'sort_order',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function projectTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class);
    }
}
