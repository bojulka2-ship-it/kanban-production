<?php

namespace Tests\Feature;

use App\Models\TaskItem;

class TaskTrackerTest extends BoardTestCase
{
    // --- Поля задачи: описание / дедлайн / приоритет ---

    public function test_responsible_employee_updates_own_task_fields(): void
    {
        $due = now()->addDays(2)->toDateString();

        $this->actingAs($this->employee)
            ->patchJson("/project-tasks/{$this->task->id}", [
                'description' => 'Согласовать документацию',
                'due_date' => $due,
                'priority' => 'high',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'priority' => 'high', 'due_date' => now()->addDays(2)->format('d.m.Y')]);

        $this->task->refresh();
        $this->assertSame('high', $this->task->priority);
        $this->assertSame('Согласовать документацию', $this->task->description);
        $this->assertSame($due, $this->task->due_date->toDateString());
    }

    public function test_manager_updates_any_task_fields(): void
    {
        $this->actingAs($this->manager)
            ->patchJson("/project-tasks/{$this->task->id}", ['priority' => 'low'])
            ->assertOk()
            ->assertJson(['priority' => 'low']);
    }

    public function test_stranger_cannot_update_foreign_task_fields(): void
    {
        $this->actingAs($this->other)
            ->patchJson("/project-tasks/{$this->task->id}", ['priority' => 'high'])
            ->assertForbidden();
    }

    public function test_archived_project_blocks_field_updates(): void
    {
        $this->project->update(['is_archived' => true]);

        $this->actingAs($this->manager)
            ->patchJson("/project-tasks/{$this->task->id}", ['priority' => 'high'])
            ->assertForbidden();
    }

    public function test_priority_must_be_known_value(): void
    {
        $this->actingAs($this->employee)
            ->patchJson("/project-tasks/{$this->task->id}", ['priority' => 'urgent'])
            ->assertStatus(422);
    }

    // --- Чек-лист ---

    public function test_responsible_runs_full_item_lifecycle(): void
    {
        // add
        $this->actingAs($this->employee)
            ->postJson("/project-tasks/{$this->task->id}/items", ['title' => 'Пункт 1'])
            ->assertOk()
            ->assertJson(['done' => 0, 'total' => 1, 'can_manage' => true]);

        $item = $this->task->items()->firstOrFail();

        // toggle
        $this->actingAs($this->employee)
            ->patchJson("/project-tasks/{$this->task->id}/items/{$item->id}", ['is_done' => true])
            ->assertOk()
            ->assertJson(['done' => 1, 'total' => 1]);
        $this->assertTrue($item->fresh()->is_done);

        // delete
        $this->actingAs($this->employee)
            ->deleteJson("/project-tasks/{$this->task->id}/items/{$item->id}")
            ->assertOk()
            ->assertJson(['done' => 0, 'total' => 0]);
        $this->assertSame(0, TaskItem::count());
    }

    public function test_stranger_cannot_manage_items(): void
    {
        $item = $this->task->items()->create(['title' => 'Пункт', 'sort_order' => 1]);

        $this->actingAs($this->other)
            ->postJson("/project-tasks/{$this->task->id}/items", ['title' => 'Взлом'])
            ->assertForbidden();

        $this->actingAs($this->other)
            ->patchJson("/project-tasks/{$this->task->id}/items/{$item->id}", ['is_done' => true])
            ->assertForbidden();

        $this->actingAs($this->other)
            ->deleteJson("/project-tasks/{$this->task->id}/items/{$item->id}")
            ->assertForbidden();

        $this->assertFalse($item->fresh()->is_done);
        $this->assertSame(1, $this->task->items()->count());
    }

    public function test_stranger_can_view_items_but_cannot_manage(): void
    {
        $this->task->items()->create(['title' => 'Пункт', 'sort_order' => 1, 'is_done' => true]);

        $this->actingAs($this->other)
            ->getJson("/project-tasks/{$this->task->id}/items")
            ->assertOk()
            ->assertJson(['done' => 1, 'total' => 1, 'can_manage' => false]);
    }

    public function test_item_from_another_task_is_not_found(): void
    {
        $item = $this->task->items()->create(['title' => 'Пункт', 'sort_order' => 1]);
        $foreign = $this->project->projectTasks()->where('id', '!=', $this->task->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->patchJson("/project-tasks/{$foreign->id}/items/{$item->id}", ['is_done' => true])
            ->assertNotFound();
    }

    // --- Модалка: деталь содержит поля трекера ---

    public function test_detail_contains_tracker_fields(): void
    {
        $this->task->update(['priority' => 'high', 'due_date' => now()->subDay()->toDateString()]);
        $this->task->items()->create(['title' => 'Пункт', 'sort_order' => 1, 'is_done' => true]);

        $this->actingAs($this->manager)
            ->getJson("/project-tasks/{$this->task->id}/detail")
            ->assertOk()
            ->assertJsonStructure(['description', 'due_date', 'due', 'priority', 'can_edit', 'items' => ['items', 'done', 'total', 'can_manage']])
            ->assertJson(['priority' => 'high', 'can_edit' => true, 'items' => ['done' => 1, 'total' => 1]]);
    }
}
