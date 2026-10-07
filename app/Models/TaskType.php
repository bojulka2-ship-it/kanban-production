<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskType extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    /**
     * Задачи этого типа в проектах.
     */
    public function projectTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }
}