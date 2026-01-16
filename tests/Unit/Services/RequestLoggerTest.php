<?php

declare(strict_types=1);

use HmacAuth\Contracts\ApiRequestLogRepositoryInterface;
use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\RequestLogger;
use Illuminate\Http\Request;

describe('RequestLogger', function () {
    beforeEach(function () {
        $this->logRepository = Mockery::mock(ApiRequestLogRepositoryInterface::class);
        $this->tenancyConfig = Mockery::mock(TenancyConfigInterface::class);

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
            enforceEnvironment: false,
            appEnvironment: 'testing',
            algorithm: 'sha256',
            clientIdLength: 16,
            secretLength: 48,
            cacheStore: null,
            cachePrefix: 'hmac:nonce:',
            nonceTtl: 600,
            maxBodySize: 1048576,
            minNonceLength: 32,
            ipBlockingEnabled: true,
            ipBlockingThreshold: 10,
            ipBlockingWindowMinutes: 10,
        );

        $this->logger = new RequestLogger(
            $this->logRepository,
            $this->config,
            $this->tenancyConfig
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    describe('logSuccessfulAttempt', function () {
        it('logs successful attempt without tenancy', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);

            $credential = new ApiCredential;
            $credential->id = 1;
            $credential->client_id = 'test-client';

            $request = Request::create('/api/test', 'GET');

            $this->logRepository->shouldReceive('create')
                ->once()
                ->withArgs(function ($data) {
                    return $data['api_credential_id'] === 1
                        && $data['client_id'] === 'test-client'
                        && $data['request_method'] === 'GET'
                        && $data['signature_valid'] === true
                        && $data['response_status'] === 200;
                });

            $this->logger->logSuccessfulAttempt($request, $credential);
        });

        it('logs successful attempt with tenancy', function () {
            // Enable tenancy in config so model accessor works
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            app()->forgetScopedInstances();

            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(true);
            $this->tenancyConfig->shouldReceive('getColumn')->andReturn('tenant_id');

            $credential = new ApiCredential;
            $credential->id = 1;
            $credential->client_id = 'test-client';
            $credential->tenant_id = 123; // Now this should work

            $request = Request::create('/api/test', 'GET');

            $this->logRepository->shouldReceive('create')
                ->once()
                ->withArgs(function ($data) {
                    // The data should include tenant_id
                    return isset($data['tenant_id']) && $data['tenant_id'] === 123 && $data['api_credential_id'] === 1;
                });

            $this->logger->logSuccessfulAttempt($request, $credential);

            // Reset config
            config(['hmac.tenancy.enabled' => false]);
            app()->forgetScopedInstances();
        });
    });

    describe('logFailedAttempt', function () {
        it('logs failed attempt without credential', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);

            $request = Request::create('/api/test', 'POST');

            $this->logRepository->shouldReceive('create')
                ->once()
                ->withArgs(function ($data) {
                    return $data['api_credential_id'] === null
                        && $data['client_id'] === 'invalid-client'
                        && $data['signature_valid'] === false
                        && $data['response_status'] === 401;
                });

            $this->logger->logFailedAttempt($request, 'invalid-client', 'Invalid signature');
        });

        it('logs failed attempt with credential', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);

            $credential = new ApiCredential;
            $credential->id = 5;
            $credential->client_id = 'test-client';

            $request = Request::create('/api/test', 'POST');

            $this->logRepository->shouldReceive('create')
                ->once()
                ->withArgs(function ($data) {
                    return $data['api_credential_id'] === 5;
                });

            $this->logger->logFailedAttempt($request, 'test-client', 'Invalid signature', $credential);
        });

        it('logs failed attempt with tenancy and credential', function () {
            // Enable tenancy in config so model accessor works
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            app()->forgetScopedInstances();

            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(true);
            $this->tenancyConfig->shouldReceive('getColumn')->andReturn('tenant_id');

            $credential = new ApiCredential;
            $credential->id = 5;
            $credential->client_id = 'test-client';
            $credential->tenant_id = 456; // Now this should work

            $request = Request::create('/api/test', 'POST');

            $this->logRepository->shouldReceive('create')
                ->once()
                ->withArgs(function ($data) {
                    // The data should include tenant_id
                    return isset($data['tenant_id']) && $data['tenant_id'] === 456 && $data['api_credential_id'] === 5;
                });

            $this->logger->logFailedAttempt($request, 'test-client', 'Invalid signature', $credential);

            // Reset config
            config(['hmac.tenancy.enabled' => false]);
            app()->forgetScopedInstances();
        });
    });

    describe('hasExcessiveFailures', function () {
        it('returns false when IP blocking is disabled', function () {
            $configDisabled = new HmacConfig(
                enabled: true,
                apiKeyHeader: 'X-Api-Key',
                signatureHeader: 'X-Signature',
                timestampHeader: 'X-Timestamp',
                nonceHeader: 'X-Nonce',
                timestampTolerance: 300,
                rateLimitEnabled: true,
                rateLimitMaxAttempts: 60,
                rateLimitDecayMinutes: 1,
                enforceEnvironment: false,
                appEnvironment: 'testing',
                algorithm: 'sha256',
                clientIdLength: 16,
                secretLength: 48,
                cacheStore: null,
            cachePrefix: 'hmac:nonce:',
                nonceTtl: 600,
                maxBodySize: 1048576,
                minNonceLength: 32,
                ipBlockingEnabled: false,
                ipBlockingThreshold: 10,
                ipBlockingWindowMinutes: 10,
            );

            $logger = new RequestLogger($this->logRepository, $configDisabled, $this->tenancyConfig);

            $result = $logger->hasExcessiveFailures('192.168.1.1');

            expect($result)->toBeFalse();
        });

        it('returns true when failures exceed threshold', function () {
            $this->logRepository->shouldReceive('countFailedByIp')
                ->with('192.168.1.1', 10)
                ->andReturn(10);

            $result = $this->logger->hasExcessiveFailures('192.168.1.1');

            expect($result)->toBeTrue();
        });

        it('returns false when failures below threshold', function () {
            $this->logRepository->shouldReceive('countFailedByIp')
                ->with('192.168.1.1', 10)
                ->andReturn(5);

            $result = $this->logger->hasExcessiveFailures('192.168.1.1');

            expect($result)->toBeFalse();
        });

        it('uses custom threshold and minutes', function () {
            $this->logRepository->shouldReceive('countFailedByIp')
                ->with('192.168.1.1', 30)
                ->andReturn(25);

            $result = $this->logger->hasExcessiveFailures('192.168.1.1', 20, 30);

            expect($result)->toBeTrue();
        });
    });

    describe('hasExcessiveClientFailures', function () {
        it('returns true when client failures exceed threshold', function () {
            $this->logRepository->shouldReceive('countFailedAttempts')
                ->with('test-client', 10)
                ->andReturn(10);

            $result = $this->logger->hasExcessiveClientFailures('test-client');

            expect($result)->toBeTrue();
        });

        it('returns false when client failures below threshold', function () {
            $this->logRepository->shouldReceive('countFailedAttempts')
                ->with('test-client', 10)
                ->andReturn(5);

            $result = $this->logger->hasExcessiveClientFailures('test-client');

            expect($result)->toBeFalse();
        });
    });

    describe('clearFailuresForIp', function () {
        it('clears failures for IP', function () {
            $this->logRepository->shouldReceive('deleteFailedByIp')
                ->with('192.168.1.1', 10)
                ->andReturn(5);

            $result = $this->logger->clearFailuresForIp('192.168.1.1');

            expect($result)->toBe(5);
        });
    });

    describe('isIpBlockingEnabled', function () {
        it('returns true when enabled', function () {
            $result = $this->logger->isIpBlockingEnabled();

            expect($result)->toBeTrue();
        });
    });

    describe('getIpBlockingConfig', function () {
        it('returns IP blocking configuration', function () {
            $result = $this->logger->getIpBlockingConfig();

            expect($result)->toBe([
                'threshold' => 10,
                'window_minutes' => 10,
            ]);
        });
    });
});
