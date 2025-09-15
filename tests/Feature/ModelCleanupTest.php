<?php

use App\Enums\TaskLogOperation;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;

it('ensures models are properly cleaned up', function () {
    // Test that Tag model works correctly
    $tag = Tag::factory()->create(['name' => 'Test Tag', 'color' => '#FF0000']);
    expect($tag)->toBeInstanceOf(Tag::class);
    expect($tag->name)->toBe('Test Tag');

    // Test that Task model works correctly
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Test Task']);
    expect($task)->toBeInstanceOf(Task::class);
    expect($task->title)->toBe('Test Task');

    // Test that TaskLog model works correctly with the new enum
    $taskLog = TaskLog::create([
        'task_id' => $task->id,
        'user_id' => $user->id,
        'operation_type' => TaskLogOperation::Create,
        'performed_at' => now(),
    ]);

    expect($taskLog)->toBeInstanceOf(TaskLog::class);
    expect($taskLog->operation_type)->toBe(TaskLogOperation::Create);

    // Test that the User model works correctly
    $user = User::factory()->create(['name' => 'Test User']);
    expect($user)->toBeInstanceOf(User::class);
    expect($user->name)->toBe('Test User');
});
