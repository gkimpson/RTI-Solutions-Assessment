<?php

use App\Models\User;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create();
    $this->regularUser = User::factory()->regularUser()->create();
    $this->otherUser = User::factory()->regularUser()->create();
    $this->cacheService = app(CacheService::class);

    // Clear all cache before each test
    Cache::flush();
});
