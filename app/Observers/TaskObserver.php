<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Support\Facades\Auth;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        TaskLog::logCreate($task, Auth::user());
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Only log if there are actual changes
        if ($task->isDirty()) {
            $changes = $this->calculateChanges($task);
            $oldValues = $this->getOriginalValues($task);

            TaskLog::logUpdate($task, $changes, $oldValues, Auth::user());
        }
    }

    /**
     * Handle the Task "deleting" event.
     * This fires before the task is actually deleted, ensuring the foreign key relationship is still valid.
     */
    public function deleting(Task $task): void
    {
        TaskLog::logDelete($task, Auth::user());
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        TaskLog::logRestore($task, Auth::user());
    }

    /**
     * Calculate changes between current and original values
     */
    private function calculateChanges(Task $task): array
    {
        $changes = [];
        $dirtyFields = $task->getDirty();
        $originalFields = $task->getOriginal();

        foreach ($dirtyFields as $field => $newValue) {
            $oldValue = $originalFields[$field] ?? null;

            // Skip if values are the same (shouldn't happen with isDirty, but safety check)
            if ($newValue !== $oldValue) {
                $changes[$field] = [
                    'from' => $oldValue,
                    'to' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get original values for fields that changed
     */
    private function getOriginalValues(Task $task): array
    {
        $original = [];
        $dirtyFields = $task->getDirty();
        $originalFields = $task->getOriginal();

        foreach (array_keys($dirtyFields) as $field) {
            $original[$field] = $originalFields[$field] ?? null;
        }

        return $original;
    }
}
