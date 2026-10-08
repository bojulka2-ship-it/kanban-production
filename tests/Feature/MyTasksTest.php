<?php

namespace Tests\Feature;

class MyTasksTest extends BoardTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('my-tasks.index'))->assertRedirect(route('login'));
    }

    public function test_employee_sees_only_own_tasks(): void
    {
        $response = $this->actingAs($this->employee)->get(route('my-tasks.index'));

        $response->assertOk();
        $response->assertSee('Тест-проект');
        $response->assertDontSee('Чужой проект');

        // В проекте 4 задачи, все — за сотрудником
        $this->assertCount(4, $response->viewData('tasks'));
    }

    public function test_empty_state_for_user_without_tasks(): void
    {
        $this->actingAs($this->manager)
            ->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee('Задач нет');
    }

    public function test_sorting_overdue_first_then_due_date_nulls_last(): void
    {
        $tasks = $this->project->projectTasks()->orderBy('id')->get();
        $tasks[0]->update(['due_date' => now()->subDays(2)->toDateString()]);
        $tasks[1]->update(['due_date' => now()->addDays(5)->toDateString()]);
        $tasks[2]->update(['due_date' => null]);
        $tasks[3]->update(['due_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($this->employee)->get(route('my-tasks.index'));

        $order = $response->viewData('tasks')->pluck('id')->all();

        $this->assertSame(
            [$tasks[0]->id, $tasks[3]->id, $tasks[1]->id, $tasks[2]->id],
            $order
        );
    }

    public function test_filter_by_priority(): void
    {
        $tasks = $this->project->projectTasks()->orderBy('id')->get();
        $tasks[0]->update(['priority' => 'high']);

        $response = $this->actingAs($this->employee)->get(route('my-tasks.index', ['priority' => 'high']));

        $filtered = $response->viewData('tasks');
        $this->assertCount(1, $filtered);
        $this->assertSame($tasks[0]->id, $filtered->first()->id);
    }

    public function test_filter_by_project(): void
    {
        $tasks = $this->project->projectTasks()->orderBy('id')->get();

        $response = $this->actingAs($this->employee)
            ->get(route('my-tasks.index', ['project' => $this->project->id]));

        $this->assertCount(4, $response->viewData('tasks'));
        $this->assertSame($tasks[0]->id, $response->viewData('tasks')->first()->id);
    }

    public function test_filter_overdue_only(): void
    {
        $tasks = $this->project->projectTasks()->orderBy('id')->get();
        $tasks[0]->update(['due_date' => now()->subDay()->toDateString()]);
        $tasks[1]->update(['due_date' => now()->addDays(3)->toDateString()]);

        $response = $this->actingAs($this->employee)->get(route('my-tasks.index', ['overdue' => '1']));

        $filtered = $response->viewData('tasks');
        $this->assertCount(1, $filtered);
        $this->assertSame($tasks[0]->id, $filtered->first()->id);
    }

    public function test_archived_project_tasks_are_excluded(): void
    {
        $this->project->update(['is_archived' => true]);

        $response = $this->actingAs($this->employee)->get(route('my-tasks.index'));

        $this->assertCount(0, $response->viewData('tasks'));
        $response->assertDontSee('Тест-проект');
    }

    public function test_header_shows_overdue_counter(): void
    {
        $this->project->projectTasks()->orderBy('id')->first()
            ->update(['due_date' => now()->subDay()->toDateString()]);

        $this->actingAs($this->employee)
            ->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee('Просроченных задач: 1', false);
    }
}
