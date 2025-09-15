<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for performance optimizations
    | in the RTI Solutions application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Query Result Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for expensive database queries.
    | Cache TTL is in seconds.
    |
    */

    'cache' => [
        'enabled' => env('CACHE_QUERIES', false),
        'ttl' => [
            'task_index' => env('CACHE_TTL_TASK_INDEX', 300), // 5 minutes
            'tag_list' => env('CACHE_TTL_TAG_LIST', 600), // 10 minutes
            'user_stats' => env('CACHE_TTL_USER_STATS', 900), // 15 minutes
            'filter_results' => env('CACHE_TTL_FILTER_RESULTS', 300), // 5 minutes
        ],
        'prefix' => 'rti_perf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk Operations
    |--------------------------------------------------------------------------
    |
    | Configure bulk operation performance settings.
    |
    */

    'bulk_operations' => [
        'chunk_size' => env('BULK_CHUNK_SIZE', 100),
        'max_operations' => env('BULK_MAX_OPERATIONS', 1000),
        'transaction_chunk_size' => env('BULK_TRANSACTION_CHUNK_SIZE', 50),
        'memory_limit_mb' => env('BULK_MEMORY_LIMIT_MB', 128),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Query Optimization
    |--------------------------------------------------------------------------
    |
    | Settings for database query performance.
    |
    */

    'database' => [
        'eager_load_relations' => env('DB_EAGER_LOAD_RELATIONS', true),
        'use_indexes' => env('DB_USE_INDEXES', true),
        'fulltext_search_min_length' => env('DB_FULLTEXT_MIN_LENGTH', 3),
        'query_log_slow_threshold' => env('DB_LOG_SLOW_QUERIES', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | API Response Optimization
    |--------------------------------------------------------------------------
    |
    | Configure API response performance settings.
    |
    */

    'api' => [
        'max_per_page' => env('API_MAX_PER_PAGE', 100),
        'default_per_page' => env('API_DEFAULT_PER_PAGE', 15),
        'response_compression' => env('API_RESPONSE_COMPRESSION', true),
        'etag_enabled' => env('API_ETAG_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Metrics
    |--------------------------------------------------------------------------
    |
    | Performance monitoring configuration.
    |
    */

    'monitoring' => [
        'enabled' => env('PERFORMANCE_MONITORING', false),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'memory_usage_tracking' => env('MEMORY_USAGE_TRACKING', false),
        'cache_hit_ratio_tracking' => env('CACHE_HIT_RATIO_TRACKING', true),
    ],

];
