<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'title',
        'start_date',
        'due_date',
        'status',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    /**
     * Задачи проекта (4 трека).
     */
    public function projectTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }
}