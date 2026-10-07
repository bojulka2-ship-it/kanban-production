<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Stage;
use App\Models\TaskHistory;
use App\Models\TaskType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * 4 демо-проекта на разных стадиях.
     * Массив задач: ключ = порядковый номер типа задачи (1..4),
     * значение = статусы этапов по порядку (pending/in_progress/done).
     */
    public function run(): void
    {
        $stages = Stage::orderBy('sort_order')->get();
        $taskTypes = TaskType::orderBy('sort_order')->get();
        $employees = User::where('role', 'employee')->get();

        $projects = [
            // (а) только начат: 1-2 задачи in_progress на первом этапе
            [
                'title' => 'Модернизация линии упаковки',
                'start' => now()->subDays(3),
                'due' => now()->addDays(27),
                'status' => 'active',
                'tasks' => [
                    ['in_progress', 'pending', 'pending', 'pending', 'pending'],
                    ['in_progress', 'pending', 'pending', 'pending', 'pending'],
                    ['pending', 'pending', 'pending', 'pending', 'pending'],
                    ['pending', 'pending', 'pending', 'pending', 'pending'],
                ],
            ],
            // (б) середина: разные этапы, у второй задачи ДВА этапа параллельно in_progress
            [
                'title' => 'Серийные корпусные детали',
                'start' => now()->subDays(20),
                'due' => now()->addDays(40),
                'status' => 'active',
                'tasks' => [
                    ['done', 'in_progress', 'pending', 'pending', 'pending'],
                    ['done', 'in_progress', 'in_progress', 'pending', 'pending'],
                    ['done', 'done', 'in_progress', 'pending', 'pending'],
                    ['done', 'done', 'done', 'in_progress', 'pending'],
                ],
            ],
            // (в) просроченный: due_date на 5 дней в прошлом, статус active
            [
                'title' => 'Ремонт конвейера №2',
                'start' => now()->subDays(40),
                'due' => now()->subDays(5),
                'status' => 'active',
                'tasks' => [
                    ['done', 'done', 'in_progress', 'pending', 'pending'],
                    ['done', 'done', 'done', 'in_progress', 'pending'],
                    ['done', 'in_progress', 'pending', 'pending', 'pending'],
                    ['done', 'done', 'pending', 'pending', 'pending'],
                ],
            ],
            // (г) завершённый: все этапы done, статус completed
            [
                'title' => 'Партия кронштейнов (закрыта)',
                'start' => now()->subDays(60),
                'due' => now()->subDays(10),
                'status' => 'completed',
                'tasks' => [
                    ['done', 'done', 'done', 'done', 'done'],
                    ['done', 'done', 'done', 'done', 'done'],
                    ['done', 'done', 'done', 'done', 'done'],
                    ['done', 'done', 'done', 'done', 'done'],
                ],
            ],
        ];

        foreach ($projects as $projectIndex => $data) {
            $project = Project::create([
                'title' => $data['title'],
                'start_date' => $data['start'],
                'due_date' => $data['due'],
                'status' => $data['status'],
                'is_archived' => false,
            ]);

            foreach ($data['tasks'] as $taskIndex => $statuses) {
                $task = $project->projectTasks()->create([
                    'task_type_id' => $taskTypes[$taskIndex]->id,
                    // один человек может отвечать за несколько задач
                    'responsible_id' => $employees[($projectIndex + $taskIndex) % $employees->count()]->id,
                ]);

                $this->fillStages($task, $stages, $statuses, $data['start']);
            }
        }
    }

    /**
     * Проставляет статусы этапов задачи, entered_at для in_progress
     * и записывает путь задачи в историю.
     */
    private function fillStages(ProjectTask $task, $stages, array $statuses, Carbon $start): void
    {
        // часы начала каждого этапа: первый этап у старта проекта, дальше +1 день
        $clock = $start->copy()->addHours(10);
        $actor = $task->responsible;

        foreach ($statuses as $index => $status) {
            if ($status !== 'pending') {
                $stageStatus = $task->stageStatuses()
                    ->where('stage_id', $stages[$index]->id)
                    ->firstOrFail();

                if ($status === 'in_progress') {
                    $stageStatus->update([
                        'status' => 'in_progress',
                        'entered_at' => $clock,
                    ]);
                    $this->history($task, $stages[$index], $actor, 'stage_started', $clock);
                } else {
                    $stageStatus->update(['status' => 'done']);
                    $this->history($task, $stages[$index], $actor, 'stage_started', $clock);
                    $this->history($task, $stages[$index], $actor, 'stage_completed', $clock->copy()->addHours(6));
                }
            }

            $clock = $clock->copy()->addDay();
        }
    }

    /**
     * Запись в журнал с историческими датами (вместо времени создания записи).
     */
    private function history(ProjectTask $task, Stage $stage, User $user, string $action, Carbon $when): void
    {
        $entry = TaskHistory::create([
            'project_task_id' => $task->id,
            'user_id' => $user->id,
            'stage_id' => $stage->id,
            'action' => $action,
        ]);

        $entry->timestamps = false;
        $entry->created_at = $when;
        $entry->updated_at = $when;
        $entry->save();
    }
}