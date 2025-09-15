<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskFilterService
{
    /**
     * Apply filters to the task query
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function applyFilters(Builder $query, Request $request): Builder
    {
        // Non-admin users can only see their assigned tasks
        if (! $request->user()->isAdmin()) {
            $query->where('assigned_to', $request->user()->id);
        }

        // Filter by single status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by multiple statuses
        if ($request->has('statuses')) {
            $query->whereIn('status', $request->statuses);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by multiple priorities
        if ($request->has('priorities')) {
            $query->whereIn('priority', $request->priorities);
        }

        // Filter by assigned user (only admins can filter by other users)
        if ($request->has('assigned_to')) {
            if ($request->user()->isAdmin()) {
                $query->where('assigned_to', $request->assigned_to);
            } else {
                // Non-admin users can only filter by themselves
                $query->where('assigned_to', $request->user()->id);
            }
        }

        // Filter by multiple assigned users (admins only)
        if ($request->has('assigned_users')) {
            if ($request->user()->isAdmin()) {
                $query->whereIn('assigned_to', $request->assigned_users);
            } else {
                // Non-admin users can only filter by themselves
                $query->where('assigned_to', $request->user()->id);
            }
        }

        // Filter by single tag
        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request): void {
                $q->where('name', $request->tag);
            });
        }

        // Filter by multiple tags
        if ($request->has('tags') && is_array($request->tags) && ! empty($request->tags)) {
            // Support both AND and OR logic for multiple tags
            // Use 'tag_operator' parameter: 'and' (default) or 'or'
            $tagOperator = $request->get('tag_operator', 'and');

            if ($tagOperator === 'or') {
                // OR logic - task must have ANY of the specified tags (optimized)
                $query->whereExists(function ($q) use ($request): void {
                    $q->select(DB::raw(1))
                        ->from('task_tag')
                        ->join('tags', 'tags.id', '=', 'task_tag.tag_id')
                        ->whereColumn('task_tag.task_id', 'tasks.id')
                        ->whereIn('tags.name', $request->tags);
                });
            } else {
                // AND logic - task must have ALL specified tags (optimized)
                $tagCount = count($request->tags);
                $query->whereExists(function ($q) use ($request, $tagCount): void {
                    $q->select(DB::raw('COUNT(DISTINCT tags.id)'))
                        ->from('task_tag')
                        ->join('tags', 'tags.id', '=', 'task_tag.tag_id')
                        ->whereColumn('task_tag.task_id', 'tasks.id')
                        ->whereIn('tags.name', $request->tags)
                        ->havingRaw('COUNT(DISTINCT tags.id) = ?', [$tagCount]);
                });
            }
        }

        // Filter by due date range
        if ($request->has('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }

        if ($request->has('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        // Filter by created date range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // Filter by updated date range
        if ($request->has('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->updated_from);
        }

        if ($request->has('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->updated_to);
        }

        // Filter overdue tasks
        if ($request->boolean('overdue')) {
            $query->where('due_date', '<', now()->toDateString())
                ->whereIn('status', ['pending', 'in_progress']);
        }

        // Search in title and description with fulltext optimization
        if ($request->has('search')) {
            $search = InputSanitizationService::sanitizeSearch($request->search);

            // Skip empty searches after sanitization
            if (empty($search)) {
                return $query;
            }

            // Use fulltext search for MySQL, fallback to LIKE for other databases
            if (config('database.default') === 'mysql' && strlen($search) >= 3) {
                // MySQL fulltext search with relevance scoring
                $query->whereRaw('MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE)', [$search])
                    ->selectRaw('tasks.*, MATCH(title, description) AGAINST(?) as relevance_score', [$search])
                    ->orderByDesc('relevance_score');
            } else {
                // Fallback to optimized LIKE search with proper escaping
                $escapedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
                $query->where(function ($q) use ($escapedSearch): void {
                    $q->where('title', 'LIKE', "%{$escapedSearch}%")
                        ->orWhere('description', 'LIKE', "%{$escapedSearch}%");
                });
            }
        }

        // Include soft deleted tasks if requested (admins only)
        if ($request->boolean('with_trashed') && $request->user()->isAdmin()) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Apply sorting to the task query
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function applySorting(Builder $query, Request $request): Builder
    {
        // Order by with sanitization
        $sortBy = InputSanitizationService::sanitizeOrderBy($request->get('sort_by', 'created_at'));
        $sortDirection = in_array(strtolower($request->get('sort_direction', 'desc')), ['asc', 'desc'])
            ? $request->get('sort_direction', 'desc')
            : 'desc';

        // Whitelist allowed sort fields for security
        $allowedSortFields = ['created_at', 'updated_at', 'due_date', 'priority', 'status', 'title', 'id'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Support for multiple sort fields (comma-separated) with security
        if ($request->has('sort_by') && str_contains($request->sort_by, ',')) {
            $sortFields = array_slice(explode(',', $request->sort_by), 0, 3); // Limit to 3 fields max
            $sortDirections = explode(',', $request->sort_direction ?? 'desc');

            foreach ($sortFields as $index => $field) {
                $field = InputSanitizationService::sanitizeOrderBy(trim($field));
                $direction = isset($sortDirections[$index]) ? trim($sortDirections[$index]) : 'desc';
                $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';

                if (in_array($field, $allowedSortFields)) {
                    $query->orderBy($field, $direction);
                }
            }
        }

        return $query;
    }

    /**
     * Get list of filters applied to the request
     */
    public function getAppliedFilters(Request $request): array
    {
        $filters = [];

        $filterKeys = [
            'status', 'priority', 'assigned_to', 'tag', 'tags', 'tag_operator', 'search',
            'due_date_from', 'due_date_to', 'created_from', 'created_to',
            'updated_from', 'updated_to', 'overdue', 'with_trashed',
        ];

        foreach ($filterKeys as $key) {
            if ($request->has($key) && $request->get($key) !== null) {
                $filters[$key] = $request->get($key);
            }
        }

        return $filters;
    }

    /**
     * Get optimized query with eager loading based on applied filters
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function optimizeQueryLoading(Builder $query, Request $request): Builder
    {
        $eagerLoad = ['assignedUser'];

        // Only load tags if we're filtering by them or likely to need them
        if ($request->has(['tag', 'tags']) ||
            ! $request->has(['status', 'priority', 'assigned_to', 'search'])) {
            $eagerLoad[] = 'tags';
        }

        // Add logs only if it's an admin user and not a bulk operation
        if ($request->user()->isAdmin() &&
            $request->get('per_page', 15) <= 20) {
            $eagerLoad[] = 'logs.user';
        }

        return $query->with($eagerLoad);
    }

    /**
     * Check if the query should use aggressive caching
     */
    public function shouldUseAggressiveCaching(Request $request): bool
    {
        // Use aggressive caching for:
        // 1. Non-search queries (search results change frequently)
        // 2. Queries with basic filters only
        // 3. Not real-time data (no recent date filters)

        $hasSearch = $request->has('search') && ! empty(trim($request->search));
        $hasRecentDateFilter = $request->has(['updated_from', 'created_from']) &&
            ($request->updated_from >= now()->subHours(1)->toDateString() ||
             $request->created_from >= now()->subHours(1)->toDateString());

        return ! $hasSearch && ! $hasRecentDateFilter;
    }
}
