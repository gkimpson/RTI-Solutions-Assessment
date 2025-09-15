<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine if the given task can be viewed by the user.
     */
    public function view(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $task->assigned_to !== null && $task->assigned_to === $user->id;
    }

    /**
     * Determine if the user can view any tasks.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine if the user can create tasks.
     */
    public function create(): bool
    {
        return true;
    }

    /**
     * Determine if the given task can be updated by the user.
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $task->assigned_to !== null && $task->assigned_to === $user->id;
    }

    /**
     * Determine if the given task can be deleted by the user.
     */
    public function delete(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $task->assigned_to !== null && $task->assigned_to === $user->id;
    }

    /**
     * Determine if the user can restore the given task.
     */
    public function restore(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $task->assigned_to !== null && $task->assigned_to === $user->id;
    }

    /**
     * Determine if the user can permanently delete the given task.
     */
    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can assign tasks to other users.
     */
    public function assign(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can toggle the status of a task.
     */
    public function toggleStatus(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $task->assigned_to !== null && $task->assigned_to === $user->id;
    }

    /**
     * Determine if the user can perform bulk operations on tasks.
     * Individual task permissions are still checked per task within the bulk operation.
     */
    public function bulk(): bool
    {
        return true;
    }
}
