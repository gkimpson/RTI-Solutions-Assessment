<?php

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->managerUser = User::factory()->regularUser()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();
});

// Index Tests (GET /api/v1/tags)
test('unauthenticated user cannot access tags endpoint', function () {
    $this->getJson('/api/v1/tags')->assertStatus(401);
});

test('authenticated user can list tags', function () {
    Sanctum::actingAs($this->regularUser);
    Tag::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/tags');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'color', 'created_at', 'updated_at'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('regular user cannot see task counts', function () {
    Sanctum::actingAs($this->regularUser);
    Tag::factory()->create(['name' => 'Work']);

    $response = $this->getJson('/api/v1/tags?with_counts=true');

    $response->assertStatus(200);

    // Should not include tasks_count in response
    $workTag = collect($response->json('data'))->firstWhere('name', 'Work');
    expect(array_key_exists('tasks_count', (array) $workTag))->toBeFalse();
});

test('admin can list tags with related tasks', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Work']);
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($tag);

    $response = $this->getJson('/api/v1/tags?with_tasks=true');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'color', 'tasks', 'created_at', 'updated_at'],
            ],
        ]);

    $workTag = collect($response->json('data'))->firstWhere('name', 'Work');
    expect($workTag['tasks'])->toHaveCount(1)
        ->and($workTag['tasks'][0]['id'])->toBe($task->id);
});

test('tags can be searched by name', function () {
    Sanctum::actingAs($this->regularUser);
    Tag::factory()->create(['name' => 'Work Tasks']);
    Tag::factory()->create(['name' => 'Personal Tasks']);
    Tag::factory()->create(['name' => 'Shopping']);

    $response = $this->getJson('/api/v1/tags?search=Task');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $names = collect($response->json('data'))->pluck('name')->toArray();
    expect($names)->toContain('Work Tasks', 'Personal Tasks')
        ->and($names)->not->toContain('Shopping');
});

test('tags can be filtered by color', function () {
    Sanctum::actingAs($this->regularUser);
    Tag::factory()->create(['name' => 'Red Tag', 'color' => '#ff0000']);
    Tag::factory()->create(['name' => 'Blue Tag', 'color' => '#0000ff']);
    Tag::factory()->create(['name' => 'No Color Tag', 'color' => null]);

    $response = $this->getJson('/api/v1/tags?color=%23ff0000');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Red Tag', 'color' => '#ff0000']);
});

test('tags can be sorted by different fields', function () {
    Sanctum::actingAs($this->regularUser);
    Tag::factory()->create(['name' => 'Z Tag', 'created_at' => now()->subDays(2)]);
    Tag::factory()->create(['name' => 'A Tag', 'created_at' => now()]);

    // Sort by name ascending (default)
    $response = $this->getJson('/api/v1/tags?sort_by=name&sort_direction=asc');
    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name')->toArray();
    expect($names[0])->toBe('A Tag')
        ->and($names[1])->toBe('Z Tag');

    // Sort by created_at descending
    $response = $this->getJson('/api/v1/tags?sort_by=created_at&sort_direction=desc');
    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name')->toArray();
    expect($names[0])->toBe('A Tag')
        ->and($names[1])->toBe('Z Tag'); // newer first
});

// Store Tests (POST /api/v1/tags)
test('admin can create a tag with valid data', function () {
    Sanctum::actingAs($this->adminUser);

    $tagData = [
        'name' => 'New Work Tag',
        'color' => '#ff0000',
    ];

    $response = $this->postJson('/api/v1/tags', $tagData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'color', 'created_at', 'updated_at'],
        ])
        ->assertJsonFragment([
            'message' => 'Tag created successfully',
            'name' => 'New Work Tag',
            'color' => '#ff0000',
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => 'New Work Tag',
        'color' => '#ff0000',
    ]);
});

test('admin can create a tag without color', function () {
    Sanctum::actingAs($this->adminUser);

    $tagData = ['name' => 'Colorless Tag'];

    $response = $this->postJson('/api/v1/tags', $tagData);

    $response->assertStatus(201)
        ->assertJsonFragment([
            'name' => 'Colorless Tag',
            'color' => null,
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => 'Colorless Tag',
        'color' => null,
    ]);
});

test('regular user cannot create tags', function () {
    Sanctum::actingAs($this->regularUser);

    $tagData = ['name' => 'User Tag'];

    $this->postJson('/api/v1/tags', $tagData)
        ->assertStatus(403);

    $this->assertDatabaseMissing('tags', ['name' => 'User Tag']);
});

test('tag creation fails with invalid data', function () {
    Sanctum::actingAs($this->adminUser);

    // Missing name
    $this->postJson('/api/v1/tags')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Name too short
    $this->postJson('/api/v1/tags', ['name' => 'A'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Name too long
    $this->postJson('/api/v1/tags', ['name' => str_repeat('A', 51)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Invalid color format
    $this->postJson('/api/v1/tags', [
        'name' => 'Valid Name',
        'color' => 'invalid-color',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['color']);
});

test('tag creation fails with duplicate name', function () {
    Sanctum::actingAs($this->adminUser);
    Tag::factory()->create(['name' => 'Existing Tag']);

    $this->postJson('/api/v1/tags', ['name' => 'Existing Tag'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name'])
        ->assertJsonFragment([
            'name' => ['A tag with this name already exists.'],
        ]);
});

test('tag creation accepts various hex color formats', function () {
    Sanctum::actingAs($this->adminUser);

    // 6-digit hex
    $this->postJson('/api/v1/tags', ['name' => 'Six Digit', 'color' => '#ff0000'])
        ->assertStatus(201);

    // 3-digit hex
    $this->postJson('/api/v1/tags', ['name' => 'Three Digit', 'color' => '#f00'])
        ->assertStatus(201);

    // Uppercase
    $this->postJson('/api/v1/tags', ['name' => 'Uppercase', 'color' => '#FF0000'])
        ->assertStatus(201);

    $this->assertDatabaseHas('tags', ['name' => 'Six Digit', 'color' => '#ff0000']);
    $this->assertDatabaseHas('tags', ['name' => 'Three Digit', 'color' => '#f00']);
    $this->assertDatabaseHas('tags', ['name' => 'Uppercase', 'color' => '#FF0000']);
});

// Show Tests (GET /api/v1/tags/{id})
test('authenticated user can view a specific tag', function () {
    Sanctum::actingAs($this->regularUser);
    $tag = Tag::factory()->create(['name' => 'Test Tag', 'color' => '#00ff00']);

    $response = $this->getJson("/api/v1/tags/$tag->id");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'color', 'created_at', 'updated_at'],
        ])
        ->assertJsonFragment([
            'id' => $tag->id,
            'name' => 'Test Tag',
            'color' => '#00ff00',
        ]);
});

test('tag show includes related tasks', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Tag With Tasks']);
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($tag);

    $response = $this->getJson("/api/v1/tags/$tag->id");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'color', 'created_at', 'updated_at',
                'tasks' => [
                    '*' => ['id', 'title', 'assigned_user'],
                ],
            ],
        ]);

    $responseData = $response->json('data');
    expect($responseData['tasks'])->toHaveCount(1)
        ->and($responseData['tasks'][0]['id'])->toBe($task->id);
});

test('viewing non-existent tag returns 404', function () {
    Sanctum::actingAs($this->regularUser);

    $this->getJson('/api/v1/tags/999')
        ->assertStatus(404)
        ->assertJsonFragment(['message' => 'Tag not found']);
});

test('unauthenticated user cannot view specific tag', function () {
    $tag = Tag::factory()->create();

    $this->getJson("/api/v1/tags/$tag->id")
        ->assertStatus(401);
});

// Update Tests (PUT /api/v1/tags/{id})
test('admin can update tag with valid data', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Original Name', 'color' => '#ff0000']);

    $updateData = [
        'name' => 'Updated Name',
        'color' => '#00ff00',
    ];

    $response = $this->putJson("/api/v1/tags/$tag->id", $updateData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'color', 'created_at', 'updated_at'],
        ])
        ->assertJsonFragment([
            'message' => 'Tag updated successfully',
            'name' => 'Updated Name',
            'color' => '#00ff00',
        ]);

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => 'Updated Name',
        'color' => '#00ff00',
    ]);
});

test('admin can update only tag name', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Original Name', 'color' => '#ff0000']);

    $response = $this->putJson("/api/v1/tags/$tag->id", ['name' => 'Updated Name Only']);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Updated Name Only',
            'color' => '#ff0000', // Should remain unchanged
        ]);
});

test('admin can update only tag color', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Original Name', 'color' => '#ff0000']);

    $response = $this->putJson("/api/v1/tags/$tag->id", ['color' => '#00ff00']);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Original Name', // Should remain unchanged
            'color' => '#00ff00',
        ]);
});

test('admin can remove tag color by setting it to null', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Colored Tag', 'color' => '#ff0000']);

    $response = $this->putJson("/api/v1/tags/$tag->id", ['color' => null]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Colored Tag',
            'color' => null,
        ]);
});

test('regular user cannot update tags', function () {
    Sanctum::actingAs($this->regularUser);
    $tag = Tag::factory()->create(['name' => 'Original Name']);

    $this->putJson("/api/v1/tags/$tag->id", ['name' => 'Updated Name'])
        ->assertStatus(403);

    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Original Name']);
});

test('tag update fails with invalid data', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Valid Tag']);

    // Name too short
    $this->putJson("/api/v1/tags/$tag->id", ['name' => 'A'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Invalid color
    $this->putJson("/api/v1/tags/$tag->id", ['color' => 'invalid'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['color']);
});

test('tag update fails with duplicate name', function () {
    Sanctum::actingAs($this->adminUser);
    Tag::factory()->create(['name' => 'Existing Tag']);
    $targetTag = Tag::factory()->create(['name' => 'Target Tag']);

    $this->putJson("/api/v1/tags/$targetTag->id", ['name' => 'Existing Tag'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('tag can be updated with same name (no duplicate error)', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Same Name', 'color' => '#ff0000']);

    // Update color but keep same name
    $response = $this->putJson("/api/v1/tags/$tag->id", [
        'name' => 'Same Name',
        'color' => '#00ff00',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Same Name',
            'color' => '#00ff00',
        ]);
});

test('updating non-existent tag returns 404', function () {
    Sanctum::actingAs($this->adminUser);

    $this->putJson('/api/v1/tags/999', ['name' => 'Updated Name'])
        ->assertStatus(404)
        ->assertJsonFragment(['message' => 'Tag not found']);
});

// Delete Tests (DELETE /api/v1/tags/{id})
test('admin can delete tag with no associated tasks', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Tag to Delete']);

    $response = $this->deleteJson("/api/v1/tags/$tag->id");

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Tag deleted successfully']);

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

test('admin cannot delete tag that has associated tasks', function () {
    Sanctum::actingAs($this->adminUser);
    $tag = Tag::factory()->create(['name' => 'Tag With Tasks']);
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($tag);

    $response = $this->deleteJson("/api/v1/tags/$tag->id");

    $response->assertStatus(409)
        ->assertJsonFragment([
            'message' => 'Cannot delete tag that is still assigned to tasks',
            'tasks_count' => 1,
        ]);

    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

test('deleting non-existent tag returns 404', function () {
    Sanctum::actingAs($this->adminUser);

    $this->deleteJson('/api/v1/tags/999')
        ->assertStatus(404)
        ->assertJsonFragment(['message' => 'Tag not found']);
});

test('unauthenticated user cannot delete tags', function () {
    $tag = Tag::factory()->create();

    $this->deleteJson("/api/v1/tags/$tag->id")
        ->assertStatus(401);
});

// Integration Tests
test('full tag CRUD workflow works correctly', function () {
    Sanctum::actingAs($this->adminUser);

    // 1. Create a tag
    $createResponse = $this->postJson('/api/v1/tags', [
        'name' => 'Workflow Tag',
        'color' => '#ff0000',
    ]);
    $createResponse->assertStatus(201);
    $tagId = $createResponse->json('data.id');

    // 2. List tags and verify it's included
    $listResponse = $this->getJson('/api/v1/tags');
    $listResponse->assertStatus(200);
    $tagExists = collect($listResponse->json('data'))->contains('id', $tagId);
    expect($tagExists)->toBeTrue();

    // 3. Show the specific tag
    $showResponse = $this->getJson("/api/v1/tags/$tagId");
    $showResponse->assertStatus(200)
        ->assertJsonFragment(['name' => 'Workflow Tag', 'color' => '#ff0000']);

    // 4. Update the tag
    $updateResponse = $this->putJson("/api/v1/tags/$tagId", [
        'name' => 'Updated Workflow Tag',
        'color' => '#00ff00',
    ]);
    $updateResponse->assertStatus(200)
        ->assertJsonFragment(['name' => 'Updated Workflow Tag', 'color' => '#00ff00']);

    // 5. Delete the tag
    $deleteResponse = $this->deleteJson("/api/v1/tags/$tagId");
    $deleteResponse->assertStatus(200)
        ->assertJsonFragment(['message' => 'Tag deleted successfully']);

    // 6. Verify it's gone
    $this->getJson("/api/v1/tags/$tagId")
        ->assertStatus(404);
});

test('tag with tasks workflow shows proper conflict handling', function () {
    Sanctum::actingAs($this->adminUser);

    // Create tag and associate with task
    $tag = Tag::factory()->create(['name' => 'Protected Tag']);
    $task = Task::factory()->create(['user_id' => $this->adminUser->id]);
    $task->tags()->attach($tag);

    // Try to delete tag with tasks - should fail
    $deleteResponse = $this->deleteJson("/api/v1/tags/$tag->id");
    $deleteResponse->assertStatus(409)
        ->assertJsonFragment(['message' => 'Cannot delete tag that is still assigned to tasks']);

    // Remove tag from task
    $task->tags()->detach($tag);

    // Now deletion should succeed
    $deleteResponse = $this->deleteJson("/api/v1/tags/$tag->id");
    $deleteResponse->assertStatus(200)
        ->assertJsonFragment(['message' => 'Tag deleted successfully']);
});
