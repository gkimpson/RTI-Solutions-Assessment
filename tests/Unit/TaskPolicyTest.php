<?php

use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->managerUser = User::factory()->regularUser()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();
    $this->policy = new TaskPolicy;
});

test('admin can view any task', function () {
    expect($this->policy->viewAny())->toBeTrue();
});

test('manager can view any task', function () {
    expect($this->policy->viewAny())->toBeTrue();
});

test('regular user can view any task', function () {
    expect($this->policy->viewAny())->toBeTrue();
});

test('admin can view any specific task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->view($this->adminUser, $task))->toBeTrue();
});

test('assigned user can view their task', function () {
    $task = Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id]);
    expect($this->policy->view($this->regularUser, $task))->toBeTrue();
});

test('regular user cannot view another users unassigned task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id, 'assigned_to' => $this->otherUser->id]);
    expect($this->policy->view($this->regularUser, $task))->toBeFalse();
});

test('any authenticated user can create a task', function () {
    expect($this->policy->create())->toBeTrue()
        ->and($this->policy->create())->toBeTrue()
        ->and($this->policy->create())->toBeTrue();
});

test('admin can update any task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->update($this->adminUser, $task))->toBeTrue();
});

test('assigned user can update their task', function () {
    $task = Task::factory()->create(['user_id' => $this->regularUser->id, 'assigned_to' => $this->regularUser->id]);
    expect($this->policy->update($this->regularUser, $task))->toBeTrue();
});

test('regular user cannot update another users unassigned task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id, 'assigned_to' => $this->otherUser->id]);
    expect($this->policy->update($this->regularUser, $task))->toBeFalse();
});

test('admin can delete any task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->delete($this->adminUser, $task))->toBeTrue();
});

test('regular user cannot delete another users task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->delete($this->regularUser, $task))->toBeFalse();
});

test('admin can restore any task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->restore($this->adminUser, $task))->toBeTrue();
});

test('regular user cannot restore another users task', function () {
    $task = Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->restore($this->regularUser, $task))->toBeFalse();
});

test('admin can force delete any task', function () {
    Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->forceDelete($this->adminUser))->toBeTrue();
});

test('manager cannot force delete any task', function () {
    Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->forceDelete($this->managerUser))->toBeFalse();
});

test('regular user cannot force delete any task', function () {
    Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->forceDelete($this->regularUser))->toBeFalse();
});

test('admin can assign tasks', function () {
    Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->assign($this->adminUser))->toBeTrue();
});

test('regular user cannot assign tasks', function () {
    Task::factory()->create(['user_id' => $this->otherUser->id]);
    expect($this->policy->assign($this->regularUser))->toBeFalse();
});
