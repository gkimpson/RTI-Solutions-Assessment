<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkTaskData;
use App\Http\Resources\BulkOperationResource;
use Illuminate\Http\JsonResponse;

class TaskBulkOperationService
{
    protected TaskService $taskService;

    /**
     * Create a new TaskBulkOperationService instance.
     *
     * @param  TaskService  $taskService  The task service for handling individual task operations
     */
    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Perform bulk operations on tasks.
     *
     * Handles bulk delete, restore, and status update operations on multiple tasks.
     * Returns a standardized response with operation results including success
     * counts, conflict counts, and any error details.
     *
     * @param  BulkTaskData  $bulkData  The bulk operation data containing task IDs, action, and parameters
     * @return JsonResponse A standardized JSON response with operation results
     */
    public function performBulkOperation(BulkTaskData $bulkData): JsonResponse
    {
        $result = $this->taskService->bulkOperation($bulkData);

        // Add the action to the result so it can be included in the resource
        $result['action'] = $bulkData->action;

        // Ensure conflicted_tasks is always present
        if (! isset($result['conflicted_tasks'])) {
            $result['conflicted_tasks'] = [];
        }

        // Always return a successful response, even with conflicts
        // The client can check the processed/conflicts counts to determine success
        return response()->json(new BulkOperationResource((object) $result));
    }
}
