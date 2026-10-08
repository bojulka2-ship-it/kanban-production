<?php

namespace App\Policies;

use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectTaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProjectTask $projectTask): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'manager';
    }

    public function update(User $user, ProjectTask $projectTask): bool
    {
        return $this->canManipulate($user, $projectTask);
    }

    public function move(User $user, ProjectTask $projectTask): bool
    {
        return $this->canManipulate($user, $projectTask);
    }

    public function complete(User $user, ProjectTask $projectTask): bool
    {
        return $this->canManipulate($user, $projectTask);
    }

    public function reopen(User $user, ProjectTask $projectTask): bool
    {
        return $this->canManipulate($user, $projectTask);
    }

    public function comment(User $user, ProjectTask $projectTask): bool
    {
        return $this->canManipulate($user, $projectTask);
    }

    private function canManipulate(User $user, ProjectTask $projectTask): bool
    {
        // Архивный проект — только просмотр (движение, этапы, комментарии запрещены)
        if ($projectTask->project->is_archived) {
            return false;
        }

        if ($user->role->value === 'manager') {
            return true;
        }
        return $projectTask->responsible_id === $user->id;
    }
}
