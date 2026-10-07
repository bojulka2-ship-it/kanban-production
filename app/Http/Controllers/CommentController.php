<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Лента комментариев задачи (видят все авторизованные).
     * returns: { comments: [...], can_write, is_manager, me }.
     */
    public function index(ProjectTask $projectTask): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'comments' => $projectTask->comments()
                ->with('user')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Comment $c) => $this->formatted($c, $user)),
            'can_write' => $this->canWrite($projectTask, $user),
            'is_manager' => $user->role->value === 'manager',
            'me' => $user->id,
        ]);
    }

    /**
     * Создание комментария: ответственный за задачу или руководитель.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_task_id' => ['required', 'integer', 'exists:project_tasks,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $projectTask = ProjectTask::findOrFail($data['project_task_id']);
        $user = auth()->user();

        if (! $this->canWrite($projectTask, $user)) {
            abort(403, 'Комментировать может ответственный или руководитель');
        }

        $comment = $projectTask->comments()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'project_task_id' => $projectTask->id,
        ]);
        $comment->load('user');

        return response()->json($this->formatted($comment, $user), 201);
    }

    /**
     * Удаление: автор комментария или руководитель.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $user = auth()->user();

        if ($user->role->value !== 'manager' && $comment->user_id !== $user->id) {
            abort(403, 'Удалить комментарий может только автор или руководитель');
        }

        $comment->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Может ли пользователь комментировать задачу.
     */
    private function canWrite(ProjectTask $projectTask, $user): bool
    {
        return $user->role->value === 'manager'
            || $projectTask->responsible_id === $user->id;
    }

    /**
     * Комментарий для JSON-ленты.
     */
    private function formatted(Comment $comment, $user): array
    {
        return [
            'id' => $comment->id,
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'initials' => $comment->user->initials,
                'color' => $comment->user->avatar_color,
            ],
            'time' => $comment->created_at->format('d.m.Y H:i'),
            'body' => $comment->body,
            'canDelete' => $this->canDelete($comment, $user),
        ];
    }

    /**
     * Может ли пользователь удалить конкретный комментарий.
     */
    private function canDelete(Comment $comment, $user): bool
    {
        return $user->role->value === 'manager'
            || $comment->user_id === $user->id;
    }
}