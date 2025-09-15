<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->managerUser = User::factory()->regularUser()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();
});
