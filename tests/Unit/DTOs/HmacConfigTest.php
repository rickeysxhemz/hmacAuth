<?php

declare(strict_types=1);

use HmacAuth\DTOs\HmacConfig;

describe('HmacConfig', function () {
    describe('constructor', function () {
        it('creates config with all valid parameters', function () {
            $config = new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
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

            expect($config->enabled)->toBeTrue()
                ->and($config->apiKeyHeader)->toBe('X-Api-Key')
                ->and($config->signatureHeader)->toBe('X-Signature')
                ->and($config->timestampTolerance)->toBe(300)
                ->and($config->algorithm)->toBe('sha256');
        });

        it('throws exception for non-positive timestamp tolerance', function () {
            expect(fn () => new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 0, // Invalid
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
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
            ))->toThrow(InvalidArgumentException::class, 'Timestamp tolerance must be positive');
        });

        it('throws exception for negative timestamp tolerance', function () {
            expect(fn () => new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: -100,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
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
            ))->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for non-positive max body size', function () {
            expect(fn () => new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
                enforceEnvironment: true,
                appEnvironment: 'testing',
                algorithm: 'sha256',
                clientIdLength: 16,
                secretLength: 48,
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 0, // Invalid
                minNonceLength: 32,
            ))->toThrow(InvalidArgumentException::class, 'Max body size must be positive');
        });

        it('throws exception for min nonce length less than 16', function () {
            expect(fn () => new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
                enforceEnvironment: true,
                appEnvironment: 'testing',
                algorithm: 'sha256',
                clientIdLength: 16,
                secretLength: 48,
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 15, // Invalid - must be at least 16
            ))->toThrow(InvalidArgumentException::class, 'Min nonce length must be at least 16');
        });

        it('accepts minimum valid nonce length of 16', function () {
            $config = new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
                enforceEnvironment: true,
                appEnvironment: 'testing',
                algorithm: 'sha256',
                clientIdLength: 16,
                secretLength: 48,
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 16, // Minimum valid
            );

            expect($config->minNonceLength)->toBe(16);
        });
    });

    describe('fromConfig()', function () {
        beforeEach(function () {
            // Set up config values
            config([
                'hmac.enabled' => true,
                'hmac.headers.api-key' => 'X-Api-Key',
                'hmac.headers.signature' => 'X-Signature',
                'hmac.headers.timestamp' => 'X-Timestamp',
                'hmac.headers.nonce' => 'X-Nonce',
                'hmac.timestamp_tolerance' => 300,
                'hmac.rate_limit.enabled' => true,
                'hmac.rate_limit.max_attempts' => 60,
                'hmac.rate_limit.decay_minutes' => 1,
                'hmac.enforce_environment' => true,
                'app.env' => 'testing',
                'hmac.algorithm' => 'sha256',
                'hmac.client_id_length' => 16,
                'hmac.secret_length' => 48,
                'hmac.cache.store' => null,
                'hmac.cache.prefix' => 'hmac:nonce:',
                'hmac.nonce_ttl' => 600,
                'hmac.max_body_size' => 1048576,
                'hmac.min_nonce_length' => 32,
                'hmac.negative_cache_ttl' => 60,
                'hmac.ip_blocking.enabled' => true,
                'hmac.ip_blocking.threshold' => 10,
                'hmac.ip_blocking.window_minutes' => 10,
            ]);
        });

        it('loads config from Laravel config', function () {
            $config = HmacConfig::fromConfig();

            expect($config->enabled)->toBeTrue()
                ->and($config->apiKeyHeader)->toBe('X-Api-Key')
                ->and($config->timestampTolerance)->toBe(300)
                ->and($config->algorithm)->toBe('sha256');
        });

        it('uses defaults for missing config values', function () {
            config(['hmac.headers.api-key' => null]);

            $config = HmacConfig::fromConfig();

            expect($config->apiKeyHeader)->toBe('X-Api-Key');
        });
    });

    describe('isProduction()', function () {
        it('returns true when app environment is production', function () {
            $config = new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
                enforceEnvironment: true,
                appEnvironment: 'production',
                algorithm: 'sha256',
                clientIdLength: 16,
                secretLength: 48,
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 32,
            );

            expect($config->isProduction())->toBeTrue();
        });

        it('returns false when app environment is not production', function () {
            $config = new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
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

            expect($config->isProduction())->toBeFalse();
        });

        it('returns false for local environment', function () {
            $config = new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
                enforceEnvironment: true,
                appEnvironment: 'local',
                algorithm: 'sha256',
                clientIdLength: 16,
                secretLength: 48,
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 32,
            );

            expect($config->isProduction())->toBeFalse();
        });
    });
});
