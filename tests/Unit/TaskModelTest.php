<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

test('a task can be assigned to a user', function () {
    $task = Task::factory()->create(['user_id' => $this->user->id, 'assigned_to' => $this->otherUser->id]);
    expect($task->assignedUser)->toBeInstanceOf(User::class)
        ->and($task->assignedUser->id)->toBe($this->otherUser->id);
});

test('soft delete sets deleted at timestamp', function () {
    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $task->delete();

    expect($task->deleted_at)->not->toBeNull();
    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

test('restore clears deleted at timestamp', function () {
    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $task->delete();
    $task->restore();

    expect($task->deleted_at)->toBeNull();
    $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
});

test('force delete removes task from database', function () {
    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $task->forceDelete();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});
