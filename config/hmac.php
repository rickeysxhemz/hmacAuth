<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | HMAC Authentication
    |--------------------------------------------------------------------------
    |
    | Enable or disable HMAC authentication globally.
    |
    */

    'enabled' => env('HMAC_AUTH_ENABLED', true),

    'algorithm' => env('HMAC_ALGORITHM', 'sha256'),

    'key_prefix' => env('HMAC_KEY_PREFIX', 'hmac'),

    /*
    |--------------------------------------------------------------------------
    | Timing Configuration
    |--------------------------------------------------------------------------
    |
    | timestamp_tolerance: Max age of requests in seconds (default: 5 min)
    | nonce_ttl: How long nonces are stored (should be >= 2x tolerance)
    |
    */

    'timestamp_tolerance' => env('HMAC_TIMESTAMP_TOLERANCE', 300),

    'nonce_ttl' => env('HMAC_NONCE_TTL', 600),

    'min_nonce_length' => env('HMAC_MIN_NONCE_LENGTH', 32),

    'negative_cache_ttl' => env('HMAC_NEGATIVE_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Request Limits
    |--------------------------------------------------------------------------
    */

    'max_body_size' => env('HMAC_MAX_BODY_SIZE', 1048576),

    'secret_length' => env('HMAC_SECRET_LENGTH', 48),

    'client_id_length' => env('HMAC_CLIENT_ID_LENGTH', 16),

    /*
    |--------------------------------------------------------------------------
    | Environment Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, production credentials only work when APP_ENV=production.
    |
    */

    'enforce_environment' => env('HMAC_ENFORCE_ENVIRONMENT', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'enabled' => env('HMAC_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('HMAC_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('HMAC_RATE_LIMIT_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Blocking
    |--------------------------------------------------------------------------
    */

    'ip_blocking' => [
        'enabled' => env('HMAC_IP_BLOCKING_ENABLED', true),
        'threshold' => env('HMAC_IP_FAILURE_THRESHOLD', 10),
        'window_minutes' => env('HMAC_IP_FAILURE_WINDOW', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the cache store used for nonce storage.
    | Set 'store' to null to use Laravel's default cache store.
    |
    */

    'cache' => [
        'store' => env('HMAC_CACHE_STORE', null),
        'prefix' => env('HMAC_CACHE_PREFIX', 'hmac:nonce:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        'api-key' => 'X-Api-Key',
        'signature' => 'X-Signature',
        'timestamp' => 'X-Timestamp',
        'nonce' => 'X-Nonce',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    */

    'tenancy' => [
        'enabled' => env('HMAC_TENANCY_ENABLED', false),
        'column' => env('HMAC_TENANT_COLUMN', 'tenant_id'),
        'model' => env('HMAC_TENANT_MODEL', 'App\\Models\\Tenant'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */

    'models' => [
        'user' => env('HMAC_USER_MODEL', 'App\\Models\\User'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    'protected_routes' => [
        'api/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain API request logs. Use Laravel's model:prune
    | command to automatically clean up old logs: php artisan model:prune
    |
    */

    'log_retention_days' => env('HMAC_LOG_RETENTION_DAYS', 30),

];
