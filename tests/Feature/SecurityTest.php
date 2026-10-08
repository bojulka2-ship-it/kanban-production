<?php

namespace Tests\Feature;

use App\Models\User;

class SecurityTest extends BoardTestCase
{
    // --- XSS: вставка разметки должна отображаться текстом ---

    public function test_html_in_project_title_is_escaped_on_board(): void
    {
        $this->project->update(['title' => '<script>alert(1)</script>']);

        $html = $this->actingAs($this->employee)->get('/')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_html_in_comment_body_is_not_rendered(): void
    {
        $payload = '<img src=x onerror=alert(1)>';

        $this->actingAs($this->employee)
            ->postJson('/comments', ['project_task_id' => $this->task->id, 'body' => $payload])
            ->assertCreated();

        $json = $this->actingAs($this->employee)
            ->getJson("/project-tasks/{$this->task->id}/comments")
            ->getContent();

        // Разметка не должна попадать в ответ в сыром виде (клиент вставляет её через x-html)
        $this->assertStringNotContainsString('<img src=x onerror=', $json);
    }

    // --- SQL-инъекции в фильтре поиска ---

    public function test_sql_injection_in_search_filter_is_harmless(): void
    {
        $payloads = [
            "' OR '1'='1",
            "'; DROP TABLE projects; --",
            "1' UNION SELECT * FROM users --",
            "%",
            "_",
        ];

        foreach ($payloads as $q) {
            $response = $this->actingAs($this->employee)->get('/?q=' . urlencode($q));
            $response->assertOk();
        }

        // Никакой из payload'ов не должен вернуть чужие проекты
        $this->actingAs($this->employee)
            ->get('/?q=' . urlencode("' OR '1'='1"))
            ->assertDontSee('Тест-проект', false);
    }

    // --- Mass assignment ---

    public function test_extra_fields_in_project_store_are_ignored(): void
    {
        $this->actingAs($this->manager)->post('/projects', [
            'title' => 'Проект-обход',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'responsible' => [1 => $this->employee->id, 2 => $this->employee->id, 3 => $this->employee->id, 4 => $this->employee->id],
            // попытка протащить служебные поля
            'is_archived' => 1,
            'status' => 'cancelled',
            'id' => 999,
        ])->assertRedirect('/');

        $project = \App\Models\Project::where('title', 'Проект-обход')->firstOrFail();

        $this->assertFalse($project->is_archived);
        $this->assertSame('active', $project->status);
    }

    public function test_employee_cannot_change_own_role(): void
    {
        $this->actingAs($this->employee)
            ->patch("/users/{$this->employee->id}", [
                'name' => $this->employee->name,
                'email' => $this->employee->email,
                'role' => 'manager',
            ])->assertForbidden();

        $this->assertSame('employee', $this->employee->fresh()->role->value);
    }

    // --- Служебные файлы недоступны через HTTP ---

    public function test_env_and_git_are_not_served(): void
    {
        $this->get('/.env')->assertNotFound();
        $this->get('/.git/config')->assertNotFound();
    }

    // --- Данные пользователей не утекают в JSON ---

    public function test_password_hash_is_not_exposed(): void
    {
        $html = $this->actingAs($this->manager)->get('/users')->getContent();

        $this->assertStringNotContainsString($this->employee->getRawOriginal('password'), $html);
    }
}
