<?php

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Policies\TagPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create users with different roles
    $this->adminUser = User::factory()->admin()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();

    // Create test resources
    $this->task = Task::factory()->create([
        'user_id' => $this->adminUser->id,
        'assigned_to' => $this->regularUser->id,
    ]);

    $this->tag = Tag::factory()->create(['name' => 'Test Tag']);

    // Initialize policies for unit testing
    $this->userPolicy = new UserPolicy;
    $this->taskPolicy = new TaskPolicy;
    $this->tagPolicy = new TagPolicy;
});

// USER POLICY UNIT TESTS
describe('User Policy', function () {
    test('admin user passes all user policy checks', function () {
        expect($this->userPolicy->viewAny($this->adminUser))->toBeTrue()
            ->and($this->userPolicy->view($this->adminUser, $this->regularUser))->toBeTrue()
            ->and($this->userPolicy->create($this->adminUser))->toBeTrue()
            ->and($this->userPolicy->update($this->adminUser, $this->regularUser))->toBeTrue()
            ->and($this->userPolicy->delete($this->adminUser, $this->regularUser))->toBeTrue()
            ->and($this->userPolicy->manageRole($this->adminUser, $this->regularUser))->toBeTrue()
            ->and($this->userPolicy->restore($this->adminUser))->toBeTrue()
            ->and($this->userPolicy->forceDelete($this->adminUser, $this->regularUser))->toBeTrue();
    });

    test('regular user can only view and update their own profile', function () {
        // Can view their own profile
        expect($this->userPolicy->view($this->regularUser, $this->regularUser))->toBeTrue()
            ->and($this->userPolicy->update($this->regularUser, $this->regularUser))->toBeTrue();

        // Cannot view or update other users
        expect($this->userPolicy->view($this->regularUser, $this->otherUser))->toBeFalse()
            ->and($this->userPolicy->update($this->regularUser, $this->otherUser))->toBeFalse()
            ->and($this->userPolicy->viewAny($this->regularUser))->toBeFalse()
            ->and($this->userPolicy->create($this->regularUser))->toBeFalse()
            ->and($this->userPolicy->delete($this->regularUser, $this->otherUser))->toBeFalse()
            ->and($this->userPolicy->manageRole($this->regularUser, $this->otherUser))->toBeFalse()
            ->and($this->userPolicy->restore($this->regularUser))->toBeFalse()
            ->and($this->userPolicy->forceDelete($this->regularUser, $this->otherUser))->toBeFalse();
    });

    test('user cannot delete or modify their own role', function () {
        expect($this->userPolicy->delete($this->adminUser, $this->adminUser))->toBeFalse()
            ->and($this->userPolicy->manageRole($this->adminUser, $this->adminUser))->toBeFalse();
    });
});

// TASK POLICY UNIT TESTS
describe('Task Policy', function () {
    test('admin user passes all task policy checks', function () {
        expect($this->taskPolicy->viewAny())->toBeTrue()
            ->and($this->taskPolicy->create())->toBeTrue()
            ->and($this->taskPolicy->view($this->adminUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->update($this->adminUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->delete($this->adminUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->restore($this->adminUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->forceDelete($this->adminUser))->toBeTrue()
            ->and($this->taskPolicy->assign($this->adminUser))->toBeTrue()
            ->and($this->taskPolicy->toggleStatus($this->adminUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->bulk())->toBeTrue();
    });

    test('regular user can only access their own tasks', function () {
        $ownTask = Task::factory()->create([
            'user_id' => $this->adminUser->id,
            'assigned_to' => $this->regularUser->id,
        ]);

        // Can access their own task
        expect($this->taskPolicy->view($this->regularUser, $ownTask))->toBeTrue()
            ->and($this->taskPolicy->update($this->regularUser, $ownTask))->toBeTrue()
            ->and($this->taskPolicy->delete($this->regularUser, $ownTask))->toBeTrue()
            ->and($this->taskPolicy->toggleStatus($this->regularUser, $ownTask))->toBeTrue();

        // Can also view tasks assigned to them by others (task creator can always view)
        expect($this->taskPolicy->view($this->regularUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->update($this->regularUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->delete($this->regularUser, $this->task))->toBeTrue()
            ->and($this->taskPolicy->toggleStatus($this->regularUser, $this->task))->toBeTrue();

        // Cannot access other users' tasks that aren't assigned to them
        $otherTask = Task::factory()->create(['assigned_to' => $this->otherUser->id]);
        expect($this->taskPolicy->view($this->regularUser, $otherTask))->toBeFalse()
            ->and($this->taskPolicy->update($this->regularUser, $otherTask))->toBeFalse()
            ->and($this->taskPolicy->delete($this->regularUser, $otherTask))->toBeFalse();

        // Cannot perform admin-only operations
        expect($this->taskPolicy->forceDelete($this->regularUser))->toBeFalse()
            ->and($this->taskPolicy->assign($this->regularUser))->toBeFalse();

        // But can restore tasks assigned to them
        expect($this->taskPolicy->restore($this->regularUser, $this->task))->toBeTrue();

        // But can still view any tasks and create new ones
        expect($this->taskPolicy->viewAny())->toBeTrue()
            ->and($this->taskPolicy->create())->toBeTrue()
            ->and($this->taskPolicy->bulk())->toBeTrue();
    });

    test('unassigned user has limited task access', function () {
        $unassignedUser = User::factory()->regularUser()->create();
        $unassignedTask = Task::factory()->create(['assigned_to' => null]);

        expect($this->taskPolicy->view($unassignedUser, $unassignedTask))->toBeFalse()
            ->and($this->taskPolicy->update($unassignedUser, $unassignedTask))->toBeFalse()
            ->and($this->taskPolicy->delete($unassignedUser, $unassignedTask))->toBeFalse()
            ->and($this->taskPolicy->toggleStatus($unassignedUser, $unassignedTask))->toBeFalse();
    });
});

// TAG POLICY UNIT TESTS
describe('Tag Policy', function () {
    test('admin user passes all tag policy checks', function () {
        expect($this->tagPolicy->viewAny())->toBeTrue()
            ->and($this->tagPolicy->view())->toBeTrue()
            ->and($this->tagPolicy->create($this->adminUser))->toBeTrue()
            ->and($this->tagPolicy->update($this->adminUser))->toBeTrue()
            ->and($this->tagPolicy->delete($this->adminUser))->toBeTrue()
            ->and($this->tagPolicy->restore($this->adminUser))->toBeTrue()
            ->and($this->tagPolicy->forceDelete($this->adminUser))->toBeTrue()
            ->and($this->tagPolicy->viewWithCounts($this->adminUser))->toBeTrue()
            ->and($this->tagPolicy->viewWithTasks($this->adminUser))->toBeTrue();
    });

    test('regular user can only view tags', function () {
        expect($this->tagPolicy->viewAny())->toBeTrue()
            ->and($this->tagPolicy->view())->toBeTrue()
            ->and($this->tagPolicy->create($this->regularUser))->toBeFalse()
            ->and($this->tagPolicy->update($this->regularUser))->toBeFalse()
            ->and($this->tagPolicy->delete($this->regularUser))->toBeFalse()
            ->and($this->tagPolicy->restore($this->regularUser))->toBeFalse()
            ->and($this->tagPolicy->forceDelete($this->regularUser))->toBeFalse()
            ->and($this->tagPolicy->viewWithCounts($this->regularUser))->toBeFalse()
            ->and($this->tagPolicy->viewWithTasks($this->regularUser))->toBeFalse();
    });
});

// AUTHENTICATION ENDPOINT AUTHORIZATION TESTS
describe('Authentication Endpoints', function () {
    test('unauthenticated user cannot access protected endpoints', function () {
        // Me endpoint
        $this->getJson('/api/v1/me')
            ->assertStatus(401);

        // Logout endpoint
        $this->postJson('/api/v1/logout')
            ->assertStatus(401);

        // Tasks endpoints
        $this->getJson('/api/v1/tasks')
            ->assertStatus(401);

        $this->postJson('/api/v1/tasks', [])
            ->assertStatus(401);

        // Tags endpoints
        $this->getJson('/api/v1/tags')
            ->assertStatus(401);

        $this->postJson('/api/v1/tags', [])
            ->assertStatus(401);

        // Users endpoints
        $this->getJson("/api/v1/users/{$this->regularUser->id}/role")
            ->assertStatus(401);

        $this->patchJson("/api/v1/users/{$this->regularUser->id}/role", [])
            ->assertStatus(401);
    });

    test('authenticated user can access their profile', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->regularUser->id,
                'name' => $this->regularUser->name,
                'email' => $this->regularUser->email,
                'role' => $this->regularUser->role,
            ]);
    });

    test('authenticated user can logout', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Logout successful']);
    });
});

// TASK ENDPOINT AUTHORIZATION TESTS
describe('Task Endpoints', function () {
    test('all authenticated users can list tasks', function () {
        Sanctum::actingAs($this->regularUser);
        $this->getJson('/api/v1/tasks')
            ->assertStatus(200);

        Sanctum::actingAs($this->adminUser);
        $this->getJson('/api/v1/tasks')
            ->assertStatus(200);
    });

    test('regular user can only see their own tasks', function () {
        Sanctum::actingAs($this->regularUser);

        // Create tasks for different users
        $ownTask = Task::factory()->create([
            'user_id' => $this->adminUser->id,
            'assigned_to' => $this->regularUser->id,
        ]);

        $otherTask = Task::factory()->create([
            'user_id' => $this->adminUser->id,
            'assigned_to' => $this->otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(200);

        // Should only see tasks assigned to them
        $taskIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($taskIds)->toContain($ownTask->id)
            ->and($taskIds)->not->toContain($otherTask->id);
    });

    test('admin user can see all tasks', function () {
        Sanctum::actingAs($this->adminUser);

        // Create multiple tasks
        Task::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(4); // 3 new + 1 existing
    });

    test('regular user can create tasks', function () {
        Sanctum::actingAs($this->regularUser);

        $taskData = [
            'title' => 'Regular User Task',
            'description' => 'Task created by regular user',
            'status' => 'pending',
            'priority' => 'medium',
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'title' => 'Regular User Task',
                'assigned_to' => $this->regularUser->id, // Should be auto-assigned
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Regular User Task',
            'assigned_to' => $this->regularUser->id,
        ]);
    });

    test('admin user can create tasks for other users', function () {
        Sanctum::actingAs($this->adminUser);

        $taskData = [
            'title' => 'Admin Assigned Task',
            'description' => 'Task assigned to another user',
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => $this->regularUser->id,
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'title' => 'Admin Assigned Task',
                'assigned_to' => $this->regularUser->id,
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Admin Assigned Task',
            'assigned_to' => $this->regularUser->id,
        ]);
    });

    test('regular user can view their own task', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/v1/tasks/{$this->task->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]);
    });

    test('regular user cannot view other users task', function () {
        Sanctum::actingAs($this->otherUser);

        $response = $this->getJson("/api/v1/tasks/{$this->task->id}");

        $response->assertStatus(403);
    });

    test('admin user can view any task', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/v1/tasks/{$this->task->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]);
    });

    test('regular user can update their own task', function () {
        $ownTask = Task::factory()->create([
            'user_id' => $this->adminUser->id,
            'assigned_to' => $this->regularUser->id,
        ]);

        Sanctum::actingAs($this->regularUser);

        $updateData = [
            'title' => 'Updated by Regular User',
            'version' => $ownTask->version,
        ];

        $response = $this->putJson("/api/v1/tasks/{$ownTask->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Updated by Regular User',
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $ownTask->id,
            'title' => 'Updated by Regular User',
        ]);
    });

    test('regular user cannot update other users task', function () {
        Sanctum::actingAs($this->otherUser);

        $updateData = [
            'title' => 'Should not work',
            'version' => $this->task->version,
        ];

        $response = $this->putJson("/api/v1/tasks/{$this->task->id}", $updateData);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('tasks', [
            'id' => $this->task->id,
            'title' => 'Should not work',
        ]);
    });

    test('regular user can delete their own task', function () {
        $ownTask = Task::factory()->create([
            'user_id' => $this->adminUser->id,
            'assigned_to' => $this->regularUser->id,
        ]);

        Sanctum::actingAs($this->regularUser);

        $response = $this->deleteJson("/api/v1/tasks/{$ownTask->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Task deleted successfully']);

        // Check that task is soft deleted
        $this->assertDatabaseMissing('tasks', [
            'id' => $ownTask->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $ownTask->id,
        ]);

        // Verify deleted_at is not null
        $task = Task::withTrashed()->find($ownTask->id);
        expect($task->deleted_at)->not->toBeNull();
    });

    test('regular user cannot delete other users task', function () {
        Sanctum::actingAs($this->otherUser);

        $response = $this->deleteJson("/api/v1/tasks/{$this->task->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('tasks', ['id' => $this->task->id]);
    });

    test('admin user can delete any task', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/v1/tasks/{$this->task->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Task deleted successfully']);

        // Check that task is soft deleted
        $this->assertDatabaseMissing('tasks', [
            'id' => $this->task->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
        ]);

        // Verify deleted_at is not null
        $task = Task::withTrashed()->find($this->task->id);
        expect($task->deleted_at)->not->toBeNull();
    });
});

// TAG ENDPOINT AUTHORIZATION TESTS
describe('Tag Endpoints', function () {
    test('all authenticated users can list tags', function () {
        Sanctum::actingAs($this->regularUser);
        $this->getJson('/api/v1/tags')
            ->assertStatus(200);

        Sanctum::actingAs($this->adminUser);
        $this->getJson('/api/v1/tags')
            ->assertStatus(200);
    });

    test('regular user cannot create tags', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/api/v1/tags', [
            'name' => 'Regular User Tag',
            'color' => '#ff0000',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('tags', ['name' => 'Regular User Tag']);
    });

    test('admin user can create tags', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/tags', [
            'name' => 'Admin Tag',
            'color' => '#00ff00',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Admin Tag']);

        $this->assertDatabaseHas('tags', [
            'name' => 'Admin Tag',
            'color' => '#00ff00',
        ]);
    });

    test('regular user cannot update tags', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->putJson("/api/v1/tags/{$this->tag->id}", [
            'name' => 'Updated Tag Name',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('tags', ['name' => 'Updated Tag Name']);
    });

    test('admin user can update tags', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/v1/tags/{$this->tag->id}", [
            'name' => 'Updated Tag Name',
            'color' => '#0000ff',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Tag Name',
                'color' => '#0000ff',
            ]);

        $this->assertDatabaseHas('tags', [
            'id' => $this->tag->id,
            'name' => 'Updated Tag Name',
            'color' => '#0000ff',
        ]);
    });

    test('regular user cannot delete tags', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->deleteJson("/api/v1/tags/{$this->tag->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('tags', ['id' => $this->tag->id]);
    });

    test('admin user can delete tags', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/v1/tags/{$this->tag->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Tag deleted successfully']);

        $this->assertDatabaseMissing('tags', ['id' => $this->tag->id]);
    });
});

// USER MANAGEMENT ENDPOINT AUTHORIZATION TESTS
describe('User Management Endpoints', function () {
    test('regular user cannot view other users role', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/v1/users/{$this->otherUser->id}/role");

        $response->assertStatus(403);
    });

    test('user can view their own role', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/v1/users/{$this->regularUser->id}/role");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'user_id' => $this->regularUser->id,
                'name' => $this->regularUser->name,
                'email' => $this->regularUser->email,
                'role' => $this->regularUser->role,
            ]);
    });

    test('admin user can view any user role', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/v1/users/{$this->regularUser->id}/role");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'user_id' => $this->regularUser->id,
                'name' => $this->regularUser->name,
                'email' => $this->regularUser->email,
                'role' => $this->regularUser->role,
            ]);
    });

    test('regular user cannot update any user role', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->patchJson("/api/v1/users/{$this->otherUser->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $this->otherUser->id,
            'role' => 'user', // Should remain unchanged
        ]);
    });

    test('admin user can update other user role', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/v1/users/{$this->regularUser->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User role updated from \'user\' to \'admin\' successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'role' => 'admin',
        ]);
    });

    test('admin user cannot update their own role', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/v1/users/{$this->adminUser->id}/role", [
            'role' => 'user',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
            'role' => 'admin', // Should remain unchanged
        ]);
    });
});
