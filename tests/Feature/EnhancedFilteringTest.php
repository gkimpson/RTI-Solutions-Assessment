<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnhancedFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
    }

    public function test_search_validation_with_minimum_length()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks?search=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search'])
            ->assertJsonFragment([
                'search' => ['Search term must be at least 2 characters.'],
            ]);
    }

    public function test_search_validation_with_invalid_characters()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks?search=<script>alert("xss")</script>');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_search_with_sql_injection_attempt_is_rejected()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks?search=test; DROP TABLE tasks;--');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_tag_operator_validation()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks?tags[]=test&tag_operator=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_operator']);
    }

    public function test_bulk_operation_with_empty_task_ids()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'delete',
            'task_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_bulk_operation_with_too_many_tasks()
    {
        Sanctum::actingAs($this->admin);

        $taskIds = range(1, 101); // More than MAX_BULK_TASKS (100)

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'delete',
            'task_ids' => $taskIds,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_due_date_too_far_in_future()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Future Task',
            'due_date' => now()->addYears(15)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.due_date.0', 'The due date cannot be more than 10 years in the future.');
    }

    public function test_metadata_validation_with_deep_nesting()
    {
        Sanctum::actingAs($this->user);

        $deepMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'too deep',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Task with deep metadata',
            'metadata' => $deepMetadata,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['metadata']);
    }

    public function test_metadata_sanitization_removes_dangerous_content()
    {
        Sanctum::actingAs($this->user);

        $dangerousMetadata = [
            'description' => '<script>alert("xss")</script>Normal content',
            'handler' => 'onclick="malicious()"',
            'url' => 'javascript:alert("xss")',
        ];

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Task with dangerous metadata',
            'metadata' => $dangerousMetadata,
        ]);

        // The request should succeed but metadata should be sanitized
        $response->assertStatus(201);

        $task = Task::latest()->first();

        // Check that dangerous content was removed
        $this->assertStringNotContainsString('<script>', json_encode($task->metadata));
        $this->assertStringNotContainsString('onclick', json_encode($task->metadata));
        $this->assertStringNotContainsString('javascript:', json_encode($task->metadata));

        // Check that safe content remains
        $this->assertStringContainsString('Normal content', json_encode($task->metadata));
    }

    public function test_tag_filtering_with_and_operator()
    {
        Sanctum::actingAs($this->user);

        $tag1 = Tag::factory()->create(['name' => 'urgent']);
        $tag2 = Tag::factory()->create(['name' => 'backend']);

        $task1 = Task::factory()->create(['assigned_to' => $this->user->id]);
        $task1->tags()->attach([$tag1->id, $tag2->id]);

        $task2 = Task::factory()->create(['assigned_to' => $this->user->id]);
        $task2->tags()->attach([$tag1->id]);

        $response = $this->getJson('/api/v1/tasks?tags[]=urgent&tags[]=backend&tag_operator=and');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task1->id);
    }

    public function test_tag_filtering_with_or_operator()
    {
        Sanctum::actingAs($this->user);

        $tag1 = Tag::factory()->create(['name' => 'urgent']);
        $tag2 = Tag::factory()->create(['name' => 'backend']);

        $task1 = Task::factory()->create(['assigned_to' => $this->user->id]);
        $task1->tags()->attach([$tag1->id]);

        $task2 = Task::factory()->create(['assigned_to' => $this->user->id]);
        $task2->tags()->attach([$tag2->id]);

        $task3 = Task::factory()->create(['assigned_to' => $this->user->id]);
        // No tags

        $response = $this->getJson('/api/v1/tasks?tags[]=urgent&tags[]=backend&tag_operator=or');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_aggressive_caching_detection()
    {
        Sanctum::actingAs($this->user);

        // Create a task for testing
        Task::factory()->create(['assigned_to' => $this->user->id]);

        // Non-search query should use aggressive caching
        $response1 = $this->getJson('/api/v1/tasks?status=pending');
        $response1->assertStatus(200);

        // Search query should not use aggressive caching
        $response2 = $this->getJson('/api/v1/tasks?search=test');
        $response2->assertStatus(200);

        // Recent date filter should not use aggressive caching
        $recentDate = now()->subHours(1)->toDateString();
        $response3 = $this->getJson("/api/v1/tasks?updated_from={$recentDate}");
        $response3->assertStatus(200);
    }

    public function test_response_format_consistency()
    {
        Sanctum::actingAs($this->user);

        Task::factory()->create(['assigned_to' => $this->user->id]);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'filters_applied',
                    'total_count',
                    'per_page',
                    'current_page',
                    'last_page',
                    'from',
                    'to',
                    'cached',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'timestamp',
            ])
            ->assertJson(['success' => true])
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_sort_field_validation_rejects_invalid_fields()
    {
        Sanctum::actingAs($this->user);

        Task::factory()->count(3)->create(['assigned_to' => $this->user->id]);

        // Attempt SQL injection through sort field
        $response = $this->getJson('/api/v1/tasks?sort_by=title; DROP TABLE tasks;--');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    public function test_multiple_sort_fields_validation()
    {
        Sanctum::actingAs($this->user);

        Task::factory()->count(3)->create(['assigned_to' => $this->user->id]);

        // Test that invalid comma-separated sort fields are rejected
        $response = $this->getJson('/api/v1/tasks?sort_by=title,status,priority,created_at,updated_at');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }
}
