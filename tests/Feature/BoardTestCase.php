<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Общая база для тестов доски: справочники + руководитель + два сотрудника,
 * один проект с четырьмя задачами (все за employee), второй — за другим.
 */
abstract class BoardTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $employee;
    protected User $other;
    protected Project $project;
    protected ProjectTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceSeeder::class);

        $this->manager = User::create([
            'name' => 'Руководитель Тест',
            'email' => 'manager@test.local',
            'password' => 'password',
            'role' => 'manager',
        ]);
        $this->employee = User::create([
            'name' => 'Сотрудник Один',
            'email' => 'emp1@test.local',
            'password' => 'password',
            'role' => 'employee',
        ]);
        $this->other = User::create([
            'name' => 'Сотрудник Два',
            'email' => 'emp2@test.local',
            'password' => 'password',
            'role' => 'employee',
        ]);

        $this->project = $this->makeProject('Тест-проект');
        foreach ($this->project->projectTasks as $task) {
            $task->update(['responsible_id' => $this->employee->id]);
        }
        $this->task = $this->project->projectTasks()->firstOrFail();

        $another = $this->makeProject('Чужой проект');
        foreach ($another->projectTasks as $task) {
            $task->update(['responsible_id' => $this->other->id]);
        }
    }

    /**
     * Проект с 4 задачами (event created создаёт 5 stage_statuses на задачу).
     */
    protected function makeProject(string $title, array $overrides = []): Project
    {
        $project = Project::create(array_merge([
            'title' => $title,
            'start_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'active',
            'is_archived' => false,
        ], $overrides));

        foreach (\App\Models\TaskType::orderBy('sort_order')->get() as $taskType) {
            $project->projectTasks()->create([
                'task_type_id' => $taskType->id,
                'responsible_id' => $this->employee->id,
            ]);
        }

        return $project->load('projectTasks');
    }
}
