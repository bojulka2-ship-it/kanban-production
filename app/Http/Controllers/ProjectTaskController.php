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