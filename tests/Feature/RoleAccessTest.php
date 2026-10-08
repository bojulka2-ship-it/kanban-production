<?php

namespace Tests\Feature;

class RoleAccessTest extends BoardTestCase
{
    // --- Раздел «Пользователи»: только руководитель ---

    public function test_employee_cannot_open_users_page(): void
    {
        $this->actingAs($this->employee)->get('/users')->assertForbidden();
    }

    public function test_manager_opens_users_page(): void
    {
        $this->actingAs($this->manager)->get('/users')->assertOk();
    }

    public function test_employee_cannot_create_update_or_toggle_users(): void
    {
        $this->actingAs($this->employee)
            ->post('/users', [
                'name' => 'Новый',
                'email' => 'new@test.local',
                'password' => 'password1',
                'role' => 'employee',
            ])->assertForbidden();

        $this->actingAs($this->employee)
            ->patch("/users/{$this->manager->id}", [
                'name' => 'Хакер',
                'email' => 'manager@test.local',
                'role' => 'employee',
            ])->assertForbidden();

        $this->actingAs($this->employee)
            ->post("/users/{$this->manager->id}/toggle-active")->assertForbidden();

        $this->actingAs($this->employee)
            ->post("/users/{$this->manager->id}/reset-password", ['password' => 'hacked123'])
            ->assertForbidden();
    }

    // --- Проекты ---

    public function test_employee_cannot_create_project(): void
    {
        $this->actingAs($this->employee)->post('/projects', [
            'title' => 'Проект',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'responsible' => [1 => $this->employee->id, 2 => $this->employee->id, 3 => $this->employee->id, 4 => $this->employee->id],
        ])->assertForbidden();
    }

    public function test_employee_cannot_edit_archive_restore_project(): void
    {
        $this->actingAs($this->employee)->get("/projects/{$this->project->id}/edit")->assertForbidden();
        $this->actingAs($this->employee)->post("/projects/{$this->project->id}/archive")->assertForbidden();
        $this->actingAs($this->employee)->post("/projects/{$this->project->id}/restore")->assertForbidden();
    }

    public function test_manager_archives_and_restores_project(): void
    {
        $this->actingAs($this->manager)
            ->post("/projects/{$this->project->id}/archive")
            ->assertRedirect('/');

        $this->assertTrue($this->project->fresh()->is_archived);

        $this->actingAs($this->manager)
            ->post("/projects/{$this->project->id}/restore")
            ->assertRedirect();

        $this->assertFalse($this->project->fresh()->is_archived);
    }

    // --- Доска: переносы и этапы ---

    public function test_responsible_employee_can_move_own_task(): void
    {
        $stageId = \App\Models\Stage::orderBy('sort_order')->value('id');

        $this->actingAs($this->employee)
            ->postJson("/project-tasks/{$this->task->id}/move", ['stage_id' => $stageId])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'in_progress']);
    }

    public function test_stranger_employee_cannot_move_foreign_task(): void
    {
        $stageId = \App\Models\Stage::orderBy('sort_order')->value('id');

        $this->actingAs($this->other)
            ->postJson("/project-tasks/{$this->task->id}/move", ['stage_id' => $stageId])
            ->assertForbidden();

        $this->actingAs($this->other)
            ->postJson("/project-tasks/{$this->task->id}/complete", ['stage_id' => $stageId])
            ->assertForbidden();
    }

    public function test_manager_can_move_any_task(): void
    {
        $stageId = \App\Models\Stage::orderBy('sort_order')->value('id');

        $this->actingAs($this->manager)
            ->postJson("/project-tasks/{$this->task->id}/move", ['stage_id' => $stageId])
            ->assertOk();
    }

    public function test_task_detail_is_viewable_by_authorized_users(): void
    {
        $this->actingAs($this->other)
            ->getJson("/project-tasks/{$this->task->id}/detail")
            ->assertOk()
            ->assertJsonStructure(['id', 'task', 'project', 'responsible', 'can_move', 'stages', 'history']);
    }

    // --- Архив: только просмотр ---

    public function test_archived_project_blocks_moves_and_comments(): void
    {
        $this->project->update(['is_archived' => true]);
        $stageId = \App\Models\Stage::orderBy('sort_order')->value('id');

        $this->actingAs($this->manager)
            ->postJson("/project-tasks/{$this->task->id}/move", ['stage_id' => $stageId])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->postJson('/comments', [
                'project_task_id' => $this->task->id,
                'body' => 'Комментарий в архиве',
            ])->assertForbidden();

        // Просмотр разрешён
        $this->actingAs($this->manager)
            ->getJson("/project-tasks/{$this->task->id}/detail")
            ->assertOk();
    }

    // --- Комментарии ---

    public function test_responsible_employee_comments_own_task(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/comments', [
                'project_task_id' => $this->task->id,
                'body' => 'Готовлю документацию',
            ])->assertCreated();
    }

    public function test_stranger_employee_cannot_comment_foreign_task(): void
    {
        $this->actingAs($this->other)
            ->postJson('/comments', [
                'project_task_id' => $this->task->id,
                'body' => 'Чужой комментарий',
            ])->assertForbidden();
    }

    public function test_comment_deletion_rules(): void
    {
        $comment = $this->task->comments()->create([
            'user_id' => $this->employee->id,
            'body' => 'Мой комментарий',
        ]);

        // Чужой сотрудник не удаляет
        $this->actingAs($this->other)
            ->deleteJson("/comments/{$comment->id}")
            ->assertForbidden();

        // Руководитель удаляет чужой
        $this->actingAs($this->manager)
            ->deleteJson("/comments/{$comment->id}")
            ->assertOk();
    }

    public function test_manager_forbidden_on_archived_project_update(): void
    {
        $this->project->update(['is_archived' => true]);

        $this->actingAs($this->manager)
            ->get("/projects/{$this->project->id}/edit")
            ->assertForbidden();
    }
}
