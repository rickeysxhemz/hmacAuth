<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * Generates consistent, secure cache keys.
 */
trait GeneratesCacheKeys
{
    abstract protected function getCachePrefix(): string;

    protected function getCacheKey(string $type, string $identifier): string
    {
        return sprintf('%s:%s:%s', $this->getCachePrefix(), $type, $identifier);
    }

    protected function getLockKey(string $identifier): string
    {
        return $this->getCacheKey('lock', $identifier);
    }

    /**
     * Hash identifier to prevent cache key injection attacks.
     */
    protected function hashIdentifier(string $identifier): string
    {
        return hash('xxh3', $identifier);
    }
}
