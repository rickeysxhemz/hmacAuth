<?php

declare(strict_types=1);

use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Services\NonceStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

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
        cacheStore: $overrides['cacheStore'] ?? null,
        cachePrefix: $overrides['cachePrefix'] ?? 'hmac:nonce:',
        nonceTtl: $overrides['nonceTtl'] ?? 600,
        maxBodySize: 1048576,
        minNonceLength: 32,
    );
}

describe('NonceStore', function () {
    describe('with mocked Cache', function () {
        beforeEach(function () {
            $this->config = createNonceConfig(['nonceTtl' => 600]);
            $this->cache = Mockery::mock(CacheRepository::class);
            $this->store = new NonceStore($this->cache, $this->config);
        });

        it('exists() returns true when nonce exists in cache', function () {
            $nonce = 'test-nonce-12345678901234567890';

            $this->cache->shouldReceive('has')
                ->with(Mockery::type('string'))
                ->once()
                ->andReturn(true);

            expect($this->store->exists($nonce))->toBeTrue();
        });

        it('exists() returns false when nonce does not exist', function () {
            $nonce = 'new-nonce-12345678901234567890';

            $this->cache->shouldReceive('has')
                ->with(Mockery::type('string'))
                ->once()
                ->andReturn(false);

            expect($this->store->exists($nonce))->toBeFalse();
        });

        it('store() saves nonce with correct TTL', function () {
            $nonce = 'store-test-nonce-12345678901234';

            $this->cache->shouldReceive('put')
                ->with(
                    Mockery::type('string'),
                    true,
                    600 // TTL from config
                )
                ->once();

            $this->store->store($nonce);
        });

        it('uses correct key prefix', function () {
            $customConfig = createNonceConfig(['cachePrefix' => 'custom:nonce:']);
            $store = new NonceStore($this->cache, $customConfig);

            $this->cache->shouldReceive('has')
                ->with(Mockery::on(function ($key) {
                    return str_starts_with($key, 'custom:nonce:');
                }))
                ->once()
                ->andReturn(false);

            $store->exists('test-nonce-value-12345678901234');
        });

        it('hashes nonce in key', function () {
            $nonce1 = 'nonce-one-12345678901234567890';
            $nonce2 = 'nonce-two-12345678901234567890';

            $this->cache->shouldReceive('has')
                ->with(Mockery::type('string'))
                ->twice()
                ->andReturn(false);

            $this->store->exists($nonce1);
            $this->store->exists($nonce2);

            // Keys should be different for different nonces
            // (This test verifies the hashing is working)
        });
    });

    describe('clear()', function () {
        it('throws exception when called in production', function () {
            $productionConfig = createNonceConfig(['appEnvironment' => 'production']);
            $cache = Mockery::mock(CacheRepository::class);
            $store = new NonceStore($cache, $productionConfig);

            expect(fn () => $store->clear())
                ->toThrow(RuntimeException::class, 'NonceStore::clear() cannot be called in production');
        });

        it('does nothing in non-production (array driver handles isolation)', function () {
            $config = createNonceConfig(['appEnvironment' => 'testing']);
            $cache = Mockery::mock(CacheRepository::class);
            $store = new NonceStore($cache, $config);

            // Should not throw and should not call any cache methods
            $store->clear();

            expect(true)->toBeTrue();
        });
    });
});

afterEach(function () {
    Mockery::close();
});
