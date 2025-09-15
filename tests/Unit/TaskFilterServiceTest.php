<?php

use App\Models\Task;
use App\Models\User;
use App\Services\TaskFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->taskFilterService = new TaskFilterService;
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->adminUser = User::factory()->admin()->create();
    $this->regularUser = User::factory()->regularUser()->create();
});

// Helper function to create a mock request with a user
function createRequestWithUser(User $user, array $data = []): Request
{
    $request = new Request($data);
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('it can filter tasks by status', function () {
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'status' => 'completed']);
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'status' => 'pending']);

    $request = createRequestWithUser($this->regularUser, ['status' => 'completed']);
    $filteredTasks = $this->taskFilterService->applyFilters(Task::query(), $request)->get();

    expect($filteredTasks)->toHaveCount(1)
        ->and($filteredTasks->first()->status->value)->toBe('completed');
});

test('it can filter tasks by priority', function () {
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'priority' => 'high']);
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'priority' => 'medium']);

    $request = createRequestWithUser($this->regularUser, ['priority' => 'high']);
    $filteredTasks = $this->taskFilterService->applyFilters(Task::query(), $request)->get();

    expect($filteredTasks)->toHaveCount(1)
        ->and($filteredTasks->first()->priority->value)->toBe('high');
});

test('it can filter tasks by due date range', function () {
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'due_date' => now()->subDays(2)]);
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'due_date' => now()->addDays()]);
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id, 'due_date' => now()->addDays(5)]);

    $request = createRequestWithUser($this->regularUser, [
        'due_date_from' => now()->format('Y-m-d'),
        'due_date_to' => now()->addDays(2)->format('Y-m-d'),
    ]);
    $filteredTasks = $this->taskFilterService->applyFilters(Task::query(), $request)->get();

    expect($filteredTasks)->toHaveCount(1)
        ->and($filteredTasks->first()->due_date->isSameDay(now()->addDays()))->toBeTrue();
});

test('it can filter tasks by assigned to', function () {
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id]);
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->otherUser->id]);

    $request = createRequestWithUser($this->regularUser, ['assigned_to' => $this->regularUser->id]);
    $filteredTasks = $this->taskFilterService->applyFilters(Task::query(), $request)->get();

    expect($filteredTasks)->toHaveCount(1)
        ->and($filteredTasks->first()->assigned_to)->toBe($this->regularUser->id);
});

test('it can filter tasks by owned by', function () {
    Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id]);
    Task::factory()->create(['user_id' => $this->otherUser->id, 'assigned_to' => $this->otherUser->id]);

    $request = createRequestWithUser($this->regularUser, ['owned_by' => $this->regularUser->id]);
    $filteredTasks = $this->taskFilterService->applyFilters(Task::query(), $request)->get();

    expect($filteredTasks)->toHaveCount(1)
        ->and($filteredTasks->first()->user_id)->toBe($this->regularUser->id);
});
