<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTO\BulkTaskData;
use App\DTO\TaskData;
use App\Exceptions\TaskConflictException;
use App\Http\Requests\BulkTaskRequest;
use App\Http\Requests\FilterTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\CacheService;
use App\Services\TaskBulkOperationService;
use App\Services\TaskFilterService;
use App\Services\TaskPaginationService;
use App\Services\TaskService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class TaskController extends BaseApiController
{
    use AuthorizesRequests;

    protected TaskService $taskService;

    protected TaskFilterService $taskFilterService;

    protected TaskPaginationService $taskPaginationService;

    protected TaskBulkOperationService $taskBulkOperationService;

    protected CacheService $cacheService;

    /**
     * Create a new TaskController instance.
     *
     * @param  TaskService  $taskService  Service for core task operations
     * @param  TaskFilterService  $taskFilterService  Service for filtering and searching tasks
     * @param  TaskPaginationService  $taskPaginationService  Service for handling pagination
     * @param  TaskBulkOperationService  $taskBulkOperationService  Service for bulk operations
     * @param  CacheService  $cacheService  Service for cache management
     */
    public function __construct(
        TaskService $taskService,
        TaskFilterService $taskFilterService,
        TaskPaginationService $taskPaginationService,
        TaskBulkOperationService $taskBulkOperationService,
        CacheService $cacheService
    ) {
        $this->taskService = $taskService;
        $this->taskFilterService = $taskFilterService;
        $this->taskPaginationService = $taskPaginationService;
        $this->taskBulkOperationService = $taskBulkOperationService;
        $this->cacheService = $cacheService;
    }

    /**
     * Display a listing of tasks.
     */
    public function index(FilterTasksRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        // Generate cache key based on user, filters, and pagination
        $cacheKey = $this->generateTaskIndexCacheKey($request);

        // Check if caching is enabled and try cache first
        if (config('performance.cache.enabled', true)) {
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                $cachedResult = $this->taskPaginationService->setCachedFlag($cachedResult, true);

                return $this->dataResponse($cachedResult);
            }
        }

        // Build query with relationships
        $query = Task::query();

        // Apply filters and sorting first
        $query = $this->taskFilterService->applyFilters($query, $request);
        $query = $this->taskFilterService->applySorting($query, $request);

        // Optimize query loading based on applied filters
        $query = $this->taskFilterService->optimizeQueryLoading($query, $request);

        // Get paginated result using the pagination service
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        // Cache the result if caching is enabled and appropriate
        if (config('performance.cache.enabled', true)) {
            $ttl = $this->taskFilterService->shouldUseAggressiveCaching($request)
                ? config('performance.cache.ttl.task_index', 300)
                : config('performance.cache.ttl.task_index_short', 60);

            Cache::put($cacheKey, $result, $ttl);
        }

        return $this->dataResponse($result);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        try {
            $taskData = TaskData::fromArray($request->validated());
            $task = $this->taskService->createTask($taskData, $request->user());

            $this->loadTaskRelationships($task);

            return $this->successResponse(
                'Task created successfully',
                new TaskResource($task),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create task: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified task.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $task = Task::with(['assignedUser', 'tags', 'logs.user'])->findOrFail($id);

            $this->authorize('view', $task);

            return $this->dataResponse(new TaskResource($task));
        } catch (AuthorizationException $e) {
            throw $e; // Re-throw authorization exceptions for framework handling
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve task: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, string $id): JsonResponse
    {
        try {
            $task = Task::with(['assignedUser', 'tags'])->findOrFail($id);

            $this->authorize('update', $task);

            $taskData = TaskData::fromArray($request->validated());
            $task = $this->taskService->updateTask($task, $taskData, $request->version);

            $this->loadTaskRelationships($task);

            return $this->successResponse(
                'Task updated successfully',
                new TaskResource($task)
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update task: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified task (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);

            $this->authorize('delete', $task);

            $task->delete();

            return $this->successResponse('Task deleted successfully');
        } catch (AuthorizationException $e) {
            throw $e; // Re-throw authorization exceptions for framework handling
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete task: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle task status (pending -> in_progress -> completed -> pending).
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        try {
            $task = Task::with(['assignedUser', 'tags'])->findOrFail($id);

            $this->authorize('toggleStatus', $task);

            // If a version is provided, validate optimistic locking
            if ($request->has('version') && (int) $request->get('version') !== $task->version) {
                throw new TaskConflictException(
                    "Task has been modified by another user. Expected version {$request->get('version')}, found $task->version. Please refresh and try again."
                );
            }

            $previousStatus = $task->status;
            $task = $this->taskService->toggleTaskStatus($task);

            $this->loadTaskRelationships($task);

            return $this->successResponse(
                "Task status changed from $previousStatus->value to {$task->status->value}",
                new TaskResource($task)
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (TaskConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to toggle task status: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft deleted task.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $task = Task::withTrashed()->with(['assignedUser', 'tags'])->findOrFail($id);

            $this->authorize('restore', $task);

            $task = $this->taskService->restoreTask($task);

            $this->loadTaskRelationships($task);

            return $this->successResponse(
                'Task restored successfully',
                new TaskResource($task)
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse('Failed to restore task: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk operations on tasks.
     */
    public function bulk(BulkTaskRequest $request): JsonResponse
    {
        try {
            $this->authorize('bulk', Task::class);

            $bulkData = BulkTaskData::fromArray($request->validated());

            $result = $this->taskBulkOperationService->performBulkOperation($bulkData);

            if (config('performance.cache.enabled', true)) {
                $this->clearTaskCaches($request->user(), true);
            }

            return $result;
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse('Failed to perform bulk operation: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Load standard task relationships for API responses
     */
    private function loadTaskRelationships(Task $task): Task
    {
        return $task->load(['assignedUser', 'tags']);
    }

    /**
     * Generate cache key for task index requests
     */
    private function generateTaskIndexCacheKey(FilterTasksRequest $request): string
    {
        $user = $request->user();
        $filters = $this->taskFilterService->getAppliedFilters($request);
        $pagination = [
            'page' => $request->get('page', 1),
            'per_page' => $request->get('per_page', config('performance.api.default_per_page', 15)),
            'paginate' => $request->boolean('paginate', true),
        ];

        $keyData = [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'filters' => $filters,
            'pagination' => $pagination,
        ];

        $prefix = config('performance.cache.prefix', 'rti_perf');

        return $prefix.':task_index:'.md5(serialize($keyData));
    }

    /**
     * Clear task-related caches for the specified user using targeted cache clearing.
     * For bulk operations, this intelligently determines whether to clear individual
     * user caches or all user stats based on the scope of changes.
     */
    private function clearTaskCaches(?User $user = null, bool $isBulkOperation = false): void
    {
        if (! $user) {
            return;
        }

        if ($isBulkOperation) {
            // For bulk operations, clear all user stats cache as multiple users may be affected
            // CacheService handles the decision between tagged clearing vs individual clearing
            $this->cacheService->clearAllUserStats();
        } else {
            // For single operations, clear only the specific user's cache
            $this->cacheService->clearUserStats($user->id);
        }
    }
}
