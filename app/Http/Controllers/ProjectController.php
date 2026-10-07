<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesManager;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use AuthorizesManager;

    /**
     * Главная: список активных проектов (архив скрыт по умолчанию).
     */
    public function index(): View
    {
        return view('home', [
            'projects' => Project::with('projectTasks.responsible')
                ->where('is_archived', false)
                ->orderBy('start_date')
                ->get(),
            'activeUsers' => $this->activeUsers(),
            'taskTypes' => TaskType::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Страница редактирования проекта (только руководитель).
     */
    public function edit(Project $project): View
    {
        $this->authorizeManager();

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
        $this->authorizeManager();
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
        $this->authorizeManager();
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
        $this->authorizeManager();
        $project->update(['is_archived' => true]);

        return redirect()->route('home')->with('success', 'Проект отправлен в архив');
    }

    /**
     * Восстановление из архива.
     */
    public function restore(Project $project): RedirectResponse
    {
        $this->authorizeManager();
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