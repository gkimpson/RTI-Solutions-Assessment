<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\FilterTasksRequest;
use App\Http\Resources\TaskCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskPaginationService
{
    protected TaskFilterService $taskFilterService;

    /**
     * Create a new TaskPaginationService instance.
     *
     * @param  TaskFilterService  $taskFilterService  The filter service for applying search and filter criteria
     */
    public function __construct(TaskFilterService $taskFilterService)
    {
        $this->taskFilterService = $taskFilterService;
    }

    /**
     * Get paginated or non-paginated task collection with metadata
     */
    public function getPaginatedTasks(Builder $query, FilterTasksRequest $request): TaskCollection
    {
        $perPage = $this->getPerPageLimit($request);
        $shouldPaginate = $request->boolean('paginate', true);

        if ($shouldPaginate) {
            return $this->getPaginatedResponse($query, $request, $perPage);
        }

        return $this->getNonPaginatedResponse($query, $request);
    }

    /**
     * Update cached flag in response metadata
     */
    public function setCachedFlag(TaskCollection $collection, bool $cached): TaskCollection
    {
        $additional = $collection->additional;
        $additional['meta']['cached'] = $cached;

        return $collection->additional($additional);
    }

    /**
     * Get the per page limit, capped at maximum
     */
    private function getPerPageLimit(FilterTasksRequest $request): int
    {
        $perPage = (int) $request->get('per_page', config('performance.api.default_per_page', 15));

        return min($perPage, config('performance.api.max_per_page', 100));
    }

    /**
     * Get paginated response with metadata and links
     */
    private function getPaginatedResponse(Builder $query, FilterTasksRequest $request, int $perPage): TaskCollection
    {
        $tasks = $query->paginate($perPage);

        return (new TaskCollection($tasks))->additional([
            'meta' => $this->buildPaginatedMetadata($tasks, $request),
            'links' => $this->buildPaginationLinks($tasks),
        ]);
    }

    /**
     * Get non-paginated response with metadata
     */
    private function getNonPaginatedResponse(Builder $query, FilterTasksRequest $request): TaskCollection
    {
        $tasks = $query->get();

        return (new TaskCollection($tasks))->additional([
            'meta' => $this->buildNonPaginatedMetadata($tasks, $request),
        ]);
    }

    /**
     * Build metadata for paginated responses
     */
    private function buildPaginatedMetadata(LengthAwarePaginator $tasks, FilterTasksRequest $request): array
    {
        return [
            'filters_applied' => $this->taskFilterService->getAppliedFilters($request),
            'total_count' => $tasks->total(),
            'per_page' => $tasks->perPage(),
            'current_page' => $tasks->currentPage(),
            'last_page' => $tasks->lastPage(),
            'from' => $tasks->firstItem(),
            'to' => $tasks->lastItem(),
            'cached' => false,
        ];
    }

    /**
     * Build metadata for non-paginated responses
     */
    private function buildNonPaginatedMetadata(Collection $tasks, FilterTasksRequest $request): array
    {
        return [
            'filters_applied' => $this->taskFilterService->getAppliedFilters($request),
            'total_count' => $tasks->count(),
            'paginated' => false,
            'cached' => false,
        ];
    }

    /**
     * Build pagination links for paginated responses
     */
    private function buildPaginationLinks(LengthAwarePaginator $tasks): array
    {
        return [
            'first' => $tasks->url(1),
            'last' => $tasks->url($tasks->lastPage()),
            'prev' => $tasks->previousPageUrl(),
            'next' => $tasks->nextPageUrl(),
        ];
    }
}
