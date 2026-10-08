<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Models\TaskItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectTaskController extends Controller
{
    /**
     * Перенос задачи в этап (целевой stage_status -> in_progress, entered_at = now).
     * Возврат на пройденный этап пишет stage_reopened.
     */
    public function move(Request $request, ProjectTask $projectTask): JsonResponse
    {
        $this->authorize('move', $projectTask);

        $data = $request->validate([
            'stage_id' => ['required', 'exists:stages,id'],
        ]);

        $status = $projectTask->stageStatuses()
            ->where('stage_id', $data['stage_id'])
            ->firstOrFail();

        // Уже в работе на этом этапе — ничего не делаем (не сбрасываем дату)
        if ($status->status !== 'in_progress') {
            DB::transaction(function () use ($projectTask, $status) {
                $action = $status->status === 'done' ? 'stage_reopened' : 'stage_started';
                $status->update(['status' => 'in_progress', 'entered_at' => now()]);
                $this->logAction($projectTask, $status->stage_id, $action);
            });
        }

        $status->refresh();

        return response()->json([
            'ok' => true,
            'status' => $status->status,
            'entered_at' => $status->entered_at?->format('d.m.Y H:i'),
        ]);
    }

    /**
     * Завершение этапа (stage_status -> done, запись stage_completed).
     */
    public function complete(Request $request, ProjectTask $projectTask): JsonResponse
    {
        $this->authorize('complete', $projectTask);

        $data = $request->validate([
            'stage_id' => ['required', 'exists:stages,id'],
        ]);

        $status = $projectTask->stageStatuses()
            ->where('stage_id', $data['stage_id'])
            ->firstOrFail();

        if ($status->status !== 'done') {
            DB::transaction(function () use ($projectTask, $status) {
                $status->update(['status' => 'done']);
                $this->logAction($projectTask, $status->stage_id, 'stage_completed');
            });
        }

        $status->refresh();

        return response()->json([
            'ok' => true,
            'status' => $status->status,
        ]);
    }

    /**
     * JSON для модалки: задача, проект, ответственный, этапы и лента истории.
     */
    public function detail(ProjectTask $projectTask): JsonResponse
    {
        $this->authorize('view', $projectTask);

        $projectTask->load(['taskType', 'project', 'responsible']);

        $stages = $projectTask->stageStatuses()
            ->with('stage:id,name')
            ->orderBy('stage_id')
            ->get()
            ->map(fn ($ss) => [
                'id' => $ss->stage_id,
                'name' => $ss->stage->name,
                'status' => $ss->status,
                'entered_at' => $ss->entered_at?->format('d.m.Y'),
            ])->values();

        $verbs = [
            'stage_started' => 'начат',
            'stage_completed' => 'завершён',
            'stage_reopened' => 'возвращён в работу',
        ];

        $history = $projectTask->taskHistory()
            ->with('user:id,name', 'stage:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(function ($h) use ($verbs) {
                return [
                    'time' => $h->created_at->format('d.m.Y H:i'),
                    'user' => $this->shortName($h->user?->name),
                    'text' => 'этап «' . ($h->stage?->name ?? '—') . '» ' . ($verbs[$h->action] ?? $h->action),
                ];
            })->values();

        return response()->json([
            'id' => $projectTask->id,
            'task' => $projectTask->taskType->name,
            'project' => $projectTask->project->title,
            'responsible' => $projectTask->responsible?->name ?? '—',
            'can_move' => auth()->user()->can('move', $projectTask),
            'stages' => $stages,
            'history' => $history,
            // Поля трекера (этап 11)
            'description' => $projectTask->description,
            'due_date' => $projectTask->due_date?->format('d.m.Y'),
            'due_date_raw' => $projectTask->due_date?->format('Y-m-d'),
            'due' => $this->dueMeta($projectTask->due_date),
            'priority' => $projectTask->priority,
            'can_edit' => auth()->user()->can('update', $projectTask),
            'items' => $this->itemsPayload($projectTask),
        ]);
    }

    /**
     * Обновление полей трекера: описание, дедлайн, приоритет (этап 11).
     */
    public function update(Request $request, ProjectTask $projectTask): JsonResponse
    {
        $this->authorize('update', $projectTask);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'in:low,normal,high'],
        ]);

        $projectTask->update($data);
        $projectTask->refresh();

        return response()->json([
            'ok' => true,
            'description' => $projectTask->description,
            'due_date' => $projectTask->due_date?->format('d.m.Y'),
            'due' => $this->dueMeta($projectTask->due_date),
            'priority' => $projectTask->priority,
        ]);
    }

    /**
     * Лента пунктов чек-листа с прогрессом.
     */
    public function items(ProjectTask $projectTask): JsonResponse
    {
        $this->authorize('view', $projectTask);

        return response()->json($this->itemsPayload($projectTask));
    }

    /**
     * Добавление пункта чек-листа.
     */
    public function storeItem(Request $request, ProjectTask $projectTask): JsonResponse
    {
        $this->authorize('manageItems', $projectTask);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $projectTask->items()->create([
            'title' => $data['title'],
            'sort_order' => (int) $projectTask->items()->max('sort_order') + 1,
        ]);

        return response()->json($this->itemsPayload($projectTask));
    }

    /**
     * Отметка/переименование пункта чек-листа.
     */
    public function updateItem(Request $request, ProjectTask $projectTask, TaskItem $item): JsonResponse
    {
        $this->authorize('manageItems', $projectTask);
        abort_unless($item->project_task_id === $projectTask->id, 404);

        $data = $request->validate([
            'is_done' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $item->update($data);

        return response()->json($this->itemsPayload($projectTask));
    }

    /**
     * Удаление пункта чек-листа.
     */
    public function destroyItem(ProjectTask $projectTask, TaskItem $item): JsonResponse
    {
        $this->authorize('manageItems', $projectTask);
        abort_unless($item->project_task_id === $projectTask->id, 404);

        $item->delete();

        return response()->json($this->itemsPayload($projectTask));
    }

    /**
     * Пункты чек-листа + прогресс «N/M» и флаг права на управление.
     */
    private function itemsPayload(ProjectTask $projectTask): array
    {
        $items = $projectTask->items()->get()->map(fn (TaskItem $i) => [
            'id' => $i->id,
            'title' => $i->title,
            'is_done' => $i->is_done,
        ])->values();

        return [
            'items' => $items,
            'done' => $items->where('is_done', true)->count(),
            'total' => $items->count(),
            'can_manage' => auth()->user()->can('manageItems', $projectTask),
        ];
    }

    /**
     * Метаданные дедлайна: тон и подпись бейджа (та же логика, что у проекта).
     */
    private function dueMeta(?\Illuminate\Support\Carbon $date): ?array
    {
        if (! $date) {
            return null;
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        if ($daysLeft < 0) {
            return ['tone' => 'overdue', 'days' => $daysLeft, 'label' => 'Просрочен на ' . (-$daysLeft) . ' дн.'];
        }
        if ($daysLeft <= 3) {
            return [
                'tone' => 'soon',
                'days' => $daysLeft,
                'label' => $daysLeft === 0 ? 'Осталось сегодня' : 'Осталось ' . $daysLeft . ' дн.',
            ];
        }

        return ['tone' => 'ok', 'days' => $daysLeft, 'label' => ''];
    }

    /**
     * Имя в формате «Фамилия И.»
     */
    private function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim($name ?? ''));
        $short = $parts[0] ?? '—';
        if (isset($parts[1])) {
            $short .= ' ' . mb_substr($parts[1], 0, 1) . '.';
        }

        return $short;
    }

    /**
     * Запись в журнал перемещений.
     */
    private function logAction(ProjectTask $projectTask, int $stageId, string $action): void
    {
        $projectTask->taskHistory()->create([
            'user_id' => auth()->id(),
            'stage_id' => $stageId,
            'action' => $action,
        ]);
    }
}