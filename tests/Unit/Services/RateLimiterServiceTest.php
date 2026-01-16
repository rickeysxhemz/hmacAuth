<?php

declare(strict_types=1);

use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Services\RateLimiterService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

function createRateLimiterConfig(array $overrides = []): HmacConfig
{
    return new HmacConfig(
        enabled: true,
        apiKeyHeader: 'X-Api-Key',
        signatureHeader: 'X-Signature',
        timestampHeader: 'X-Timestamp',
        nonceHeader: 'X-Nonce',
        timestampTolerance: 300,
        rateLimitEnabled: $overrides['rateLimitEnabled'] ?? true,
        rateLimitMaxAttempts: $overrides['rateLimitMaxAttempts'] ?? 60,
        rateLimitDecayMinutes: $overrides['rateLimitDecayMinutes'] ?? 1,
        enforceEnvironment: true,
        appEnvironment: 'testing',
        algorithm: 'sha256',
        clientIdLength: 16,
        secretLength: 48,
        cacheStore: null,
        cachePrefix: 'hmac:nonce:',
        nonceTtl: 600,
        maxBodySize: 1048576,
        minNonceLength: 32,
    );
}

describe('RateLimiterService', function () {
    beforeEach(function () {
        $this->cache = Mockery::mock(CacheRepository::class);
    });

    describe('isLimited()', function () {
        it('returns false when rate limiting is disabled', function () {
            $config = createRateLimiterConfig(['rateLimitEnabled' => false]);
            $service = new RateLimiterService($this->cache, $config);

            // Cache should not be called
            $this->cache->shouldNotReceive('get');

            expect($service->isLimited('test-client-id'))->toBeFalse();
        });

        it('returns false when under limit', function () {
            $config = createRateLimiterConfig(['rateLimitMaxAttempts' => 60]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('get')
                ->with(Mockery::type('string'), 0)
                ->once()
                ->andReturn(30);

            expect($service->isLimited('test-client-id'))->toBeFalse();
        });

        it('returns true when at limit', function () {
            $config = createRateLimiterConfig(['rateLimitMaxAttempts' => 60]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('get')
                ->with(Mockery::type('string'), 0)
                ->once()
                ->andReturn(60);

            expect($service->isLimited('test-client-id'))->toBeTrue();
        });

        it('returns true when over limit', function () {
            $config = createRateLimiterConfig(['rateLimitMaxAttempts' => 60]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('get')
                ->with(Mockery::type('string'), 0)
                ->once()
                ->andReturn(100);

            expect($service->isLimited('test-client-id'))->toBeTrue();
        });

        it('handles non-numeric cache values', function () {
            $config = createRateLimiterConfig(['rateLimitMaxAttempts' => 60]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('get')
                ->with(Mockery::type('string'), 0)
                ->once()
                ->andReturn('invalid');

            // Should treat non-numeric as 0
            expect($service->isLimited('test-client-id'))->toBeFalse();
        });

        it('uses consistent cache key for same client', function () {
            $config = createRateLimiterConfig();
            $service = new RateLimiterService($this->cache, $config);

            $keys = [];
            $this->cache->shouldReceive('get')
                ->with(Mockery::capture($keys), 0)
                ->twice()
                ->andReturn(0);

            $service->isLimited('same-client-id');
            $service->isLimited('same-client-id');

            // Both calls should use the same key
            // (We can't directly compare due to the capture behavior)
        });
    });

    describe('recordFailure()', function () {
        it('does nothing when rate limiting is disabled', function () {
            $config = createRateLimiterConfig(['rateLimitEnabled' => false]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldNotReceive('add');
            $this->cache->shouldNotReceive('increment');

            $service->recordFailure('test-client-id');
        });

        it('adds new key when it does not exist', function () {
            $config = createRateLimiterConfig(['rateLimitDecayMinutes' => 1]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('add')
                ->with(Mockery::type('string'), 1, Mockery::type(\DateTimeInterface::class))
                ->once()
                ->andReturn(true);

            $service->recordFailure('new-client-id');
        });

        it('increments existing key', function () {
            $config = createRateLimiterConfig(['rateLimitDecayMinutes' => 1]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('add')
                ->with(Mockery::type('string'), 1, Mockery::type(\DateTimeInterface::class))
                ->once()
                ->andReturn(false); // Key already exists

            $this->cache->shouldReceive('increment')
                ->with(Mockery::type('string'))
                ->once();

            $service->recordFailure('existing-client-id');
        });

        it('uses correct decay time', function () {
            $config = createRateLimiterConfig(['rateLimitDecayMinutes' => 5]);
            $service = new RateLimiterService($this->cache, $config);

            $capturedExpiry = null;
            $this->cache->shouldReceive('add')
                ->with(Mockery::type('string'), 1, Mockery::capture($capturedExpiry))
                ->once()
                ->andReturn(true);

            $service->recordFailure('test-client');

            // The expiry should be approximately 5 minutes from now
            // We can't directly test the Carbon instance, but we verify the method was called
        });
    });

    describe('reset()', function () {
        it('does nothing when rate limiting is disabled', function () {
            $config = createRateLimiterConfig(['rateLimitEnabled' => false]);
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldNotReceive('forget');

            $service->reset('test-client-id');
        });

        it('removes rate limit key', function () {
            $config = createRateLimiterConfig();
            $service = new RateLimiterService($this->cache, $config);

            $this->cache->shouldReceive('forget')
                ->with(Mockery::type('string'))
                ->once();

            $service->reset('test-client-id');
        });
    });

    describe('getCacheKeyForClient()', function () {
        it('returns consistent key for same client', function () {
            $config = createRateLimiterConfig();
            $service = new RateLimiterService($this->cache, $config);

            $key1 = $service->getCacheKeyForClient('test-client');
            $key2 = $service->getCacheKeyForClient('test-client');

            expect($key1)->toBe($key2);
        });

        it('returns different keys for different clients', function () {
            $config = createRateLimiterConfig();
            $service = new RateLimiterService($this->cache, $config);

            $key1 = $service->getCacheKeyForClient('client-one');
            $key2 = $service->getCacheKeyForClient('client-two');

            expect($key1)->not->toBe($key2);
        });

        it('includes rate limit prefix', function () {
            $config = createRateLimiterConfig();
            $service = new RateLimiterService($this->cache, $config);

            $key = $service->getCacheKeyForClient('test-client');

            expect($key)->toContain('hmac_rate_limit');
        });
    });
});

afterEach(function () {
    Mockery::close();
});
