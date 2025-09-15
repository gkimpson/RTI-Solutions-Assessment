<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkTaskData;
use App\DTO\TaskData;
use App\Enums\TaskStatus;
use App\Exceptions\TaskConflictException;
use App\Exceptions\TaskNotDeletedException;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TaskService
{
    /**
     * Fields that are allowed for mass assignment in task creation/updates
     */
    private const ALLOWED_TASK_FIELDS = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'assigned_to',
        'user_id',
        'metadata',
    ];

    protected CacheService $cacheService;

    /**
     * Create a new TaskService instance.
     *
     * @param  CacheService  $cacheService  The cache service for managing task-related cache operations
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Create a new task with tags
     */
    public function createTask(TaskData $taskData, User $user): Task
    {
        $data = $taskData->toArray();

        // Set the creator user_id
        $data['user_id'] = $user->id;

        // Handle task assignment logic
        if (! $user->isAdmin()) {
            // Non-admin users can only create tasks assigned to themselves
            $data['assigned_to'] = $user->id;
        } elseif (! isset($data['assigned_to'])) {
            $data['assigned_to'] = null;
        }

        // Sanitize metadata if present
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = InputSanitizationService::sanitizeMetadata($data['metadata']);
        }

        // Filter data to only allowed fields to prevent mass assignment vulnerabilities
        $filteredData = Arr::only($data, self::ALLOWED_TASK_FIELDS);
        $task = Task::create($filteredData);

        // Attach tags if provided
        if (isset($data['tag_ids'])) {
            $task->tags()->attach($data['tag_ids']);
        }

        // Audit logging handled automatically by TaskObserver

        // Clear relevant caches
        if ($task->assigned_to) {
            $this->cacheService->clearUserStats($task->assigned_to);
        }

        return $task;
    }

    /**
     * Update a task with optimistic locking
     */
    public function updateTask(Task $task, TaskData $taskData, int $version): Task
    {
        return DB::transaction(function () use ($task, $taskData, $version) {
            $data = $taskData->toArray();
            $before = $task->toArray();

            // Remove tag_ids and version from data as they're handled separately
            unset($data['tag_ids'], $data['version']);

            // Filter data to only allowed fields to prevent mass assignment vulnerabilities
            $filteredData = Arr::only($data, self::ALLOWED_TASK_FIELDS);

            // Perform atomic update with version check using executeVersionedUpdate
            $result = $this->executeVersionedUpdate(
                $task->id,
                $version,
                $filteredData,
                'update'
            );

            if (! $result['success']) {
                throw new TaskConflictException($result['error']);
            }

            // Reload task with fresh data from database (more efficient than refresh)
            $task = $task->fresh();

            // Update tags if provided (atomic operation)
            if (isset($taskData->toArray()['tag_ids'])) {
                $task->tags()->sync($taskData->toArray()['tag_ids']);
            }

            // Clear relevant caches
            if ($task->assigned_to) {
                $this->cacheService->clearUserStats($task->assigned_to);
            }

            // Manual audit logging required because executeVersionedUpdate bypasses model events
            $changes = [];
            foreach ($filteredData as $field => $newValue) {
                $oldValue = $before[$field] ?? null;
                if ($newValue !== $oldValue) {
                    $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
                }
            }

            if (! empty($changes)) {
                try {
                    TaskLog::logUpdate($task, $changes, $before, Auth::user());
                } catch (\Exception $e) {
                    // Log the error but don't fail the operation
                    Log::error('Failed to log task update', [
                        'task_id' => $task->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $task;
        });
    }

    /**
     * Toggle task status
     */
    public function toggleTaskStatus(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $before = $task->toArray();
            $previousStatus = $task->status;
            $currentVersion = $task->version;

            // Determine next status
            $nextStatus = match ($task->status) {
                TaskStatus::Pending => TaskStatus::InProgress->value,
                TaskStatus::InProgress => TaskStatus::Completed->value,
                TaskStatus::Completed => TaskStatus::Pending->value,
            };

            // Perform atomic update with version check using executeVersionedUpdate
            $result = $this->executeVersionedUpdate(
                $task->id,
                $currentVersion,
                [
                    'status' => $nextStatus,
                ],
                'toggle status'
            );

            if (! $result['success']) {
                throw new TaskConflictException($result['error']);
            }

            // Reload task with updated data efficiently
            $task = $task->fresh();

            // Clear relevant caches
            if ($task->assigned_to) {
                $this->cacheService->clearUserStats($task->assigned_to);
            }

            // Manual audit logging required because executeVersionedUpdate bypasses model events
            try {
                TaskLog::logStatusToggle($task, $previousStatus, $nextStatus, Auth::user());
            } catch (\Exception $e) {
                // Log the error but don't fail the operation
                Log::error('Failed to log task status toggle', [
                    'task_id' => $task->id,
                    'from_status' => $previousStatus,
                    'to_status' => $nextStatus,
                    'error' => $e->getMessage(),
                ]);
            }

            return $task;
        });
    }

    /**
     * Restore a soft deleted task
     */
    public function restoreTask(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            if (! $task->trashed()) {
                throw new TaskNotDeletedException;
            }

            $before = $task->toArray();
            $originalVersion = $task->version;

            // Perform atomic restore with version increment using executeVersionedUpdate
            $result = $this->executeVersionedUpdate(
                $task->id,
                $originalVersion,
                ['deleted_at' => null],
                'restore',
                true // withTrashed = true for restore operations
            );

            if (! $result['success']) {
                throw new TaskConflictException($result['error']);
            }

            // Reload task with updated data efficiently
            $task = $task->fresh();

            // Clear relevant caches
            if ($task->assigned_to) {
                $this->cacheService->clearUserStats($task->assigned_to);
            }

            // Manual audit logging required because executeVersionedUpdate bypasses model events
            try {
                TaskLog::logRestore($task, Auth::user());
            } catch (\Exception $e) {
                // Log the error but don't fail the operation
                Log::error('Failed to log task restore', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $task;
        });
    }

    /**
     * Perform bulk operations on tasks with proper optimistic locking and chunking.
     *
     * This method processes bulk operations on multiple tasks while supporting
     * optimistic locking through version checking. Tasks are processed in chunks
     * to prevent memory issues and long-running transactions.
     *
     * @param  BulkTaskData  $bulkData  The bulk operation data containing:
     *                                  - action: The operation to perform (delete, restore, update_status)
     *                                  - taskIds: Array of task IDs to operate on
     *                                  - status: Status to set (required for update_status action)
     *                                  - versions: Optional array of expected versions for optimistic locking
     * @return array Result array containing:
     *               - message: Human-readable summary of the operation
     *               - processed: Number of tasks successfully processed
     *               - total: Total number of tasks in the request
     *               - conflicts: Number of version conflicts encountered
     *               - errors: Array of error messages for failed operations
     *               - processing_time: Time taken to process the bulk operation
     *               - chunks_processed: Number of chunks processed
     *
     * Performance Optimizations:
     * - Chunks large operations to prevent memory issues
     * - Uses separate transactions per chunk to avoid long locks
     * - Pre-fetches tasks to avoid N+1 queries within chunks
     * - Monitors memory usage and processing time
     */
    public function bulkOperation(BulkTaskData $bulkData): array
    {
        $startTime = microtime(true);
        $action = $bulkData->action;
        $taskIds = $bulkData->taskIds;
        $versions = $bulkData->versions ?? [];

        // Get chunk size from configuration
        $chunkSize = config('performance.bulk_operations.chunk_size', 100);
        $maxOperations = config('performance.bulk_operations.max_operations', 1000);

        // Validate operation size
        if (count($taskIds) > $maxOperations) {
            throw new \InvalidArgumentException("Bulk operation exceeds maximum allowed operations ({$maxOperations})");
        }

        $totalSuccessCount = 0;
        $totalErrors = [];
        $totalConflictCount = 0;
        $chunksProcessed = 0;

        // Process in chunks to manage memory and transaction size
        $taskIdChunks = array_chunk($taskIds, $chunkSize);

        foreach ($taskIdChunks as $chunkIndex => $taskIdChunk) {
            $chunkResult = $this->processBulkChunk(
                $taskIdChunk,
                $action,
                $bulkData,
                $versions,
                $chunkIndex
            );

            $totalSuccessCount += $chunkResult['processed'];
            $totalConflictCount += $chunkResult['conflicts'];
            $totalErrors = [...$totalErrors, ...$chunkResult['errors']];
            $chunksProcessed++;

            // Monitor memory usage and potentially break if needed
            if (memory_get_usage(true) > (config('performance.bulk_operations.memory_limit_mb', 128) * 1024 * 1024)) {
                $totalErrors[] = "Memory limit reached, stopping processing at chunk {$chunkIndex}";
                break;
            }
        }

        $actionPast = match ($action) {
            'delete' => 'deleted',
            'restore' => 'restored',
            'update_status' => 'updated',
            default => 'processed',
        };

        $message = "{$totalSuccessCount} tasks {$actionPast} successfully";
        if ($totalConflictCount > 0) {
            $message .= " ({$totalConflictCount} version conflicts encountered)";
        }
        if ($chunksProcessed > 1) {
            $message .= " (processed in {$chunksProcessed} chunks)";
        }

        // Clear all user stats cache since bulk operations affect multiple users
        if ($totalSuccessCount > 0) {
            $this->cacheService->clearAllUserStats();
        }

        $processingTime = microtime(true) - $startTime;

        return [
            'message' => $message,
            'processed' => $totalSuccessCount,
            'total' => count($taskIds),
            'conflicts' => $totalConflictCount,
            'errors' => $totalErrors,
            'processing_time' => $processingTime,
            'chunks_processed' => $chunksProcessed,
        ];
    }

    /**
     * Check if the current user is authorized to perform a specific action on a task
     */
    private function canPerformTaskAction(Task $task, string $action): bool
    {
        return match ($action) {
            'delete' => Gate::allows('delete', $task),
            'restore' => Gate::allows('restore', $task),
            'update_status' => Gate::allows('update', $task), // Status updates use the update permission
            default => false
        };
    }

    /**
     * Execute a database update with optimistic locking and return result array
     *
     * @param  int  $taskId  The task ID
     * @param  int  $expectedVersion  Expected version for optimistic locking
     * @param  array  $updateData  Data to update
     * @param  string  $operation  Operation name for error messages (delete, restore, update)
     * @param  bool  $withTrashed  Whether to include soft-deleted models in the query
     * @return array Result array with success status, conflict flag, and error message if applicable
     */
    private function executeVersionedUpdate(
        int $taskId,
        int $expectedVersion,
        array $updateData,
        string $operation,
        bool $withTrashed = false
    ): array {
        // Always increment version for optimistic locking
        $updateData['version'] = $expectedVersion + 1;

        // Build query based on whether we need trashed records
        $query = $withTrashed ? Task::withTrashed() : Task::query();

        $affected = $query
            ->where('id', $taskId)
            ->where('version', $expectedVersion)
            ->update($updateData);

        if ($affected === 0) {
            // Fetch current version for better error message
            $currentTaskQuery = $withTrashed ? Task::withTrashed() : Task::query();
            $currentTask = $currentTaskQuery->find($taskId);
            $currentVersion = $currentTask->version ?? 'unknown';

            return [
                'success' => false,
                'conflict' => true,
                'error' => "Task {$taskId}: Version conflict during {$operation}. Expected version {$expectedVersion}, but found {$currentVersion}.",
            ];
        }

        return [
            'success' => true,
            'conflict' => false,
            'error' => null,
        ];
    }

    /**
     * Process a chunk of tasks in a single transaction
     *
     * @throws \Throwable
     */
    private function processBulkChunk(
        array $taskIds,
        string $action,
        BulkTaskData $bulkData,
        array $versions,
        int $chunkIndex
    ): array {
        return DB::transaction(function () use ($taskIds, $action, $bulkData) {
            $successCount = 0;
            $errors = [];
            $conflictCount = 0;

            // Pre-fetch all tasks for this chunk (avoiding N+1 queries)
            $query = $action === 'restore' ? Task::withTrashed() : Task::query();
            $tasks = $query->whereIn('id', $taskIds)->get()->keyBy('id');

            // Process each task in the chunk
            foreach ($taskIds as $index => $taskId) {
                try {
                    // Get the pre-fetched task
                    $task = $tasks->get($taskId);
                    if (! $task) {
                        $errors[] = "Task {$taskId}: Task not found";

                        continue;
                    }

                    // Check authorization for this specific task
                    if (! $this->canPerformTaskAction($task, $action)) {
                        $errors[] = "Task {$taskId}: Not authorized to {$action} this task";

                        continue;
                    }

                    // Get the expected version for this task if provided
                    $expectedVersion = $bulkData->getVersionForTask($taskId, $index);

                    $result = match ($action) {
                        'delete' => $this->processBulkDeleteOptimized($task, $expectedVersion),
                        'restore' => $this->processBulkRestoreOptimized($task, $expectedVersion),
                        'update_status' => $this->processBulkStatusUpdateOptimized($task, $bulkData->status, $expectedVersion),
                        default => throw new \InvalidArgumentException("Invalid bulk action: {$action}")
                    };

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        if ($result['conflict']) {
                            $conflictCount++;
                        }
                        $errors[] = $result['error'];
                    }
                } catch (\Exception $e) {
                    $errors[] = "Task {$taskId}: {$e->getMessage()}";
                }
            }

            return [
                'processed' => $successCount,
                'conflicts' => $conflictCount,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Generic method to perform bulk operations with version checking and logging.
     *
     * @param  Task  $task  Pre-fetched task model
     * @param  int|null  $expectedVersion  Optional expected version for optimistic locking
     * @param  array  $updateData  Data to update in the database
     * @param  string  $operation  Operation name for error messages (delete, restore, update)
     * @param  bool  $withTrashed  Whether to include soft-deleted models in the query
     * @param  string  $logOperationType  Type of operation for logging
     * @param  array  $logChanges  Changes to log
     * @param  bool  $validateTrashedState  Whether to validate trashed state
     * @param  bool  $shouldbeTrashed  Expected trashed state for validation
     * @return array Result array with success status, conflict flag, and error message if applicable
     */
    private function performBulkOperation(
        Task $task,
        ?int $expectedVersion,
        array $updateData,
        string $operation,
        bool $withTrashed,
        string $logOperationType,
        array $logChanges,
        bool $validateTrashedState = false,
        bool $shouldbeTrashed = false
    ): array {
        // Validate trashed state if needed
        if ($validateTrashedState) {
            $isTrashed = $task->trashed();
            if ($shouldbeTrashed && ! $isTrashed) {
                return [
                    'success' => false,
                    'conflict' => false,
                    'error' => "Task {$task->id}: Cannot restore - task is not deleted",
                ];
            }
            if (! $shouldbeTrashed && $isTrashed) {
                return [
                    'success' => false,
                    'conflict' => false,
                    'error' => "Task {$task->id}: Cannot delete - task is already deleted",
                ];
            }
        }

        $originalVersion = $task->version;
        $before = $task->toArray();

        // If an expected version is provided, use it for the version check
        // Otherwise, use the current version (which will always match, ensuring no conflict)
        $versionToCheck = $expectedVersion ?? $originalVersion;

        // Perform atomic update with version check
        $result = $this->executeVersionedUpdate(
            $task->id,
            $versionToCheck,
            $updateData,
            $operation,
            $withTrashed // incrementVersion = true
        );

        if (! $result['success']) {
            return $result;
        }

        // Log the operation - use fresh() to reload model efficiently
        $task = $task->fresh();
        try {
            TaskLog::logOperation(
                $task,
                $logOperationType,
                Auth::user(),
                array_merge($logChanges, ['bulk_operation' => true]),
                $before,
                $task->toArray()
            );
        } catch (\Exception $e) {
            // Log the error but don't fail the bulk operation
            Log::error('Failed to log bulk operation', [
                'task_id' => $task->id,
                'operation_type' => $logOperationType,
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => true, 'conflict' => false, 'error' => null];
    }

    /**
     * Optimized bulk delete operation that works with pre-fetched Task model.
     *
     * @param  Task  $task  Pre-fetched task model
     * @param  int|null  $expectedVersion  Optional expected version for optimistic locking
     * @return array Result array with success status, conflict flag, and error message if applicable
     */
    private function processBulkDeleteOptimized(Task $task, ?int $expectedVersion = null): array
    {
        return $this->performBulkOperation(
            task: $task,
            expectedVersion: $expectedVersion,
            updateData: ['deleted_at' => now()],
            operation: 'delete',
            withTrashed: false,
            logOperationType: TaskLog::OPERATION_DELETE,
            logChanges: [],
            validateTrashedState: true,
            shouldbeTrashed: false
        );
    }

    /**
     * Optimized bulk restore operation that works with pre-fetched Task model.
     *
     * @param  Task  $task  Pre-fetched task model (with trashed)
     * @param  int|null  $expectedVersion  Optional expected version for optimistic locking
     * @return array Result array with success status, conflict flag, and error message if applicable
     */
    private function processBulkRestoreOptimized(Task $task, ?int $expectedVersion = null): array
    {
        return $this->performBulkOperation(
            task: $task,
            expectedVersion: $expectedVersion,
            updateData: ['deleted_at' => null],
            operation: 'restore',
            withTrashed: true,
            logOperationType: TaskLog::OPERATION_RESTORE,
            logChanges: [],
            validateTrashedState: true,
            shouldbeTrashed: true
        );
    }

    /**
     * Optimized bulk status update operation that works with pre-fetched Task model.
     *
     * @param  Task  $task  Pre-fetched task model
     * @param  string  $status  The status to set for the task
     * @param  int|null  $expectedVersion  Optional expected version for optimistic locking
     * @return array Result array with success status, conflict flag, and error message if applicable
     */
    private function processBulkStatusUpdateOptimized(Task $task, string $status, ?int $expectedVersion = null): array
    {
        return $this->performBulkOperation(
            task: $task,
            expectedVersion: $expectedVersion,
            updateData: ['status' => $status],
            operation: 'status update',
            withTrashed: false,
            logOperationType: TaskLog::OPERATION_UPDATE,
            logChanges: [
                'status' => ['from' => $task->status, 'to' => $status],
                'version_updated' => true,
            ]
        );
    }

    
}
