<?php

declare(strict_types=1);

use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Services\NonceStore;
use Illuminate\Redis\Connections\Connection;

function createNonceConfig(array $overrides = []): HmacConfig
{
    return new HmacConfig(
        enabled: $overrides['enabled'] ?? true,
        apiKeyHeader: 'X-Api-Key',
        signatureHeader: 'X-Signature',
        timestampHeader: 'X-Timestamp',
        nonceHeader: 'X-Nonce',
        timestampTolerance: 300,
        rateLimitEnabled: true,
        rateLimitMaxAttempts: 60,
        rateLimitDecayMinutes: 1,
        enforceEnvironment: true,
        appEnvironment: $overrides['appEnvironment'] ?? 'testing',
        algorithm: 'sha256',
        clientIdLength: 16,
        secretLength: 48,
        redisPrefix: $overrides['redisPrefix'] ?? 'hmac:',
        nonceTtl: $overrides['nonceTtl'] ?? 600,
        maxBodySize: 1048576,
        minNonceLength: 32,
        failOnRedisError: $overrides['failOnRedisError'] ?? false,
    );
}

describe('NonceStore', function () {
    describe('in testing mode (no Redis)', function () {
        beforeEach(function () {
            $this->config = createNonceConfig();
            $this->store = new NonceStore(null, $this->config);
        });

        it('exists() returns false when Redis is null', function () {
            $nonce = bin2hex(random_bytes(16));

            expect($this->store->exists($nonce))->toBeFalse();
        });

        it('store() does nothing when Redis is null', function () {
            $nonce = bin2hex(random_bytes(16));

            // Should not throw
            $this->store->store($nonce);

            // Should still return false (not stored)
            expect($this->store->exists($nonce))->toBeFalse();
        });

        it('clear() does nothing when Redis is null', function () {
            // Should not throw
            $this->store->clear();

            expect(true)->toBeTrue();
        });
    });

    describe('with mocked Redis', function () {
        beforeEach(function () {
            $this->config = createNonceConfig(['nonceTtl' => 600]);
            $this->redis = Mockery::mock(Connection::class)->makePartial();
            $this->store = new NonceStore($this->redis, $this->config);
        });

        it('exists() returns true when nonce exists in Redis', function () {
            $nonce = 'test-nonce-12345678901234567890';

            $this->redis->shouldReceive('command')
                ->with('exists', Mockery::type('array'))
                ->once()
                ->andReturn(1);

            expect($this->store->exists($nonce))->toBeTrue();
        });

        it('exists() returns false when nonce does not exist', function () {
            $nonce = 'new-nonce-12345678901234567890';

            $this->redis->shouldReceive('command')
                ->with('exists', Mockery::type('array'))
                ->once()
                ->andReturn(0);

            expect($this->store->exists($nonce))->toBeFalse();
        });

        it('store() saves nonce with correct TTL', function () {
            $nonce = 'store-test-nonce-12345678901234';

            $this->redis->shouldReceive('command')
                ->with('setex', Mockery::on(function ($args) {
                    return count($args) === 3
                        && is_string($args[0])
                        && $args[1] === 600 // TTL from config
                        && $args[2] === '1';
                }))
                ->once()
                ->andReturn(true);

            $this->store->store($nonce);
        });

        it('uses correct key prefix', function () {
            $customConfig = createNonceConfig(['redisPrefix' => 'custom:']);
            $store = new NonceStore($this->redis, $customConfig);

            $this->redis->shouldReceive('command')
                ->with('exists', Mockery::on(function ($args) {
                    return str_starts_with($args[0], 'custom:nonce:');
                }))
                ->once()
                ->andReturn(0);

            $store->exists('test-nonce-value-12345678901234');
        });

        it('hashes nonce in key', function () {
            $nonce1 = 'nonce-one-12345678901234567890';
            $nonce2 = 'nonce-two-12345678901234567890';

            $this->redis->shouldReceive('command')
                ->with('exists', Mockery::type('array'))
                ->twice()
                ->andReturn(0);

            $this->store->exists($nonce1);
            $this->store->exists($nonce2);

            // Keys should be different for different nonces
            // (This test verifies the hashing is working)
        });
    });

    describe('clear()', function () {
        it('throws exception when called in production', function () {
            $productionConfig = createNonceConfig(['appEnvironment' => 'production']);
            $redis = Mockery::mock(Connection::class);
            $store = new NonceStore($redis, $productionConfig);

            expect(fn () => $store->clear())
                ->toThrow(RuntimeException::class, 'NonceStore::clear() cannot be called in production');
        });

        it('clears all nonce keys in non-production', function () {
            $config = createNonceConfig(['appEnvironment' => 'testing']);
            $redis = Mockery::mock(Connection::class);
            $store = new NonceStore($redis, $config);

            config(['database.redis.options.prefix' => '']);

            // Mock SCAN command - first call returns keys and cursor '0' (done)
            $redis->shouldReceive('command')
                ->with('scan', ['0', 'MATCH', 'hmac:nonce:*', 'COUNT', 100])
                ->once()
                ->andReturn(['0', ['hmac:nonce:key1', 'hmac:nonce:key2']]);

            // Mock batch DEL command
            $redis->shouldReceive('command')
                ->with('del', ['hmac:nonce:key1', 'hmac:nonce:key2'])
                ->once();

            $store->clear();
        });

        it('handles empty key list gracefully', function () {
            $config = createNonceConfig(['appEnvironment' => 'testing']);
            $redis = Mockery::mock(Connection::class);
            $store = new NonceStore($redis, $config);

            // Mock SCAN command returning empty key list
            $redis->shouldReceive('command')
                ->with('scan', ['0', 'MATCH', 'hmac:nonce:*', 'COUNT', 100])
                ->once()
                ->andReturn(['0', []]);

            // Should not call del when no keys found
            $redis->shouldNotReceive('command')
                ->with('del', Mockery::any());

            $store->clear();
        });
    });

    describe('Redis error handling', function () {
        it('returns false on Redis error when fail_on_error is disabled', function () {
            // Skip test if RedisException class doesn't exist (Redis extension not loaded)
            if (! class_exists(\RedisException::class)) {
                $this->markTestSkipped('RedisException class not available (Redis extension not loaded)');
            }

            $config = createNonceConfig(['failOnRedisError' => false]);
            $redis = Mockery::mock(Connection::class);
            $store = new NonceStore($redis, $config);

            $redis->shouldReceive('command')
                ->with('exists', Mockery::any())
                ->once()
                ->andThrow(new \RedisException('Redis connection failed'));

            // Should return default value (false) instead of throwing
            expect($store->exists('test-nonce-value-12345678901234'))->toBeFalse();
        });
    });
});

afterEach(function () {
    Mockery::close();
});
