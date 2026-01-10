<?php

declare(strict_types=1);

namespace HmacAuth\DTOs;

use InvalidArgumentException;

/**
 * Immutable value object for HMAC configuration.
 */
final readonly class HmacConfig
{
    public function __construct(
        public bool $enabled,
        public string $apiKeyHeader,
        public string $signatureHeader,
        public string $timestampHeader,
        public string $nonceHeader,
        public int $timestampTolerance,
        public bool $rateLimitEnabled,
        public int $rateLimitMaxAttempts,
        public int $rateLimitDecayMinutes,
        public bool $enforceEnvironment,
        public string $appEnvironment,
        public string $algorithm,
        public int $clientIdLength,
        public int $secretLength,
        public string $redisPrefix,
        public int $nonceTtl,
        public int $maxBodySize,
        public int $minNonceLength,
        public bool $tenancyEnabled = false,
        public string $tenancyColumn = 'tenant_id',
        public string $tenancyModel = 'App\\Models\\Tenant',
        public string $databaseRedisPrefix = '',
        public bool $failOnRedisError = false,
        public int $negativeCacheTtl = 60,
        public bool $ipBlockingEnabled = true,
        public int $ipBlockingThreshold = 10,
        public int $ipBlockingWindowMinutes = 10,
    ) {
        if ($timestampTolerance <= 0) {
            throw new InvalidArgumentException('Timestamp tolerance must be positive');
        }
        if ($maxBodySize <= 0) {
            throw new InvalidArgumentException('Max body size must be positive');
        }
        if ($minNonceLength < 16) {
            throw new InvalidArgumentException('Min nonce length must be at least 16');
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            enabled: self::bool('hmac.enabled', true),
            apiKeyHeader: self::string('hmac.headers.api-key', 'X-Api-Key'),
            signatureHeader: self::string('hmac.headers.signature', 'X-Signature'),
            timestampHeader: self::string('hmac.headers.timestamp', 'X-Timestamp'),
            nonceHeader: self::string('hmac.headers.nonce', 'X-Nonce'),
            timestampTolerance: self::int('hmac.timestamp_tolerance', 300),
            rateLimitEnabled: self::bool('hmac.rate_limit.enabled', true),
            rateLimitMaxAttempts: self::int('hmac.rate_limit.max_attempts', 60),
            rateLimitDecayMinutes: self::int('hmac.rate_limit.decay_minutes', 1),
            enforceEnvironment: self::bool('hmac.enforce_environment', true),
            appEnvironment: self::string('app.env', 'local'),
            algorithm: self::string('hmac.algorithm', 'sha256'),
            clientIdLength: self::int('hmac.client_id_length', 16),
            secretLength: self::int('hmac.secret_length', 48),
            redisPrefix: self::string('hmac.redis.prefix', 'hmac:'),
            nonceTtl: self::int('hmac.nonce_ttl', 600),
            maxBodySize: self::int('hmac.max_body_size', 1048576),
            minNonceLength: self::int('hmac.min_nonce_length', 32),
            tenancyEnabled: self::bool('hmac.tenancy.enabled', false),
            tenancyColumn: self::string('hmac.tenancy.column', 'tenant_id'),
            tenancyModel: self::string('hmac.tenancy.model', 'App\\Models\\Tenant'),
            databaseRedisPrefix: self::string('database.redis.options.prefix', ''),
            failOnRedisError: self::bool('hmac.redis.fail_on_error', false),
            negativeCacheTtl: self::int('hmac.negative_cache_ttl', 60),
            ipBlockingEnabled: self::bool('hmac.ip_blocking.enabled', true),
            ipBlockingThreshold: self::int('hmac.ip_blocking.threshold', 10),
            ipBlockingWindowMinutes: self::int('hmac.ip_blocking.window_minutes', 10),
        );
    }

    private static function string(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    private static function int(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private static function bool(string $key, bool $default): bool
    {
        $value = config($key, $default);

        return is_bool($value) ? $value : $default;
    }

    public function isProduction(): bool
    {
        return $this->appEnvironment === 'production';
    }
}
