<?php

use App\Models\Task;
use App\Models\User;
use App\Rules\ValidDueDate;
use App\Rules\ValidTaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();
});

// ValidDueDate Rule Tests
describe('ValidDueDate Rule', function () {
    test('accepts valid future dates', function () {
        $rule = new ValidDueDate;
        $fail = function ($message) {
            throw new RuntimeException($message);
        };

        $futureDates = [
            now()->addDay()->format('Y-m-d'),
            now()->addWeek()->format('Y-m-d'),
            now()->addMonth()->format('Y-m-d'),
            now()->addYear()->format('Y-m-d'),
            now()->addDays(30)->format('Y-m-d H:i:s'),
        ];

        foreach ($futureDates as $date) {
            // Should not throw exception
            expect(fn () => $rule->validate('due_date', $date, $fail))->not->toThrow(Exception::class);
        }
    });

    test('accepts today as valid date', function () {
        $rule = new ValidDueDate;
        $fail = function ($message) {
            throw new RuntimeException($message);
        };

        $todayDates = [
            now()->format('Y-m-d'),
            now()->startOfDay()->format('Y-m-d H:i:s'),
            now()->endOfDay()->format('Y-m-d H:i:s'),
        ];

        foreach ($todayDates as $date) {
            // Should not throw exception
            expect(fn () => $rule->validate('due_date', $date, $fail))->not->toThrow(Exception::class);
        }
    });

    test('rejects past dates', function () {
        $rule = new ValidDueDate;
        $failedMessage = null;
        $fail = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        $pastDates = [
            now()->subDay()->format('Y-m-d'),
            now()->subWeek()->format('Y-m-d'),
            now()->subMonth()->format('Y-m-d'),
            now()->subYear()->format('Y-m-d'),
            '2020-01-01',
            '2023-12-31',
        ];

        foreach ($pastDates as $date) {
            $failedMessage = null;
            $rule->validate('due_date', $date, $fail);
            expect($failedMessage)->toBe('The :attribute cannot be in the past.');
        }
    });

});

// ValidTaskAssignment Rule Tests
describe('ValidTaskAssignment Rule', function () {
    test('allows admin to assign tasks to any user', function () {
        Sanctum::actingAs($this->adminUser);

        $rule = new ValidTaskAssignment;
        $fail = function ($message) {
            throw new RuntimeException($message);
        };

        // Admin should be able to assign to themselves
        expect(fn () => $rule->validate('assigned_to', $this->adminUser->id, $fail))->not->toThrow(Exception::class)
            ->and(fn () => $rule->validate('assigned_to', $this->regularUser->id, $fail))->not->toThrow(Exception::class)
            ->and(fn () => $rule->validate('assigned_to', $this->otherUser->id, $fail))->not->toThrow(Exception::class);

        // Admin should be able to assign to regular users
    });

    test('allows regular user to assign tasks to themselves only', function () {
        Sanctum::actingAs($this->regularUser);

        $rule = new ValidTaskAssignment;
        $fail = function ($message) {
            throw new RuntimeException($message);
        };

        // Regular user should be able to assign to themselves
        expect(fn () => $rule->validate('assigned_to', $this->regularUser->id, $fail))->not->toThrow(Exception::class);
    });

    test('prevents regular user from assigning tasks to others', function () {
        Sanctum::actingAs($this->regularUser);

        $rule = new ValidTaskAssignment;
        $failedMessage = null;
        $fail = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        // Regular user should NOT be able to assign to other users
        $rule->validate('assigned_to', $this->otherUser->id, $fail);
        expect($failedMessage)->toBe('You can only assign tasks to yourself.');

        // Reset and test admin user assignment
        $failedMessage = null;
        $rule->validate('assigned_to', $this->adminUser->id, $fail);
        expect($failedMessage)->toBe('You can only assign tasks to yourself.');
    });

    test('rejects assignment to non-existent users', function () {
        Sanctum::actingAs($this->adminUser);

        $rule = new ValidTaskAssignment;
        $failedMessage = null;
        $fail = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        $nonExistentUserIds = [999, 0, -1, 'abc', 999999];

        foreach ($nonExistentUserIds as $userId) {
            $failedMessage = null;
            $rule->validate('assigned_to', $userId, $fail);
            expect($failedMessage)->toBe('The selected assignee does not exist.');
        }
    });

    test('fails when user is not authenticated', function () {
        // No authenticated user
        auth()->logout();

        $rule = new ValidTaskAssignment;
        $failedMessage = null;
        $fail = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        $rule->validate('assigned_to', $this->regularUser->id, $fail);
        expect($failedMessage)->toBe('You must be authenticated to assign tasks.');
    });

    test('handles soft-deleted users correctly', function () {
        Sanctum::actingAs($this->adminUser);

        // Create and soft delete a user
        $deletedUser = User::factory()->regularUser()->create();
        $deletedUser->delete();

        $rule = new ValidTaskAssignment;
        $failedMessage = null;
        $fail = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        // Should fail because soft-deleted users don't "exist" in normal queries
        $rule->validate('assigned_to', $deletedUser->id, $fail);
        expect($failedMessage)->toBe('The selected assignee does not exist.');
    });

    test('manager user has same restrictions as regular user', function () {
        $managerUser = User::factory()->regularUser()->create();
        Sanctum::actingAs($managerUser);

        $rule = new ValidTaskAssignment;
        $fail = function ($message) {
            throw new RuntimeException($message);
        };

        // Manager should be able to assign to themselves
        expect(fn () => $rule->validate('assigned_to', $managerUser->id, $fail))->not->toThrow(Exception::class);

        // But NOT to others
        $failedMessage = null;
        $failFunction = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        $rule->validate('assigned_to', $this->regularUser->id, $failFunction);
        expect($failedMessage)->toBe('You can only assign tasks to yourself.');
    });
});

// Integration Tests with Form Requests
describe('Custom Validation Rules in Form Requests', function () {
    test('ValidDueDate rule works in task creation API', function () {
        Sanctum::actingAs($this->regularUser);

        // Valid future date should work
        $validData = [
            'title' => 'Valid Test Task Title',
            'description' => 'Test description',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'pending',
            'priority' => 'medium',
        ];

        $response = $this->postJson('/api/v1/tasks', $validData);
        $response->assertStatus(201);

        // Past date should fail
        $invalidData = $validData;
        $invalidData['due_date'] = now()->subDays(5)->format('Y-m-d');

        $response = $this->postJson('/api/v1/tasks', $invalidData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    });

    test('ValidTaskAssignment rule works in task creation API', function () {
        Sanctum::actingAs($this->regularUser);

        // Regular user assigning to self should work
        $validData = [
            'title' => 'Self Assigned Task Title',
            'description' => 'Test description',
            'due_date' => now()->addDays()->format('Y-m-d'),
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $this->regularUser->id,
        ];

        $response = $this->postJson('/api/v1/tasks', $validData);
        $response->assertStatus(201);

        // Regular user assigning to other should fail
        $invalidData = $validData;
        $invalidData['assigned_to'] = $this->otherUser->id;

        $response = $this->postJson('/api/v1/tasks', $invalidData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    });

    test('ValidTaskAssignment rule allows admin to assign to anyone', function () {
        Sanctum::actingAs($this->adminUser);

        // Admin assigning to regular user should work
        $validData = [
            'title' => 'Admin Assigned Task Title',
            'description' => 'Test description',
            'due_date' => now()->addDays()->format('Y-m-d'),
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $this->regularUser->id,
        ];

        $response = $this->postJson('/api/v1/tasks', $validData);
        $response->assertStatus(201);

        // Verify task was assigned correctly
        $task = Task::where('title', 'Admin Assigned Task Title')->first();
        expect($task->assigned_to)->toBe($this->regularUser->id);
    });

    test('ValidTaskAssignment rule prevents assignment to non-existent users', function () {
        Sanctum::actingAs($this->adminUser);

        $invalidData = [
            'title' => 'Invalid Assignment Task Title',
            'description' => 'Test description',
            'due_date' => now()->addDays()->format('Y-m-d'),
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => 999999, // Non-existent user
        ];

        $response = $this->postJson('/api/v1/tasks', $invalidData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    });

    test('both validation rules work together in complex scenarios', function () {
        Sanctum::actingAs($this->regularUser);

        // Multiple validation errors should be returned
        $invalidData = [
            'title' => 'Complex Validation Test Title',
            'description' => 'Test description',
            'due_date' => now()->subDays(5)->format('Y-m-d'), // Past date - invalid
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $this->otherUser->id, // Other user - invalid for regular user
        ];

        $response = $this->postJson('/api/v1/tasks', $invalidData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date', 'assigned_to']);
    });

    test('validation rules work correctly in task updates', function () {
        Sanctum::actingAs($this->regularUser);
        $task = Task::factory()->create([
            'user_id' => $this->regularUser->id,
            'assigned_to' => $this->regularUser->id,
            'version' => 1,
        ]);

        // Try to update with invalid data
        $invalidUpdateData = [
            'due_date' => now()->subDays(2)->format('Y-m-d'), // Past date
            'assigned_to' => $this->otherUser->id, // Other user
            'version' => 1,
        ];

        $response = $this->putJson("/api/v1/tasks/$task->id", $invalidUpdateData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date', 'assigned_to']);

        // Verify task was not updated
        $task->refresh();
        expect($task->assigned_to)->toBe($this->regularUser->id);
    });

});

// Performance Tests
test('custom validation rules perform efficiently', function () {
    Sanctum::actingAs($this->adminUser);

    $dueDateRule = new ValidDueDate;
    $assignmentRule = new ValidTaskAssignment;

    $fail = function ($message) {};

    // Test ValidDueDate performance
    $startTime = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $dueDateRule->validate('due_date', now()->addDays($i)->format('Y-m-d'), $fail);
    }
    $dueDateTime = microtime(true) - $startTime;

    // Test ValidTaskAssignment performance
    $startTime = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $assignmentRule->validate('assigned_to', $this->adminUser->id, $fail);
    }
    $assignmentTime = microtime(true) - $startTime;

    // Both should complete within reasonable time (less than 1 second each)
    expect($dueDateTime)->toBeLessThan(1.0)
        ->and($assignmentTime)->toBeLessThan(1.0);
});

test('validation rules handle concurrent requests correctly', function () {
    Sanctum::actingAs($this->adminUser);

    $rule = new ValidTaskAssignment;
    $fail = function ($message) {};

    // Simulate multiple concurrent validations
    $results = [];
    for ($i = 0; $i < 10; $i++) {
        $results[] = function () use ($rule, $fail) {
            $rule->validate('assigned_to', $this->adminUser->id, $fail);

            return true;
        };
    }

    // All validations should succeed
    foreach ($results as $validation) {
        expect($validation())->toBeTrue();
    }
});
