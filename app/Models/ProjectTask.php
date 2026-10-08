<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'task_type_id',
        'responsible_id',
        'description',
        'due_date',
        'priority',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    /**
     * Событие created: при создании задачи автоматически создаём
     * по одной карточке статуса для каждого этапа (pending).
     */
    protected static function booted(): void
    {
        static::created(function (ProjectTask $task) {
            foreach (Stage::orderBy('sort_order')->get() as $stage) {
                $task->stageStatuses()->create([
                    'stage_id' => $stage->id,
                    'status' => 'pending',
                ]);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    /**
     * Ответственный за задачу.
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * Статусы по этапам (карточки на доске).
     */
    public function stageStatuses(): HasMany
    {
        return $this->hasMany(StageStatus::class);
    }

    /**
     * Журнал перемещений задачи.
     */
    public function taskHistory(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }

    /**
     * Комментарии к задаче.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Пункты чек-листа задачи (этап 11).
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('sort_order')->orderBy('id');
    }
}