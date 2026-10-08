<?php

namespace Tests\Feature;

class ValidationTest extends BoardTestCase
{
    private function managerPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Новый проект',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'responsible' => [
                1 => $this->employee->id,
                2 => $this->employee->id,
                3 => $this->employee->id,
                4 => $this->employee->id,
            ],
        ], $overrides);
    }

    public function test_due_date_before_start_date_rejected(): void
    {
        $this->actingAs($this->manager)
            ->from('/')->post('/projects', $this->managerPayload([
                'start_date' => now()->toDateString(),
                'due_date' => now()->subDay()->toDateString(),
            ]))
            ->assertRedirect('/')
            ->assertSessionHasErrors('due_date');
    }

    public function test_title_required_and_max_100(): void
    {
        $this->actingAs($this->manager)
            ->from('/')->post('/projects', $this->managerPayload(['title' => '']))
            ->assertSessionHasErrors('title');

        $this->actingAs($this->manager)
            ->from('/')->post('/projects', $this->managerPayload(['title' => str_repeat('Длинное название ', 20)]))
            ->assertSessionHasErrors('title');
    }

    public function test_exactly_four_responsibles_required(): void
    {
        $payload = $this->managerPayload();
        unset($payload['responsible'][4]);

        $this->actingAs($this->manager)
            ->from('/')->post('/projects', $payload)
            ->assertSessionHasErrors('responsible');
    }

    public function test_inactive_responsible_rejected(): void
    {
        $this->other->update(['is_active' => false]);
        $payload = $this->managerPayload();
        $payload['responsible'][1] = $this->other->id;

        $this->actingAs($this->manager)
            ->from('/')->post('/projects', $payload)
            ->assertSessionHasErrors('responsible.1');
    }

    public function test_new_project_gets_exactly_20_stage_statuses(): void
    {
        $this->actingAs($this->manager)->post('/projects', $this->managerPayload());

        $project = \App\Models\Project::where('title', 'Новый проект')->firstOrFail();

        $this->assertSame(4, $project->projectTasks()->count());
        $this->assertSame(20, \App\Models\StageStatus::whereIn(
            'project_task_id',
            $project->projectTasks()->pluck('id')
        )->count());
        $this->assertSame('active', $project->status);
        $this->assertFalse($project->is_archived);
    }

    public function test_project_status_whitelist_enforced(): void
    {
        $this->actingAs($this->manager)
            ->from('/')->put("/projects/{$this->project->id}", $this->managerPayload([
                'title' => $this->project->title,
                'status' => 'hacked-status',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_comment_body_limits(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/comments', ['project_task_id' => $this->task->id, 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->actingAs($this->employee)
            ->postJson('/comments', ['project_task_id' => $this->task->id, 'body' => str_repeat('а', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->actingAs($this->employee)
            ->postJson('/comments', ['project_task_id' => 999999, 'body' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_task_id');
    }

    public function test_user_store_validation(): void
    {
        $this->actingAs($this->manager)
            ->from('/users')->post('/users', [
                'name' => '',
                'email' => 'manager@test.local', // дубликат
                'password' => 'short',
                'role' => 'root',
            ])
            ->assertRedirect('/users')
            ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    public function test_password_change_requires_confirmation(): void
    {
        $this->actingAs($this->employee)
            ->from('/password')->put('/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
            ])
            ->assertSessionHasErrors('password');
    }
}
