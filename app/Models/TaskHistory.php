<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskHistory extends Model
{
    // Имя таблицы задано вручную: Laravel назвал бы её task_histories
    protected $table = 'task_history';

    protected $fillable = [
        'project_task_id',
        'user_id',
        'stage_id',
        'action',
    ];

    public function projectTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}