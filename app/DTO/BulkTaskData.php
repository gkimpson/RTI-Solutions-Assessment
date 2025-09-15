<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\BulkAction;
use App\Enums\TaskStatus;
use InvalidArgumentException;

/**
 * Data Transfer Object for bulk task operations.
 *
 * This DTO represents the data structure for bulk operations on tasks,
 * including the action to perform, task IDs, and optional parameters
 * such as status for update operations and versions for optimistic locking.
 */
class BulkTaskData
{
    /**
     * Valid actions for bulk operations (deprecated - use BulkAction enum)
     */
    private const VALID_ACTIONS = ['delete', 'restore', 'update_status'];

    /**
     * Valid status values for update_status action (deprecated - use TaskStatus enum)
     */
    private const VALID_STATUSES = ['pending', 'in_progress', 'completed'];

    /**
     * Create a new BulkTaskData instance.
     *
     * @param  string  $action  The bulk operation to perform (delete, restore, update_status)
     * @param  array  $taskIds  Array of task IDs to perform the operation on
     * @param  string|null  $status  The status to set for tasks (required for update_status action)
     * @param  array|null  $versions  Array of expected versions for optimistic locking.
     *                                Can be indexed array [version1, version2, ...] or
     *                                associative array [taskId => version, ...].
     *                                If provided, each task's current version must match the
     *                                expected version or the operation will fail with a conflict.
     */
    public function __construct(
        public readonly string $action,
        public readonly array $taskIds,
        public readonly ?string $status = null,
        public readonly ?array $versions = null,
    ) {
        $this->validate();
    }

    /**
     * Create a new BulkTaskData instance from an array.
     *
     * @param  array  $data  The data array containing action, task_ids, status, and versions
     */
    public static function fromArray(array $data): self
    {
        // Convert task_ids to integers
        $taskIds = array_map('intval', $data['task_ids']);

        // Convert versions to integers if provided
        $versions = null;
        if (isset($data['versions']) && is_array($data['versions'])) {
            $versions = array_map(static fn ($version) => $version !== null ? (int) $version : null, $data['versions']);
        }

        return new self(
            action: $data['action'],
            taskIds: $taskIds,
            status: $data['status'] ?? null,
            versions: $versions,
        );
    }

    /**
     * Convert the DTO to an array.
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'task_ids' => $this->taskIds,
            'status' => $this->status,
            'versions' => $this->versions,
        ];
    }

    /**
     * Check if the action is valid.
     */
    public function isValidAction(): bool
    {
        return in_array($this->action, BulkAction::values());
    }

    /**
     * Check if versions are provided.
     */
    public function hasVersions(): bool
    {
        return ! empty($this->versions);
    }

    /**
     * Get the version for a specific task by index or task ID.
     *
     * @param  int  $taskId  The task ID
     * @param  int  $index  The index in the task IDs array
     * @return int|null The version for the task, or null if not provided
     */
    public function getVersionForTask(int $taskId, int $index): ?int
    {
        if (! $this->hasVersions()) {
            return null;
        }

        // Try to get version by index first (indexed array)
        return $this->versions[$index] ?? $this->versions[$taskId] ?? null;
    }

    /**
     * Validate the DTO data.
     *
     * @throws InvalidArgumentException When validation fails
     */
    private function validate(): void
    {
        // Validate action
        if (! $this->isValidAction()) {
            throw new InvalidArgumentException(
                sprintf('Action must be one of: %s', implode(', ', BulkAction::values()))
            );
        }

        // Validate task IDs
        if (empty($this->taskIds)) {
            throw new InvalidArgumentException('Task IDs cannot be empty');
        }

        // Check for duplicate task IDs
        if (count($this->taskIds) !== count(array_unique($this->taskIds))) {
            throw new InvalidArgumentException('Duplicate task IDs are not allowed');
        }

        // Validate all task IDs are positive integers
        foreach ($this->taskIds as $taskId) {
            if (! is_int($taskId) || $taskId <= 0) {
                throw new InvalidArgumentException('All task IDs must be positive integers');
            }
        }

        // Validate status for update_status action
        if ($this->action === 'update_status') {
            if (! $this->status) {
                throw new InvalidArgumentException('Status is required for update_status action');
            }
            if (! in_array($this->status, TaskStatus::values(), true)) {
                throw new InvalidArgumentException(
                    sprintf('Status must be one of: %s', implode(', ', TaskStatus::values()))
                );
            }
        }

        // Validate versions array if provided
        if ($this->versions !== null) {
            if (count($this->versions) > 0 && count($this->versions) !== count($this->taskIds)) {
                throw new InvalidArgumentException(
                    'Versions array length must match task IDs array length when provided'
                );
            }

            // Validate all versions are positive integers
            foreach ($this->versions as $version) {
                if ($version !== null && (! is_int($version) || $version <= 0)) {
                    throw new InvalidArgumentException('All versions must be positive integers or null');
                }
            }
        }
    }
}
