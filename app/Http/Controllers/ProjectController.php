<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Stage;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Главная: канбан-доска-матрица. Фильтры — серверные, через GET-параметры:
     * q (поиск по названию), responsible, status, overdue, archive (0/1).
     */
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'responsible' => (int) $request->query('responsible', 0),
            'status' => (string) $request->query('status', ''),
            'overdue' => $request->query('overdue') === '1',
            'archive' => $request->query('archive') === '1',
        ];

        $query = Project::with([
                'projectTasks' => function ($q) {
                    $q->with(['responsible', 'stageStatuses', 'taskType', 'project'])
                        ->withCount(['items', 'items as items_done_count' => fn ($qq) => $qq->where('is_done', true)]);
                },
            ])
            ->where('is_archived', $filters['archive'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->orderBy('start_date');

        // Ответственный хотя бы за одну задачу проекта
        if ($filters['responsible'] > 0) {
            $query->whereHas('projectTasks', fn ($q) => $q->where('responsible_id', $filters['responsible']));
        }

        // Статус проекта ('' — без фильтра)
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        // Только просроченные: дедлайн в прошлом у активного проекта
        if ($filters['overdue']) {
            $query->where('status', 'active')->where('due_date', '<', now()->toDateString());
        }

        $projects = $query->get();

        // Поиск по названию: mb_stripos — регистронезависимый, работает и с кириллицей
        // (SQLite LIKE регистронезависим только для ASCII). Проектов до 20 — фильтруем в памяти.
        if ($filters['q'] !== '') {
            $projects = $projects->filter(fn ($p) => mb_stripos($p->title, $filters['q']) !== false);
        }

        return view('board', [
            'projects' => $projects,
            'filters' => $filters,
            'activeUsers' => $this->activeUsers(),
            'stages' => Stage::orderBy('sort_order')->get(),
            'taskTypes' => TaskType::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Страница редактирования проекта (только руководитель).
     */
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            'project' => $project->load('projectTasks'),
            'activeUsers' => $this->activeUsers(),
            'taskTypes' => TaskType::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Создание проекта: проект + 4 задачи в одной транзакции.
     * Stage_statuses создаются автоматически событием created модели ProjectTask.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $project = Project::create([
                'title' => $data['title'],
                'start_date' => $data['start_date'],
                'due_date' => $data['due_date'],
                'status' => 'active',
                'is_archived' => false,
            ]);

            foreach (TaskType::orderBy('sort_order')->get() as $taskType) {
                $project->projectTasks()->create([
                    'task_type_id' => $taskType->id,
                    'responsible_id' => $data['responsible'][$taskType->id],
                ]);
            }
        });

        return redirect()->route('home')->with('success', 'Проект создан');
    }

    /**
     * Редактирование проекта: поля + статус + ответственные по задачам.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $project) {
            $project->update([
                'title' => $data['title'],
                'start_date' => $data['start_date'],
                'due_date' => $data['due_date'],
                'status' => $data['status'],
            ]);

            foreach (TaskType::orderBy('sort_order')->get() as $taskType) {
                $project->projectTasks()->updateOrCreate(
                    ['task_type_id' => $taskType->id],
                    ['responsible_id' => $data['responsible'][$taskType->id]],
                );
            }
        });

        return back()->with('success', 'Проект обновлён');
    }

    /**
     * В архив.
     */
    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);
        $project->update(['is_archived' => true]);

        return redirect()->route('home')->with('success', 'Проект отправлен в архив');
    }

    /**
     * Восстановление из архива.
     */
    public function restore(Project $project): RedirectResponse
    {
        $this->authorize('restore', $project);
        $project->update(['is_archived' => false]);

        return back()->with('success', 'Проект восстановлен');
    }

    /**
     * Активные пользователи для select ответственных.
     */
    private function activeUsers()
    {
        return User::where('is_active', true)->orderBy('name')->get();
    }
}