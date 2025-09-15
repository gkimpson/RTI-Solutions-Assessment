<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BulkAction;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;

class BulkTaskRequest extends FormRequest
{
    /**
     * Maximum number of tasks that can be processed in a single bulk operation
     */
    private const MAX_BULK_TASKS = 100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Parameters:
     * - action (required): The bulk operation to perform. Must be one of: delete, restore, update_status
     * - task_ids (required): Array of task IDs to perform the operation on
     * - status (required if action is update_status): The status to set for tasks
     * - versions (optional): Array of expected versions for optimistic locking.
     *   Can be indexed array [version1, version2, ...] or associative array [taskId => version, ...]
     *   If provided, each task's current version must match the expected version or the operation will fail with a conflict.
     */
    public function rules(): array
    {
        return [
            'action' => 'required|in:'.implode(',', BulkAction::values()),
            'task_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_BULK_TASKS],
            'task_ids.*' => 'required|integer|exists:tasks,id|min:1',
            'status' => 'required_if:action,update_status|in:'.implode(',', TaskStatus::values()),
            'versions' => ['nullable', 'array', 'max:'.self::MAX_BULK_TASKS],
            'versions.*' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Action is required.',
            'action.in' => 'Action must be one of: '.implode(', ', BulkAction::values()).'.',
            'task_ids.required' => 'Task IDs are required.',
            'task_ids.array' => 'Task IDs must be an array.',
            'task_ids.min' => 'At least one task ID is required.',
            'task_ids.max' => 'Cannot process more than '.self::MAX_BULK_TASKS.' tasks in a single operation.',
            'task_ids.*.required' => 'Each task ID is required.',
            'task_ids.*.integer' => 'Each task ID must be an integer.',
            'task_ids.*.min' => 'Each task ID must be a positive integer.',
            'task_ids.*.exists' => 'One or more selected tasks do not exist.',
            'status.required_if' => 'Status is required when action is update_status.',
            'status.in' => 'Status must be one of: '.implode(', ', TaskStatus::values()).'.',
            'versions.array' => 'Versions must be an array.',
            'versions.max' => 'Cannot provide more than '.self::MAX_BULK_TASKS.' versions.',
            'versions.*.integer' => 'Each version must be an integer.',
            'versions.*.min' => 'Each version must be a positive integer.',
        ];
    }
}
