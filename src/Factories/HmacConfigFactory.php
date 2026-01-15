<?php

declare(strict_types=1);

namespace HmacAuth\Factories;

use HmacAuth\Contracts\HmacConfigFactoryInterface;
use HmacAuth\DTOs\HmacConfig;
use Illuminate\Contracts\Config\Repository;

/**
 * Factory for creating HmacConfig instances from Laravel config.
 */
final readonly class HmacConfigFactory implements HmacConfigFactoryInterface
{
    public function __construct(
        private Repository $config,
    ) {}

    public function create(): HmacConfig
    {
        return new HmacConfig(
            enabled: $this->bool('hmac.enabled', true),
            apiKeyHeader: $this->string('hmac.headers.api-key', 'X-Api-Key'),
            signatureHeader: $this->string('hmac.headers.signature', 'X-Signature'),
            timestampHeader: $this->string('hmac.headers.timestamp', 'X-Timestamp'),
            nonceHeader: $this->string('hmac.headers.nonce', 'X-Nonce'),
            timestampTolerance: $this->int('hmac.timestamp_tolerance', 300),
            rateLimitEnabled: $this->bool('hmac.rate_limit.enabled', true),
            rateLimitMaxAttempts: $this->int('hmac.rate_limit.max_attempts', 60),
            rateLimitDecayMinutes: $this->int('hmac.rate_limit.decay_minutes', 1),
            enforceEnvironment: $this->bool('hmac.enforce_environment', true),
            appEnvironment: $this->string('app.env', 'local'),
            algorithm: $this->string('hmac.algorithm', 'sha256'),
            clientIdLength: $this->int('hmac.client_id_length', 16),
            secretLength: $this->int('hmac.secret_length', 48),
            redisPrefix: $this->string('hmac.redis.prefix', 'hmac:'),
            nonceTtl: $this->int('hmac.nonce_ttl', 600),
            maxBodySize: $this->int('hmac.max_body_size', 1048576),
            minNonceLength: $this->int('hmac.min_nonce_length', 32),
            tenancyEnabled: $this->bool('hmac.tenancy.enabled', false),
            tenancyColumn: $this->string('hmac.tenancy.column', 'tenant_id'),
            tenancyModel: $this->string('hmac.tenancy.model', 'App\\Models\\Tenant'),
            databaseRedisPrefix: $this->string('database.redis.options.prefix', ''),
            failOnRedisError: $this->bool('hmac.redis.fail_on_error', false),
            negativeCacheTtl: $this->int('hmac.negative_cache_ttl', 60),
            ipBlockingEnabled: $this->bool('hmac.ip_blocking.enabled', true),
            ipBlockingThreshold: $this->int('hmac.ip_blocking.threshold', 10),
            ipBlockingWindowMinutes: $this->int('hmac.ip_blocking.window_minutes', 10),
        );
    }

    private function string(string $key, string $default): string
    {
        $value = $this->config->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->config->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function bool(string $key, bool $default): bool
    {
        $value = $this->config->get($key, $default);

        return is_bool($value) ? $value : $default;
    }
}
