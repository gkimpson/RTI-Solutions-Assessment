<?php

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Policies\TagPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->managerUser = User::factory()->regularUser()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();

    $this->tag = Tag::factory()->create(['name' => 'Test Tag', 'color' => '#ff0000']);
    $this->policy = new TagPolicy;
});

// Policy Unit Tests
test('admin user passes all tag policy checks', function () {
    expect($this->policy->viewAny())->toBeTrue()
        ->and($this->policy->view())->toBeTrue()
        ->and($this->policy->create($this->adminUser))->toBeTrue()
        ->and($this->policy->update($this->adminUser))->toBeTrue()
        ->and($this->policy->delete($this->adminUser))->toBeTrue()
        ->and($this->policy->restore($this->adminUser))->toBeTrue()
        ->and($this->policy->forceDelete($this->adminUser))->toBeTrue()
        ->and($this->policy->viewWithCounts($this->adminUser))->toBeTrue()
        ->and($this->policy->viewWithTasks($this->adminUser))->toBeTrue();
});

test('regular user can only view tags', function () {
    expect($this->policy->viewAny())->toBeTrue()
        ->and($this->policy->view())->toBeTrue()
        ->and($this->policy->create($this->regularUser))->toBeFalse()
        ->and($this->policy->update($this->regularUser))->toBeFalse()
        ->and($this->policy->delete($this->regularUser))->toBeFalse()
        ->and($this->policy->restore($this->regularUser))->toBeFalse()
        ->and($this->policy->forceDelete($this->regularUser))->toBeFalse()
        ->and($this->policy->viewWithCounts($this->regularUser))->toBeFalse()
        ->and($this->policy->viewWithTasks($this->regularUser))->toBeFalse();
});

test('manager user has same permissions as regular user for tags', function () {
    expect($this->policy->viewAny())->toBeTrue()
        ->and($this->policy->view())->toBeTrue()
        ->and($this->policy->create($this->managerUser))->toBeFalse()
        ->and($this->policy->update($this->managerUser))->toBeFalse()
        ->and($this->policy->delete($this->managerUser))->toBeFalse()
        ->and($this->policy->restore($this->managerUser))->toBeFalse()
        ->and($this->policy->forceDelete($this->managerUser))->toBeFalse()
        ->and($this->policy->viewWithCounts($this->managerUser))->toBeFalse()
        ->and($this->policy->viewWithTasks($this->managerUser))->toBeFalse();
});

// API Endpoint Authorization Tests

// Index Authorization Tests
test('unauthenticated user cannot access tags index', function () {
    $this->getJson('/api/v1/tags')
        ->assertStatus(401);
});

test('all authenticated users can access tags index', function () {
    Sanctum::actingAs($this->regularUser);
    $this->getJson('/api/v1/tags')
        ->assertStatus(200);

    Sanctum::actingAs($this->managerUser);
    $this->getJson('/api/v1/tags')
        ->assertStatus(200);

    Sanctum::actingAs($this->adminUser);
    $this->getJson('/api/v1/tags')
        ->assertStatus(200);
});

test('regular user cannot access tags with counts', function () {
    Sanctum::actingAs($this->regularUser);
    $tag = Tag::factory()->create();
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($tag);

    $response = $this->getJson('/api/v1/tags?with_counts=true');
    $response->assertStatus(200);

    // Response should not include tasks_count for regular users
    $tagData = (array) collect($response->json('data'))->firstWhere('id', $tag->id);
    expect(array_key_exists('tasks_count', $tagData))->toBeFalse();
});

test('regular user cannot access tags with tasks', function () {
    Sanctum::actingAs($this->regularUser);
    $tag = Tag::factory()->create();
    $task = Task::factory()->create(['user_id' => $this->adminUser->id, 'assigned_to' => $this->regularUser->id]);
    $task->tags()->attach($tag);

    $response = $this->getJson('/api/v1/tags?with_tasks=true');
    $response->assertStatus(200);

    // Regular users shouldn't get tasks in response (policy prevents it)
    $tagData = collect($response->json('data'))->firstWhere('id', $tag->id);
    expect($tagData)->not->toHaveKey('tasks');
});

test('admin user can access tags with tasks', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create();
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($tag);

    $response = $this->getJson('/api/v1/tags?with_tasks=true');
    $response->assertStatus(200);

    // Admin should get tasks in response
    $tagData = collect($response->json('data'))->firstWhere('id', $tag->id);
    expect($tagData['tasks'])->toHaveCount(1)
        ->and($tagData['tasks'][0]['id'])->toBe($task->id);
});

// Show Authorization Tests
test('unauthenticated user cannot view specific tag', function () {
    $this->getJson("/api/v1/tags/{$this->tag->id}")
        ->assertStatus(401);
});

test('all authenticated users can view specific tag', function () {
    Sanctum::actingAs($this->regularUser);
    $this->getJson("/api/v1/tags/{$this->tag->id}")
        ->assertStatus(200);

    Sanctum::actingAs($this->managerUser);
    $this->getJson("/api/v1/tags/{$this->tag->id}")
        ->assertStatus(200);

    Sanctum::actingAs($this->adminUser);
    $this->getJson("/api/v1/tags/{$this->tag->id}")
        ->assertStatus(200);
});

// Create Authorization Tests
test('unauthenticated user cannot create tags', function () {
    $this->postJson('/api/v1/tags', ['name' => 'Unauthorized Tag'])
        ->assertStatus(401);

    $this->assertDatabaseMissing('tags', ['name' => 'Unauthorized Tag']);
});

test('regular user cannot create tags', function () {
    Sanctum::actingAs($this->regularUser);

    $this->postJson('/api/v1/tags', ['name' => 'Regular User Tag'])
        ->assertStatus(403);

    $this->assertDatabaseMissing('tags', ['name' => 'Regular User Tag']);
});

test('manager user cannot create tags', function () {
    Sanctum::actingAs($this->managerUser);

    $this->postJson('/api/v1/tags', ['name' => 'Manager Tag'])
        ->assertStatus(403);

    $this->assertDatabaseMissing('tags', ['name' => 'Manager Tag']);
});

test('admin user can create tags', function () {
    Sanctum::actingAs($this->adminUser);

    $response = $this->postJson('/api/v1/tags', [
        'name' => 'Admin Tag',
        'color' => '#00ff00',
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['name' => 'Admin Tag']);

    $this->assertDatabaseHas('tags', ['name' => 'Admin Tag', 'color' => '#00ff00']);
});

// Update Authorization Tests
test('unauthenticated user cannot update tags', function () {
    $this->putJson("/api/v1/tags/{$this->tag->id}", ['name' => 'Updated Name'])
        ->assertStatus(401);

    $this->assertDatabaseHas('tags', ['id' => $this->tag->id, 'name' => 'Test Tag']);
});

test('regular user cannot update tags', function () {
    Sanctum::actingAs($this->regularUser);

    $this->putJson("/api/v1/tags/{$this->tag->id}", ['name' => 'Updated by Regular'])
        ->assertStatus(403);

    $this->assertDatabaseHas('tags', ['id' => $this->tag->id, 'name' => 'Test Tag']);
});

test('manager user cannot update tags', function () {
    Sanctum::actingAs($this->managerUser);

    $this->putJson("/api/v1/tags/{$this->tag->id}", ['name' => 'Updated by Manager'])
        ->assertStatus(403);

    $this->assertDatabaseHas('tags', ['id' => $this->tag->id, 'name' => 'Test Tag']);
});

test('admin user can update tags', function () {
    Sanctum::actingAs($this->adminUser);

    $response = $this->putJson("/api/v1/tags/{$this->tag->id}", [
        'name' => 'Updated by Admin',
        'color' => '#0000ff',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'Updated by Admin', 'color' => '#0000ff']);

    $this->assertDatabaseHas('tags', [
        'id' => $this->tag->id,
        'name' => 'Updated by Admin',
        'color' => '#0000ff',
    ]);
});

// Delete Authorization Tests
test('unauthenticated user cannot delete tags', function () {
    $this->deleteJson("/api/v1/tags/{$this->tag->id}")
        ->assertStatus(401);

    $this->assertDatabaseHas('tags', ['id' => $this->tag->id]);
});

test('admin user can delete tags', function () {
    Sanctum::actingAs($this->adminUser);

    $response = $this->deleteJson("/api/v1/tags/{$this->tag->id}");

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Tag deleted successfully']);

    $this->assertDatabaseMissing('tags', ['id' => $this->tag->id]);
});

test('admin cannot delete tag with associated tasks', function () {
    Sanctum::actingAs($this->adminUser);
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($this->tag);

    $response = $this->deleteJson("/api/v1/tags/{$this->tag->id}");

    $response->assertStatus(409)
        ->assertJsonFragment([
            'message' => 'Cannot delete tag that is still assigned to tasks',
            'tasks_count' => 1,
        ]);

    $this->assertDatabaseHas('tags', ['id' => $this->tag->id]);
});
