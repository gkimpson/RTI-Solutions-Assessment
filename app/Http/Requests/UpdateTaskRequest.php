<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Rules\ValidDueDate;
use App\Rules\ValidMetadata;
use App\Rules\ValidTaskAssignment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:5', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:'.implode(',', TaskStatus::values())],
            'priority' => ['sometimes', 'required', 'in:'.implode(',', TaskPriority::values())],
            'due_date' => ['nullable', 'date', new ValidDueDate],
            'assigned_to' => ['nullable', 'exists:users,id', new ValidTaskAssignment],
            'metadata' => ['nullable', new ValidMetadata],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
            'version' => ['required', 'integer', 'min:1'], // For optimistic locking
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required.',
            'title.min' => 'Task title must be at least 5 characters.',
            'status.in' => 'Status must be one of: '.implode(', ', TaskStatus::values()),
            'priority.in' => 'Priority must be one of: '.implode(', ', TaskPriority::values()),
            'due_date.date' => 'Due date must be a valid date.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'tag_ids.*.exists' => 'One or more selected tags do not exist.',
            'version.required' => 'Version is required for optimistic locking.',
            'version.integer' => 'Version must be an integer.',
        ];
    }
}
