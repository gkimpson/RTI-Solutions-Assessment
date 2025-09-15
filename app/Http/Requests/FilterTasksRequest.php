<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class FilterTasksRequest extends FormRequest
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
            // Basic filters
            'status' => ['nullable', 'in:'.implode(',', Task::getStatuses())],
            'priority' => ['nullable', 'in:'.implode(',', Task::getPriorities())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'tag' => ['nullable', 'string'],

            // Date range filters
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date', 'after_or_equal:due_date_from'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'updated_from' => ['nullable', 'date'],
            'updated_to' => ['nullable', 'date', 'after_or_equal:updated_from'],

            // Boolean filters
            'overdue' => ['nullable', 'boolean'],
            'with_trashed' => ['nullable', 'boolean'],

            // Search with length limits and sanitization
            'search' => ['nullable', 'string', 'min:2', 'max:255', 'regex:/^[a-zA-Z0-9\s\-_.,!?]+$/'],

            // Sorting
            'sort_by' => ['nullable', 'in:created_at,updated_at,due_date,priority,status,title,id'],
            'sort_direction' => ['nullable', 'in:asc,desc'],

            // Pagination
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('performance.api.max_per_page', 100)],
            'paginate' => ['nullable', 'boolean'],

            // Advanced filters
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['in:'.implode(',', Task::getStatuses())],
            'priorities' => ['nullable', 'array'],
            'priorities.*' => ['in:'.implode(',', Task::getPriorities())],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'tag_operator' => ['nullable', 'string', 'in:and,or'],
            'assigned_users' => ['nullable', 'array'],
            'assigned_users.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: '.implode(', ', Task::getStatuses()),
            'priority.in' => 'Priority must be one of: '.implode(', ', Task::getPriorities()),
            'assigned_to.exists' => 'Selected user does not exist.',
            'due_date_to.after_or_equal' => 'To date must be after or equal to from date.',
            'created_to.after_or_equal' => 'To date must be after or equal to from date.',
            'updated_to.after_or_equal' => 'To date must be after or equal to from date.',
            'per_page.max' => 'Items per page cannot exceed '.config('performance.api.max_per_page', 100).'.',
            'statuses.*.in' => 'Each status must be one of: '.implode(', ', Task::getStatuses()),
            'priorities.*.in' => 'Each priority must be one of: '.implode(', ', Task::getPriorities()),
            'assigned_users.*.exists' => 'One or more selected users do not exist.',
            'sort_by.in' => 'Sort by field must be one of: created_at, updated_at, due_date, priority, status, title, id',
            'sort_direction.in' => 'Sort direction must be either asc or desc',
            'search.min' => 'Search term must be at least 2 characters.',
            'search.max' => 'Search term cannot exceed 255 characters.',
            'search.regex' => 'Search term contains invalid characters. Only letters, numbers, spaces, and basic punctuation allowed.',
            'tags.*.max' => 'Tag names cannot exceed 50 characters.',
            'tag_operator.in' => 'Tag operator must be either "and" or "or".',
        ];
    }

}
