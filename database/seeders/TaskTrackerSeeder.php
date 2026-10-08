<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Seeder;

class TaskTrackerSeeder extends Seeder
{
    /**
     * Демо-данные трекера (этап 11) на одном проекте:
     * описание, дедлайны (одна задача просрочена), приоритеты, пункты чек-листа.
     * Идемпотентно: поля обновляются, пункты создаются только если их ещё нет.
     */
    public function run(): void
    {
        $project = Project::where('title', 'Серийные корпусные детали')->first()
            ?? Project::has('projectTasks')->first();

        if (! $project) {
            return;
        }

        $tasks = $project->projectTasks()->get()
            ->sortBy(fn (ProjectTask $t) => $t->taskType->sort_order)
            ->values();

        foreach ($this->presets() as $index => $preset) {
            $task = $tasks[$index] ?? null;
            if (! $task) {
                continue;
            }

            $task->update([
                'description' => $preset['description'],
                'due_date' => $preset['due'],
                'priority' => $preset['priority'],
            ]);

            // Пункты создаём только при первом наполнении, чтобы повторный запуск не дублировал
            if ($task->items()->count() === 0) {
                $order = 1;
                foreach ($preset['items'] as [$title, $isDone]) {
                    $task->items()->create([
                        'title' => $title,
                        'is_done' => $isDone,
                        'sort_order' => $order++,
                    ]);
                }
            }
        }
    }

    /**
     * Пресеты по порядку типов задач (1..4).
     */
    private function presets(): array
    {
        return [
            [
                'description' => 'Согласовать чертежи с отделом главного конструктора и запустить в производство.',
                'due' => now()->subDays(3),
                'priority' => 'high',
                'items' => [
                    ['Чертежи согласованы', true],
                    ['Материалы заказаны', true],
                    ['Заготовки получены', false],
                    ['Первая партия изготовлена', false],
                ],
            ],
            [
                'description' => 'Проверить комплектность поставки и оформить приёмку.',
                'due' => now()->addDays(5),
                'priority' => 'high',
                'items' => [
                    ['Накладные проверены', true],
                    ['Крепёж пересчитан', false],
                    ['Акт приёмки подписан', false],
                ],
            ],
            [
                'description' => null,
                'due' => null,
                'priority' => 'low',
                'items' => [
                    ['Техпроцесс разработан', true],
                    ['Нормирование выполнено', true],
                    ['Инструкции обновлены', false],
                    ['Операторы обучены', false],
                    ['Контрольный образец сдан', false],
                ],
            ],
            [
                'description' => 'Испытания на стенде, протокол приложить к акту.',
                'due' => now()->addDays(12),
                'priority' => 'normal',
                'items' => [],
            ],
        ];
    }
}
