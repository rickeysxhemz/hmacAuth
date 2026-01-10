<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Concerns\RedisStoreConcerns;
use HmacAuth\Contracts\NonceStoreInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Exceptions\NonceValidationException;
use Illuminate\Redis\Connections\Connection;
use RuntimeException;
use Throwable;

final readonly class NonceStore implements NonceStoreInterface
{
    use RedisStoreConcerns;

    private string $prefix;

    public function __construct(
        private ?Connection $redis,
        private HmacConfig $config,
    ) {
        $this->prefix = $this->config->redisPrefix.'nonce:';
    }

    public function exists(string $nonce): bool
    {
        if ($this->isTestingMode()) {
            return false;
        }

        $key = $this->getKey($nonce);

        return $this->executeRedisOperation(
            operation: function () use ($key): bool {
                /** @var int $result */
                $result = $this->redis->command('exists', [$key]);

                return $result > 0;
            },
            default: false,
            context: 'NonceStore::exists',
            logData: ['nonce_prefix' => $this->maskSensitiveValue($nonce)],
            exceptionClass: NonceValidationException::class,
            exceptionMessage: 'Nonce validation unavailable'
        );
    }

    public function store(string $nonce): void
    {
        if ($this->isTestingMode()) {
            return;
        }

        $this->executeRedisOperation(
            operation: fn (): bool => (bool) $this->redis->command('setex', [$this->getKey($nonce), $this->config->nonceTtl, '1']),
            default: false,
            context: 'NonceStore::store',
            logData: ['nonce_prefix' => $this->maskSensitiveValue($nonce)],
            exceptionClass: NonceValidationException::class,
            exceptionMessage: 'Nonce storage unavailable'
        );
    }

    /** @throws RuntimeException|Throwable if called in production */
    public function clear(): void
    {
        if ($this->config->isProduction()) {
            throw new RuntimeException('NonceStore::clear() cannot be called in production');
        }

        if ($this->isTestingMode()) {
            return;
        }

        $pattern = $this->prefix.'*';

        /** @var array<string> $keys */
        $keys = $this->redis->command('keys', [$pattern]);

        if ($keys === []) {
            return;
        }

        $redisPrefix = $this->config->databaseRedisPrefix;

        foreach ($keys as $fullKey) {
            $shortKey = $redisPrefix !== '' && str_starts_with($fullKey, $redisPrefix)
                ? substr($fullKey, strlen($redisPrefix))
                : $fullKey;
            $this->redis->command('del', [$shortKey]);
        }
    }

    protected function shouldFailOnRedisError(): bool
    {
        return $this->config->failOnRedisError;
    }

    private function isTestingMode(): bool
    {
        return $this->redis === null;
    }

    private function getKey(string $nonce): string
    {
        return $this->prefix.hash('xxh3', $nonce);
    }
}
