<?php

declare(strict_types=1);

return [
    /**
     * Enable/disable HMAC authentication globally
     */
    'enabled' => env('HMAC_AUTH_ENABLED', true),

    /**
     * HMAC algorithm (sha256, sha384, sha512)
     */
    'algorithm' => env('HMAC_ALGORITHM', 'sha256'),

    /**
     * Prefix for generated client IDs (e.g., "myapp_live_xxx", "myapp_test_xxx")
     */
    'key_prefix' => env('HMAC_KEY_PREFIX', 'hmac'),

    /**
     * Timestamp tolerance in seconds (prevents replay attacks)
     * Default: 300 seconds (5 minutes)
     */
    'timestamp_tolerance' => env('HMAC_TIMESTAMP_TOLERANCE', 300),

    /**
     * Nonce TTL in seconds (stored in Redis)
     * Default: 600 seconds (10 minutes)
     */
    'nonce_ttl' => env('HMAC_NONCE_TTL', 600),

    /**
     * Client secret length (in bytes, will be base64url encoded)
     * Default: 48 bytes = 64 characters after encoding (384 bits entropy)
     */
    'secret_length' => env('HMAC_SECRET_LENGTH', 48),

    /**
     * Client ID random part length (in bytes, will be hex encoded)
     * Default: 16 bytes = 32 hex characters (128 bits entropy)
     */
    'client_id_length' => env('HMAC_CLIENT_ID_LENGTH', 16),

    /**
     * Maximum request body size in bytes
     * Default: 1MB
     */
    'max_body_size' => env('HMAC_MAX_BODY_SIZE', 1048576),

    /**
     * Minimum nonce length in characters
     * Default: 32 characters
     */
    'min_nonce_length' => env('HMAC_MIN_NONCE_LENGTH', 32),

    /**
     * Negative cache TTL in seconds for "not found" credentials
     * Default: 60 seconds
     */
    'negative_cache_ttl' => env('HMAC_NEGATIVE_CACHE_TTL', 60),

    /**
     * Enforce environment matching between credentials and application
     *
     * When enabled (default), credentials marked as 'production' can only be used
     * when APP_ENV=production, and 'testing' credentials can only be used in
     * non-production environments (local, staging, testing, etc.)
     *
     * Set to false to disable environment enforcement (not recommended for production)
     */
    'enforce_environment' => env('HMAC_ENFORCE_ENVIRONMENT', true),

    /**
     * Rate limiting configuration (per client ID)
     */
    'rate_limit' => [
        'enabled' => env('HMAC_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('HMAC_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('HMAC_RATE_LIMIT_DECAY', 1),
    ],

    /**
     * IP-based blocking configuration
     * Blocks IPs with excessive failed authentication attempts
     */
    'ip_blocking' => [
        'enabled' => env('HMAC_IP_BLOCKING_ENABLED', true),
        'threshold' => env('HMAC_IP_FAILURE_THRESHOLD', 10),
        'window_minutes' => env('HMAC_IP_FAILURE_WINDOW', 10),
    ],

    /**
     * Redis configuration for nonce storage
     */
    'redis' => [
        'connection' => env('HMAC_REDIS_CONNECTION', 'default'),
        'prefix' => env('HMAC_REDIS_PREFIX', 'hmac:'),
        'fail_on_error' => env('HMAC_REDIS_STRICT', false),
    ],

    /**
     * Required headers for HMAC authentication
     */
    'headers' => [
        'api-key' => 'X-Api-Key',
        'signature' => 'X-Signature',
        'timestamp' => 'X-Timestamp',
        'nonce' => 'X-Nonce',
    ],

    /**
     * Multi-tenancy configuration.
     *
     * When 'enabled' is false (default), the package operates in standalone mode
     * without tenant scoping. Credentials are not associated with any tenant.
     *
     * When 'enabled' is true, credentials are scoped to tenants using the
     * configured column name and model.
     */
    'tenancy' => [
        /**
         * Enable multi-tenancy support.
         * Default: false (standalone mode)
         */
        'enabled' => env('HMAC_TENANCY_ENABLED', false),

        /**
         * The database column name used for tenant foreign key.
         * This column will be added to api_credentials and api_request_logs tables.
         *
         * Common options: 'tenant_id', 'company_id', 'team_id', 'organization_id'
         * Default: 'tenant_id'
         */
        'column' => env('HMAC_TENANT_COLUMN', 'tenant_id'),

        /**
         * The fully qualified class name of your tenant model.
         * This model is used for the tenant relationship on credentials.
         *
         * Default: 'App\Models\Tenant'
         */
        'model' => env('HMAC_TENANT_MODEL', 'App\\Models\\Tenant'),
    ],

    /**
     * Model classes for relationships
     * Override these if your application uses different model classes
     */
    'models' => [
        'user' => env('HMAC_USER_MODEL', 'App\\Models\\User'),
    ],

    /**
     * Routes that require HMAC authentication
     * You can specify route patterns here for documentation purposes
     */
    'protected_routes' => [
        'api/*',
    ],
];
