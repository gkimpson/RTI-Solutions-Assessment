<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class TagPolicy
{
    /**
     * Determine if the given tag can be viewed by the user.
     */
    public function view(): bool
    {
        return true;
    }

    /**
     * Determine if the user can view any tags.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine if the user can create tags.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given tag can be updated by the user.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given tag can be deleted by the user.
     */
    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the given tag.
     */
    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the given tag.
     */
    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view tags with task counts.
     */
    public function viewWithCounts(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view tags with related tasks.
     */
    public function viewWithTasks(User $user): bool
    {
        return $user->isAdmin();
    }
}
