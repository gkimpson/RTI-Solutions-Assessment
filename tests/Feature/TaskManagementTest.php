<?php

use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();
});

// CRUD Operations Tests
describe('Task CRUD Operations', function () {
    test('unauthenticated user cannot access tasks endpoint', function () {
        $this->getJson('/api/v1/tasks')->assertStatus(401);
    });

    test('authenticated user can list their tasks', function () {
        Sanctum::actingAs($this->regularUser);

        $ownTask = Task::factory()->create(['assigned_to' => $this->regularUser->id]);
        Task::factory()->create(['assigned_to' => $this->otherUser->id]);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
                'links',
                'timestamp',
            ]);

        // Should only see own tasks
        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.id'))->toBe($ownTask->id);
    });

    test('admin can see all tasks', function () {
        Sanctum::actingAs($this->adminUser);

        Task::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(3);
    });

    test('user can create task for themselves', function () {
        Sanctum::actingAs($this->regularUser);

        $taskData = [
            'title' => 'Test Task Creation',
            'description' => 'This is a test task',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDay()->format('Y-m-d'),
            'metadata' => ['category' => 'testing'],
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'title', 'description', 'status', 'priority', 'assigned_to', 'version'],
            ]);

        expect($response->json('data.assigned_to'))->toBe($this->regularUser->id)
            ->and($response->json('data.version'))->toBe(1)
            ->and(TaskLog::where('task_id', $response->json('data.id'))->count())->toBe(1);

        // Verify audit log was created
    });

    test('admin can create task for other users', function () {
        Sanctum::actingAs($this->adminUser);

        $taskData = [
            'title' => 'Admin Assigned Task',
            'description' => 'Task assigned by admin',
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => $this->regularUser->id,
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(201);
        expect($response->json('data.assigned_to'))->toBe($this->regularUser->id);
    });

    test('user can update their own task', function () {
        Sanctum::actingAs($this->regularUser);

        $task = Task::factory()->create(['assigned_to' => $this->regularUser->id]);

        $updateData = [
            'title' => 'Updated Task Title',
            'priority' => 'high',
            'version' => $task->version,
        ];

        $response = $this->putJson("/api/v1/tasks/$task->id", $updateData);

        $response->assertStatus(200);
        expect($response->json('data.title'))->toBe('Updated Task Title')
            ->and($response->json('data.priority'))->toBe('high')
            ->and($response->json('data.version'))->toBe($task->version + 1);
    });

    test('user cannot update other users task', function () {
        Sanctum::actingAs($this->regularUser);

        $task = Task::factory()->create(['assigned_to' => $this->otherUser->id]);

        $response = $this->putJson("/api/v1/tasks/$task->id", [
            'title' => 'Should not work',
            'version' => $task->version,
        ]);

        $response->assertStatus(403);
    });

    test('user can soft delete their own task', function () {
        Sanctum::actingAs($this->regularUser);

        $task = Task::factory()->create(['assigned_to' => $this->regularUser->id]);

        $response = $this->deleteJson("/api/v1/tasks/$task->id");

        $response->assertStatus(200);
        expect(Task::find($task->id))->toBeNull()
            ->and(Task::withTrashed()->find($task->id))->not->toBeNull(); // Soft deleted
    });
});

// Validation Tests
describe('Task Validation', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->regularUser);
    });

    test('title is required and has minimum length', function () {
        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Hi', // Too short
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    });

    test('status must be valid enum value', function () {
        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Valid Title Here',
            'status' => 'invalid_status',
            'priority' => 'medium',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    test('metadata validation prevents malicious data', function () {
        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Valid Title Here',
            'status' => 'pending',
            'priority' => 'medium',
            'metadata' => [
                'deeply' => [
                    'nested' => [
                        'structure' => [
                            'too_deep' => 'should fail',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['metadata']);
    });

    test('metadata validation allows valid structure', function () {
        $validMetadata = [
            'category' => 'work',
            'priority_notes' => 'Handle with care',
            'tags' => ['urgent', 'client'],
            'estimated_hours' => 5,
        ];

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Valid Title Here',
            'status' => 'pending',
            'priority' => 'medium',
            'metadata' => $validMetadata,
        ]);

        $response->assertStatus(201);
        expect($response->json('data.metadata'))->toBe($validMetadata);
    });

    test('version is required for updates', function () {
        $task = Task::factory()->create(['assigned_to' => $this->regularUser->id]);

        $response = $this->putJson("/api/v1/tasks/$task->id", [
            'title' => 'Updated Title',
            // Missing version
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['version']);
    });
});

// Optimistic Locking Tests
describe('Optimistic Locking', function () {

    test('toggle status handles version conflicts', function () {
        Sanctum::actingAs($this->regularUser);

        // Create a task directly to avoid any factory side effects
        $task = Task::create([
            'title' => 'Test Task for Version Conflict',
            'description' => 'Testing optimistic locking',
            'status' => 'pending',
            'priority' => 'medium',
            'user_id' => $this->regularUser->id,
            'assigned_to' => $this->regularUser->id,
            'version' => 1,
        ]);

        // Simulate concurrent modification by updating version directly in database
        // This simulates another user modifying the task
        Task::where('id', $task->id)->update(['version' => 2]);

        $response = $this->patchJson("/api/v1/tasks/$task->id/toggle-status", [
            'version' => 1, // We still think the version is 1
        ]);

        $response->assertStatus(409);
    });
});

// Bulk Operations Tests
describe('Bulk Operations', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->adminUser);
    });

    test('bulk delete with valid task ids', function () {
        $tasks = Task::factory()->count(3)->create();
        $taskIds = $tasks->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'delete',
            'task_ids' => $taskIds,
        ]);

        $response->assertStatus(200);
        expect($response->json('processed_count'))->toBe(3)
            ->and($response->json('total_count'))->toBe(3);

        // Verify tasks are soft deleted
        foreach ($taskIds as $taskId) {
            expect(Task::find($taskId))->toBeNull()
                ->and(Task::withTrashed()->find($taskId))->not->toBeNull();
        }
    });

    test('bulk restore with deleted tasks', function () {
        $tasks = Task::factory()->count(2)->create();
        $tasks->each->delete(); // Soft delete
        $taskIds = $tasks->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'restore',
            'task_ids' => $taskIds,
        ]);

        $response->assertStatus(200);
        expect($response->json('processed_count'))->toBe(2);

        // Verify tasks are restored
        foreach ($taskIds as $taskId) {
            expect(Task::find($taskId))->not->toBeNull();
        }
    });

    test('bulk status update changes task statuses', function () {
        $tasks = Task::factory()->count(3)->create(['status' => 'pending']);
        $taskIds = $tasks->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'update_status',
            'task_ids' => $taskIds,
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        expect($response->json('processed_count'))->toBe(3);

        // Verify status changes
        foreach ($taskIds as $taskId) {
            expect(Task::find($taskId)->status->value)->toBe('in_progress');
        }
    });

    test('bulk operations handle version conflicts gracefully', function () {
        $task1 = Task::factory()->create();
        $task2 = Task::factory()->create();

        // Store original versions
        $originalVersion1 = $task1->version;

        // Simulate concurrent modification on task1 by another user
        // This updates both the status and version, creating a real conflict scenario
        Task::where('id', $task1->id)->update([
            'status' => 'completed',
            'version' => $originalVersion1 + 1,
        ]);

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'delete',
            'task_ids' => [$task1->id, $task2->id],
            // Provide the expected versions - task1 should have version 1 but actually has version 2
            'versions' => [$task1->id => $originalVersion1, $task2->id => $task2->version],
        ]);

        $response->assertStatus(200);
        expect($response->json('processed_count'))->toBe(1)
            ->and($response->json('conflict_count'))->toBe(1); // Only task2 succeeded
    });
});

// Authorization Tests
describe('Authorization', function () {
    test('regular user cannot access admin functions', function () {
        Sanctum::actingAs($this->regularUser);

        $adminTask = Task::factory()->create(['assigned_to' => $this->adminUser->id]);

        // Cannot view other's tasks
        $this->getJson("/api/v1/tasks/$adminTask->id")->assertStatus(403);

        // Cannot update other's tasks
        $this->putJson("/api/v1/tasks/$adminTask->id", [
            'title' => 'Unauthorized update',
            'version' => $adminTask->version,
        ])->assertStatus(403);

        // Cannot delete other's tasks
        $this->deleteJson("/api/v1/tasks/$adminTask->id")->assertStatus(403);
    });

    test('task creator maintains access even when not assigned', function () {
        Sanctum::actingAs($this->adminUser);

        $task = Task::factory()->create([
            'user_id' => $this->adminUser->id,
            'assigned_to' => $this->regularUser->id,
        ]);

        // Admin (creator) can still access task assigned to others
        $this->getJson("/api/v1/tasks/$task->id")->assertStatus(200);
        $this->putJson("/api/v1/tasks/$task->id", [
            'title' => 'Creator can update',
            'version' => $task->version,
        ])->assertStatus(200);
    });
});

// Audit Logging Tests
describe('Audit Logging', function () {
    test('task creation is logged', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Logged Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $taskId = $response->json('data.id');

        $log = TaskLog::where('task_id', $taskId)->first();
        expect($log)->not->toBeNull()
            ->and($log->operation_type->value)->toBe('create')
            ->and($log->user_id)->toBe($this->regularUser->id);
    });

    test('task updates are logged with changes', function () {
        Sanctum::actingAs($this->regularUser);

        // Create task through API to ensure audit log is created
        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Initial Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $taskId = $response->json('data.id');
        $task = Task::find($taskId);

        $this->putJson("/api/v1/tasks/$taskId", [
            'title' => 'Updated Title',
            'priority' => 'high',
            'version' => $task->version,
        ]);

        $logs = TaskLog::where('task_id', $taskId)->get();
        expect($logs)->toHaveCount(2);

        $updateLog = $logs->where('operation_type', 'update')->first();
        expect($updateLog['changes'])->toHaveKey('title')
            ->and($updateLog['changes'])->toHaveKey('priority');
    });

    test('status toggle is logged with transition details', function () {
        Sanctum::actingAs($this->regularUser);

        $task = Task::factory()->create([
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/v1/tasks/$task->id/toggle-status");
        $response->assertStatus(200);

        $log = TaskLog::where('task_id', $task->id)
            ->where('operation_type', 'toggle_status')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log['changes']['status']['from'])->toBe('pending')
            ->and($log['changes']['status']['to'])->toBe('in_progress');
    });
});
