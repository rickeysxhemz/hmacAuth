<?php

declare(strict_types=1);

use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Services\SecureKeyGenerator;

beforeEach(function () {
    $this->config = new HmacConfig(
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

    $this->generator = new SecureKeyGenerator($this->config);
});

describe('SecureKeyGenerator', function () {
    describe('generateClientId()', function () {
        it('generates client ID with correct prefix for production', function () {
            config(['hmac.key_prefix' => 'myapp']);

            $clientId = $this->generator->generateClientId('production');

            expect($clientId)->toStartWith('myapp_live_')
                ->and(strlen($clientId))->toBeGreaterThan(10);
        });

        it('generates client ID with correct prefix for testing', function () {
            config(['hmac.key_prefix' => 'myapp']);

            $clientId = $this->generator->generateClientId('testing');

            expect($clientId)->toStartWith('myapp_test_')
                ->and(strlen($clientId))->toBeGreaterThan(10);
        });

        it('generates unique client IDs', function () {
            $ids = [];
            for ($i = 0; $i < 100; $i++) {
                $ids[] = $this->generator->generateClientId('testing');
            }

            expect(array_unique($ids))->toHaveCount(100);
        });

        it('uses default prefix when not configured', function () {
            config(['hmac.key_prefix' => 'hmac']);

            $clientId = $this->generator->generateClientId('production');

            expect($clientId)->toStartWith('hmac_live_');
        });

        it('generates client ID of expected length', function () {
            config(['hmac.key_prefix' => 'test']);

            $clientId = $this->generator->generateClientId('testing');

            // Prefix (test_test_) + 32 hex characters (16 bytes * 2)
            // "test_test_" = 10 chars + 32 hex = 42 chars total
            expect(strlen($clientId))->toBe(42);
        });

        it('generates only hex characters for random part', function () {
            config(['hmac.key_prefix' => 'hmac']);

            $clientId = $this->generator->generateClientId('testing');

            // Extract the random part (after "hmac_test_")
            $randomPart = substr($clientId, 10);

            expect($randomPart)->toMatch('/^[0-9a-f]+$/');
        });
    });

    describe('generateClientSecret()', function () {
        it('generates secret of correct length', function () {
            $secret = $this->generator->generateClientSecret();

            // 48 bytes = 64 characters in Base64 (without padding)
            expect(strlen($secret))->toBe(64);
        });

        it('generates unique secrets', function () {
            $secrets = [];
            for ($i = 0; $i < 100; $i++) {
                $secrets[] = $this->generator->generateClientSecret();
            }

            expect(array_unique($secrets))->toHaveCount(100);
        });

        it('produces Base64URL encoded output', function () {
            $secret = $this->generator->generateClientSecret();

            // Should not contain standard Base64 special characters
            expect($secret)->not->toContain('+')
                ->and($secret)->not->toContain('/')
                ->and($secret)->not->toContain('=');
        });

        it('produces decodable Base64URL string', function () {
            $secret = $this->generator->generateClientSecret();

            // Convert back to standard Base64 and decode
            $standardBase64 = strtr($secret, '-_', '+/');
            $decoded = base64_decode($standardBase64, true);

            expect($decoded)->not->toBeFalse()
                ->and(strlen($decoded))->toBe(48);
        });

        it('generates cryptographically random values', function () {
            // Generate many secrets and ensure they have good entropy
            $secrets = [];
            for ($i = 0; $i < 10; $i++) {
                $secrets[] = $this->generator->generateClientSecret();
            }

            // All should be different
            expect(array_unique($secrets))->toHaveCount(10);

            // Each should have expected length
            foreach ($secrets as $secret) {
                expect(strlen($secret))->toBe(64);
            }
        });
    });

    describe('generateNonce()', function () {
        it('generates nonce of correct length', function () {
            $nonce = $this->generator->generateNonce();

            // 16 bytes = 32 hex characters
            expect(strlen($nonce))->toBe(32);
        });

        it('generates unique nonces', function () {
            $nonces = [];
            for ($i = 0; $i < 100; $i++) {
                $nonces[] = $this->generator->generateNonce();
            }

            expect(array_unique($nonces))->toHaveCount(100);
        });

        it('produces hex encoded output', function () {
            $nonce = $this->generator->generateNonce();

            expect($nonce)->toMatch('/^[0-9a-f]+$/');
        });

        it('generates nonces with high entropy', function () {
            // Generate multiple nonces and check they're all unique
            $nonces = [];
            for ($i = 0; $i < 1000; $i++) {
                $nonce = $this->generator->generateNonce();
                expect(isset($nonces[$nonce]))->toBeFalse();
                $nonces[$nonce] = true;
            }
        });
    });

    describe('with custom configuration', function () {
        it('respects custom client ID length', function () {
            $customConfig = new HmacConfig(
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
                clientIdLength: 32, // Double the default
                secretLength: 48,
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 32,
            );

            $generator = new SecureKeyGenerator($customConfig);
            config(['hmac.key_prefix' => 'test']);

            $clientId = $generator->generateClientId('testing');

            // "test_test_" = 10 chars + 64 hex chars (32 bytes * 2)
            expect(strlen($clientId))->toBe(74);
        });

        it('respects custom secret length', function () {
            $customConfig = new HmacConfig(
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
                secretLength: 64, // 64 bytes
                cacheStore: null,
                cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 32,
            );

            $generator = new SecureKeyGenerator($customConfig);
            $secret = $generator->generateClientSecret();

            // 64 bytes = 86 characters in Base64 (without padding)
            expect(strlen($secret))->toBe(86);
        });
    });
});
