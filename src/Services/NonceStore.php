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

        $redis = $this->redis;
        assert($redis !== null);

        return $this->executeRedisOperation(
            operation: function () use ($key, $redis): bool {
                /** @var int $result */
                $result = $redis->command('exists', [$key]);

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

        $redis = $this->redis;
        assert($redis !== null);

        $key = $this->getKey($nonce);
        $ttl = $this->config->nonceTtl;

        $this->executeRedisOperation(
            operation: fn (): bool => (bool) $redis->command('setex', [$key, $ttl, '1']),
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

        $redis = $this->redis;
        assert($redis !== null);

        $pattern = $this->prefix.'*';
        $redisPrefix = $this->config->databaseRedisPrefix;
        $cursor = '0';
        $batchSize = 100;
        $maxIterations = 1000;
        $iterations = 0;

        do {
            /** @var array{0: string, 1: array<string>} $result */
            $result = $redis->command('scan', [$cursor, 'MATCH', $pattern, 'COUNT', $batchSize]);
            $cursor = $result[0];
            $keys = $result[1];

            if ($keys !== []) {
                $shortKeys = array_map(
                    fn (string $fullKey): string => $redisPrefix !== '' && str_starts_with($fullKey, $redisPrefix)
                        ? substr($fullKey, strlen($redisPrefix))
                        : $fullKey,
                    $keys
                );

                $redis->command('del', $shortKeys);
            }

            $iterations++;
        } while ($cursor !== '0' && $iterations < $maxIterations);
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
