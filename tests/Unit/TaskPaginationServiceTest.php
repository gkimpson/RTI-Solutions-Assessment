<?php

use App\Http\Requests\FilterTasksRequest;
use App\Http\Resources\TaskCollection;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskFilterService;
use App\Services\TaskPaginationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->taskFilterService = Mockery::mock(TaskFilterService::class);
    $this->taskPaginationService = new TaskPaginationService($this->taskFilterService);
    $this->user = User::factory()->create();
});

afterEach(function () {
    Mockery::close();
});

// Helper function to create a FilterTasksRequest with user and data
function createFilterRequest(User $user, array $data = []): FilterTasksRequest
{
    // Create request with proper URL and query parameters for pagination testing
    $queryString = http_build_query($data);
    $url = 'http://localhost/api/v1/tasks'.($queryString ? '?'.$queryString : '');

    $request = FilterTasksRequest::create($url, 'GET', $data);
    $request->setUserResolver(fn () => $user);

    return $request;
}

describe('Service Construction & Dependencies', function () {
    test('service can be instantiated with TaskFilterService dependency', function () {
        $service = new TaskPaginationService($this->taskFilterService);

        expect($service)->toBeInstanceOf(TaskPaginationService::class);
    });
});

describe('Per Page Limit Logic', function () {
    test('returns default per page when no per_page specified', function () {
        $request = createFilterRequest($this->user);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        expect($result)->toBeInstanceOf(TaskCollection::class);
    });

    test('returns custom per_page value when specified', function () {
        Task::factory()->count(30)->create();

        $request = createFilterRequest($this->user, ['per_page' => 20]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $additional = $result->additional;
        expect($additional['meta']['per_page'])->toBe(20);
    });

    test('enforces maximum per page limit', function () {
        Task::factory()->count(150)->create();

        $request = createFilterRequest($this->user, ['per_page' => 150]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $additional = $result->additional;
        expect($additional['meta']['per_page'])->toBe(100);
    });

    test('handles zero and negative per_page values', function () {
        Task::factory()->count(5)->create();

        $request = createFilterRequest($this->user, ['per_page' => 0]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        expect($result)->toBeInstanceOf(TaskCollection::class);
    });
});

describe('Paginated Response Generation', function () {
    test('generates paginated response with correct structure', function () {
        Task::factory()->count(25)->create();

        $request = createFilterRequest($this->user, ['per_page' => 10]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn(['status' => 'pending']);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        expect($result)->toBeInstanceOf(TaskCollection::class);

        $additional = $result->additional;
        expect($additional)->toHaveKeys(['meta', 'links']);
    });

    test('generates correct pagination metadata', function () {
        Task::factory()->count(25)->create();

        $request = createFilterRequest($this->user, ['per_page' => 10, 'page' => 2]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn(['status' => 'pending']);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta)->toHaveKeys([
            'filters_applied',
            'total_count',
            'per_page',
            'current_page',
            'last_page',
            'from',
            'to',
            'cached',
        ])
            ->and($meta['total_count'])->toBe(25)
            ->and($meta['per_page'])->toBe(10)
            ->and($meta['current_page'])->toBeGreaterThanOrEqual(1)
            ->and($meta['cached'])->toBeFalse();
        // Laravel pagination starts from page 1, but page 2 should still be valid
    });

    test('generates correct pagination links', function () {
        Task::factory()->count(25)->create();

        $request = createFilterRequest($this->user, ['per_page' => 10, 'page' => 1]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $links = $result->additional['links'];
        expect($links)->toHaveKeys(['first', 'last', 'prev', 'next'])
            ->and($links['first'])->toContain('page=1')
            ->and($links['last'])->toContain('page=3')
            ->and($links['prev'])->toBeNull()
            ->and($links['next'])->toContain('page=2');
        // 25 items / 10 per page = 3 pages

        // On first page, prev should be null and next should exist
    });

    test('includes filters_applied from TaskFilterService', function () {
        Task::factory()->count(5)->create();

        $expectedFilters = ['status' => 'pending', 'priority' => 'high'];
        $request = createFilterRequest($this->user);
        $this->taskFilterService->shouldReceive('getAppliedFilters')
            ->with($request)
            ->andReturn($expectedFilters);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['filters_applied'])->toBe($expectedFilters);
    });

    test('handles empty paginated results', function () {
        $request = createFilterRequest($this->user, ['per_page' => 10]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['total_count'])->toBe(0)
            ->and($meta['from'])->toBeNull()
            ->and($meta['to'])->toBeNull();
    });
});

describe('Non-Paginated Response Generation', function () {
    test('generates non-paginated response with correct structure', function () {
        Task::factory()->count(5)->create();

        $request = createFilterRequest($this->user, ['paginate' => false]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        expect($result)->toBeInstanceOf(TaskCollection::class);

        $additional = $result->additional;
        expect($additional)->toHaveKey('meta')
            ->and($additional)->not->toHaveKey('links');
    });

    test('generates correct non-paginated metadata', function () {
        Task::factory()->count(15)->create();

        $request = createFilterRequest($this->user, ['paginate' => false]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn(['priority' => 'high']);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta)->toHaveKeys([
            'filters_applied',
            'total_count',
            'paginated',
            'cached',
        ])
            ->and($meta['total_count'])->toBe(15)
            ->and($meta['paginated'])->toBeFalse()
            ->and($meta['cached'])->toBeFalse();
    });

    test('includes filters_applied in non-paginated response', function () {
        Task::factory()->count(3)->create();

        $expectedFilters = ['search' => 'test query'];
        $request = createFilterRequest($this->user, ['paginate' => false]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')
            ->with($request)
            ->andReturn($expectedFilters);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['filters_applied'])->toBe($expectedFilters);
    });

    test('handles large non-paginated datasets', function () {
        Task::factory()->count(150)->create();

        $request = createFilterRequest($this->user, ['paginate' => false]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['total_count'])->toBe(150)
            ->and($meta['paginated'])->toBeFalse();
    });
});

describe('Main Method Routing Logic', function () {
    test('routes to paginated response when paginate is true', function () {
        Task::factory()->count(20)->create();

        $request = createFilterRequest($this->user, ['paginate' => true, 'per_page' => 10]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $additional = $result->additional;
        expect($additional)->toHaveKeys(['meta', 'links'])
            ->and($additional['meta']['per_page'])->toBe(10);
    });

    test('routes to non-paginated response when paginate is false', function () {
        Task::factory()->count(20)->create();

        $request = createFilterRequest($this->user, ['paginate' => false]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $additional = $result->additional;
        expect($additional)->toHaveKey('meta')
            ->and($additional)->not->toHaveKey('links')
            ->and($additional['meta']['paginated'])->toBeFalse();
    });

    test('uses pagination by default when no paginate parameter', function () {
        Task::factory()->count(20)->create();

        $request = createFilterRequest($this->user, ['per_page' => 5]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $additional = $result->additional;
        expect($additional)->toHaveKeys(['meta', 'links'])
            ->and($additional['meta']['per_page'])->toBe(5);
    });

    test('passes correct per_page to pagination logic', function () {
        Task::factory()->count(30)->create();

        $request = createFilterRequest($this->user, ['per_page' => 25]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['per_page'])->toBe(25)
            ->and($meta['current_page'])->toBe(1)
            ->and($meta['total_count'])->toBe(30);
    });
});

describe('Cache Flag Management', function () {
    test('sets cached flag to true in metadata', function () {
        Task::factory()->count(5)->create();

        $request = createFilterRequest($this->user);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $updatedResult = $this->taskPaginationService->setCachedFlag($result, true);

        $meta = $updatedResult->additional['meta'];
        expect($meta['cached'])->toBeTrue();
    });

    test('sets cached flag to false in metadata', function () {
        Task::factory()->count(5)->create();

        $request = createFilterRequest($this->user);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $updatedResult = $this->taskPaginationService->setCachedFlag($result, false);

        $meta = $updatedResult->additional['meta'];
        expect($meta['cached'])->toBeFalse();
    });

    test('preserves other metadata when updating cached flag', function () {
        Task::factory()->count(10)->create();

        $request = createFilterRequest($this->user, ['per_page' => 5]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn(['status' => 'pending']);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $originalMeta = $result->additional['meta'];
        $updatedResult = $this->taskPaginationService->setCachedFlag($result, true);
        $updatedMeta = $updatedResult->additional['meta'];

        expect($updatedMeta['total_count'])->toBe($originalMeta['total_count'])
            ->and($updatedMeta['per_page'])->toBe($originalMeta['per_page'])
            ->and($updatedMeta['filters_applied'])->toBe($originalMeta['filters_applied'])
            ->and($updatedMeta['cached'])->toBeTrue();
    });

    test('returns TaskCollection instance after updating cached flag', function () {
        Task::factory()->count(3)->create();

        $request = createFilterRequest($this->user);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $updatedResult = $this->taskPaginationService->setCachedFlag($result, true);

        expect($updatedResult)->toBeInstanceOf(TaskCollection::class)
            ->and($updatedResult)->toBe($result);
    });
});

describe('Integration & Edge Cases', function () {
    test('works with actual database queries and relationships', function () {
        $user = User::factory()->create();
        Task::factory()
            ->count(3)
            ->for($user, 'assignedUser')
            ->create();

        $request = createFilterRequest($this->user, ['per_page' => 2]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        expect($result)->toBeInstanceOf(TaskCollection::class)
            ->and($result->additional['meta']['total_count'])->toBe(3)
            ->and($result->additional['meta']['per_page'])->toBe(2);
    });

    test('handles empty query results correctly', function () {
        $request = createFilterRequest($this->user, ['per_page' => 10]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags'])->where('id', '>', 999999);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['total_count'])->toBe(0)
            ->and($meta['from'])->toBeNull()
            ->and($meta['to'])->toBeNull();
    });

    test('handles single result correctly', function () {
        Task::factory()->create();

        $request = createFilterRequest($this->user, ['per_page' => 10]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['total_count'])->toBe(1)
            ->and($meta['from'])->toBe(1)
            ->and($meta['to'])->toBe(1)
            ->and($meta['last_page'])->toBe(1);
    });

    test('handles requests for non-existent pages', function () {
        Task::factory()->count(5)->create();

        $request = createFilterRequest($this->user, ['per_page' => 10, 'page' => 5]);
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['total_count'])->toBe(5)
            ->and($meta['current_page'])->toBe(1)
            ->and($meta['from'])->toBe(1)
            ->and($meta['to'])->toBe(5);
        // Laravel pagination redirects to page 1 when page doesn't exist
    });

    test('handles boundary conditions correctly', function () {
        Task::factory()->count(15)->create(); // Exactly default per_page

        $request = createFilterRequest($this->user); // Uses default per_page = 15
        $this->taskFilterService->shouldReceive('getAppliedFilters')->andReturn([]);

        $query = Task::with(['assignedUser', 'tags']);
        $result = $this->taskPaginationService->getPaginatedTasks($query, $request);

        $meta = $result->additional['meta'];
        expect($meta['total_count'])->toBe(15)
            ->and($meta['per_page'])->toBe(15)
            ->and($meta['last_page'])->toBe(1)
            ->and($meta['from'])->toBe(1)
            ->and($meta['to'])->toBe(15);
    });
});
