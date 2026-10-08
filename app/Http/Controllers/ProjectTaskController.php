<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
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
        ]);
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