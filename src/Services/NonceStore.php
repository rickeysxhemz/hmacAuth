<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Contracts\NonceStoreInterface;
use HmacAuth\DTOs\HmacConfig;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

final readonly class NonceStore implements NonceStoreInterface
{
    public function __construct(
        private CacheRepository $cache,
        private HmacConfig $config,
    ) {}

    public function exists(string $nonce): bool
    {
        return $this->cache->has($this->getKey($nonce));
    }

    public function store(string $nonce): void
    {
        $this->cache->put(
            $this->getKey($nonce),
            true,
            $this->config->nonceTtl
        );
    }

    public function clear(): void
    {
        if ($this->config->isProduction()) {
            throw new RuntimeException('NonceStore::clear() cannot be called in production');
        }

        // For array driver, cache is request-scoped anyway
        // For other drivers in testing, we skip clearing (test isolation handled by driver)
    }

    private function getKey(string $nonce): string
    {
        return $this->config->cachePrefix.hash('xxh3', $nonce);
    }
}
