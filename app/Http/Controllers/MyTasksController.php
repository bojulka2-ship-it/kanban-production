<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyTasksController extends Controller
{
    /**
     * Страница «Мои задачи»: задачи, где текущий пользователь — ответственный,
     * в неархивных проектах. Сортировка: просроченные и ближайшие сверху,
     * задачи без дедлайна — в конце. Фильтры — серверные, через GET-параметры.
     */
    public function index(Request $request): View
    {
        $filters = [
            'project' => (int) $request->query('project', 0),
            'priority' => (string) $request->query('priority', ''),
            'overdue' => $request->query('overdue') === '1',
        ];

        $query = ProjectTask::query()
            ->forResponsible(auth()->id())
            ->with([
                'project:id,title',
                'taskType:id,name',
                'stageStatuses' => fn ($q) => $q->orderBy('stage_id'),
            ])
            ->withCount(['items', 'items as items_done_count' => fn ($q) => $q->where('is_done', true)]);

        if ($filters['project'] > 0) {
            $query->where('project_id', $filters['project']);
        }

        if (in_array($filters['priority'], ['low', 'normal', 'high'], true)) {
            $query->where('priority', $filters['priority']);
        }

        if ($filters['overdue']) {
            $query->dueOverdue();
        }

        $tasks = $query
            // Дедлайн есть — раньше, без дедлайна — в конце; внутри — по возрастанию даты
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        // Проекты пользователя (только те, где у него есть задачи) для фильтра
        $projects = Project::whereIn(
            'id',
            ProjectTask::query()->forResponsible(auth()->id())->pluck('project_id')
        )->orderBy('title')->get(['id', 'title']);

        return view('my-tasks', [
            'tasks' => $tasks,
            'filters' => $filters,
            'projects' => $projects,
            'overdueCount' => ProjectTask::query()->forResponsible(auth()->id())->dueOverdue()->count(),
        ]);
    }
}
