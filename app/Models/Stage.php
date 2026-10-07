<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    /**
     * Статусы задачи по этому этапу (карточка в колонке).
     */
    public function stageStatuses(): HasMany
    {
        return $this->hasMany(StageStatus::class);
    }

    /**
     * Записи журнала по этому этапу.
     */
    public function taskHistory(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }
}