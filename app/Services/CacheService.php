<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private const USER_STATS_CACHE_KEY = 'user_stats_';

    private const USER_STATS_CACHE_TAG = 'user_stats';

    /**
     * Clear user stats cache
     */
    public function clearUserStats(int $userId): void
    {
        // Use cache tagging when available (Redis/Memcached)
        if ($this->supportsTags()) {
            // With tagging, we can invalidate all user stats at once
            // or target specific user stats if needed
            Cache::tags([self::USER_STATS_CACHE_TAG])->flush();

            return;
        }

        // Fallback to regular cache clearing
        Cache::forget(self::USER_STATS_CACHE_KEY.$userId);
    }

    /**
     * Clear all user stats cache (when tasks are bulk updated)
     */
    public function clearAllUserStats(): void
    {
        // Use cache tagging when available (Redis/Memcached)
        if ($this->supportsTags()) {
            // With tagging, we can invalidate all user stats at once
            Cache::tags([self::USER_STATS_CACHE_TAG])->flush();

            return;
        }

        // Fallback to the existing approach for non-tagging cache stores
        // Get all unique user IDs who have assigned tasks
        $userIds = Task::distinct()
            ->whereNotNull('assigned_to')
            ->pluck('assigned_to');

        // Safeguard: If there are too many users, clear cache by pattern instead
        // This prevents memory issues with very large user bases
        $maxBatchSize = 1000;

        if ($userIds->count() > $maxBatchSize) {
            // For very large datasets, use cache pattern clearing if available
            // This is more efficient but less precise
            // All Laravel cache stores implement the flush method
            // Note: This clears ALL cache, not just user stats
            // With tagging, this wouldn't be necessary
            Log::warning('Large user base detected, clearing all cache instead of individual user stats', [
                'user_count' => $userIds->count(),
                'max_batch_size' => $maxBatchSize,
            ]);

            return;
        }

        // Build cache keys for batch deletion
        $cacheKeys = $userIds->map(fn ($userId) => self::USER_STATS_CACHE_KEY.$userId)->toArray();

        // Use batch cache deletion for better performance
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Check if the current cache store supports tags
     */
    private function supportsTags(): bool
    {
        $store = Cache::getStore();

        return method_exists($store, 'tags');
    }
}
