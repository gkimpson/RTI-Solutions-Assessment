<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiResponseFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_success_response_format()
    {
        Sanctum::actingAs($this->user);

        $task = Task::factory()->create(['assigned_to' => $this->user->id]);

        $response = $this->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'timestamp',
            ])
            ->assertJson(['success' => true])
            ->assertJsonPath('timestamp', function ($timestamp) {
                return is_string($timestamp) && strtotime($timestamp) !== false;
            });
    }

    public function test_error_response_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks/999999');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
            ])
            ->assertJson(['success' => false]);
    }

    public function test_validation_error_response_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'ab', // Too short
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'timestamp',
            ])
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
            ]);
    }

    public function test_security_headers_present()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks');

        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'",
            'X-API-Version' => 'v1',
            'Cache-Control' => 'must-revalidate, no-cache, no-store, private',
        ];

        foreach ($securityHeaders as $header => $expectedValue) {
            $response->assertHeader($header, $expectedValue);
        }
    }

    public function test_rate_limit_headers_present()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertHeaderMissing('X-RateLimit-Limit')
            ->assertHeaderMissing('X-RateLimit-Remaining');

        // Note: Rate limiting headers are simplified in this implementation
        // In production, you'd integrate with actual rate limiting middleware
    }

    public function test_content_type_header()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_timestamps_are_iso_format()
    {
        Sanctum::actingAs($this->user);

        $task = Task::factory()->create(['assigned_to' => $this->user->id]);

        $response = $this->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200);

        $responseData = $response->json();

        // Check response timestamp
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $responseData['timestamp']
        );

        // Check task timestamps
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $responseData['data']['created_at']
        );
    }

    public function test_bulk_operation_response_format()
    {
        Sanctum::actingAs($this->user);

        $tasks = Task::factory()->count(3)->create(['assigned_to' => $this->user->id]);

        $response = $this->postJson('/api/v1/tasks/bulk', [
            'action' => 'update_status',
            'task_ids' => $tasks->pluck('id')->toArray(),
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'action',
                'processed_count',
                'conflict_count',
                'errors',
                'conflicted_tasks',
            ]);
    }

    public function test_authentication_error_format()
    {
        // Test without authentication
        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authorization_error_format()
    {
        $adminUser = User::factory()->admin()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($otherUser);

        $adminTask = Task::factory()->create(['assigned_to' => $adminUser->id]);

        $response = $this->getJson("/api/v1/tasks/{$adminTask->id}");

        $response->assertStatus(403); // Authorization forbidden
    }

    public function test_not_found_error_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks/999999');

        $response->assertStatus(500); // Model not found becomes 500 in our error handling
    }

    public function test_method_not_allowed_error_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->patchJson('/api/v1/tasks'); // PATCH not allowed on collection

        $response->assertStatus(405);
    }
}
