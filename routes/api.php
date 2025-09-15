<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// API v1 routes
Route::prefix('v1')->group(function (): void {
    // Public authentication routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function (): void {
        // Authentication routes
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Resource routes
        Route::apiResource('tasks', TaskController::class);
        Route::apiResource('tags', TagController::class);

        // Advanced task operations
        Route::patch('tasks/{task}/toggle-status', [TaskController::class, 'toggleStatus']);
        Route::patch('tasks/{task}/restore', [TaskController::class, 'restore']);
        Route::post('tasks/bulk', [TaskController::class, 'bulk']);

        // User management (Admin only)
        Route::get('users/{user}/role', [UserController::class, 'getRole']);
        Route::patch('users/{user}/role', [UserController::class, 'updateRole']);
    });
});
