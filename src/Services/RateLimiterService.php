<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Concerns\GeneratesCacheKeys;
use HmacAuth\Contracts\RateLimiterInterface;
use HmacAuth\DTOs\HmacConfig;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Service for rate limiting failed authentication attempts.
 */
final readonly class RateLimiterService implements RateLimiterInterface
{
    use GeneratesCacheKeys;

    public function __construct(
        private CacheRepository $cache,
        private HmacConfig $config,
    ) {}

    protected function getCachePrefix(): string
    {
        return 'hmac_rate_limit';
    }

    public function isLimited(string $clientId): bool
    {
        if (! $this->config->rateLimitEnabled) {
            return false;
        }

        $key = $this->getCacheKey('attempts', $this->hashIdentifier($clientId));
        $cached = $this->cache->get($key, 0);
        $attempts = is_numeric($cached) ? (int) $cached : 0;

        return $attempts >= $this->config->rateLimitMaxAttempts;
    }

    public function recordFailure(string $clientId): void
    {
        if (! $this->config->rateLimitEnabled) {
            return;
        }

        $key = $this->getCacheKey('attempts', $this->hashIdentifier($clientId));
        $seconds = $this->config->rateLimitDecayMinutes * 60;

        if (! $this->cache->add($key, 1, now()->addSeconds($seconds))) {
            $this->cache->increment($key);
        }
    }

    public function reset(string $clientId): void
    {
        if (! $this->config->rateLimitEnabled) {
            return;
        }

        $key = $this->getCacheKey('attempts', $this->hashIdentifier($clientId));
        $this->cache->forget($key);
    }

    public function getCacheKeyForClient(string $clientId): string
    {
        return $this->getCacheKey('attempts', $this->hashIdentifier($clientId));
    }
}
