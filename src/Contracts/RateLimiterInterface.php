<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

/**
 * Interface for rate limiting failed authentication attempts.
 */
interface RateLimiterInterface
{
    /**
     * Check if a client is currently rate limited.
     */
    public function isLimited(string $clientId): bool;

    /**
     * Record a failed attempt for a client.
     */
    public function recordFailure(string $clientId): void;

    /**
     * Clear rate limit for a client (on successful auth).
     */
    public function reset(string $clientId): void;

    /**
     * Get the cache key for a client's rate limit.
     */
    public function getCacheKeyForClient(string $clientId): string;
}