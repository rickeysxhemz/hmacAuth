<?php

declare(strict_types=1);

use Carbon\Carbon;
use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\KeyGeneratorInterface;
use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\ApiCredentialService;

describe('ApiCredentialService', function () {
    beforeEach(function () {
        $this->repository = Mockery::mock(ApiCredentialRepositoryInterface::class);
        $this->keyGenerator = Mockery::mock(KeyGeneratorInterface::class);
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
            redisPrefix: 'hmac:',
            nonceTtl: 600,
            maxBodySize: 1048576,
            minNonceLength: 32,
            negativeCacheTtl: 60,
            ipBlockingEnabled: true,
            ipBlockingThreshold: 10,
            ipBlockingWindowMinutes: 10,
        );
        $this->tenancyConfig = Mockery::mock(TenancyConfigInterface::class);

        $this->service = new ApiCredentialService(
            $this->repository,
            $this->keyGenerator,
            $this->config,
            $this->tenancyConfig,
        );
    });

    describe('generate', function () {
        it('creates credential with valid environment', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $this->keyGenerator->shouldReceive('generateClientId')
                ->with('testing')
                ->andReturn('test_abc123');
            $this->keyGenerator->shouldReceive('generateClientSecret')
                ->andReturn('secret123');

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->shouldReceive('load')->with([])->andReturnSelf();

            $this->repository->shouldReceive('create')
                ->with(Mockery::on(function ($data) {
                    return $data['client_id'] === 'test_abc123'
                        && $data['client_secret'] === 'secret123'
                        && $data['hmac_algorithm'] === 'sha256'
                        && $data['environment'] === 'testing'
                        && $data['is_active'] === true;
                }))
                ->andReturn($credential);

            $result = $this->service->generate(createdBy: 1, environment: 'testing');

            expect($result['credential'])->toBe($credential);
            expect($result['plain_secret'])->toBe('secret123');
        });

        it('throws exception for invalid environment', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);

            $this->service->generate(createdBy: 1, environment: 'invalid');
        })->throws(InvalidArgumentException::class);

        it('requires tenant ID when tenancy is enabled', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(true);

            $this->service->generate(createdBy: 1, tenantId: null);
        })->throws(InvalidArgumentException::class, 'Tenant ID is required when tenancy is enabled');

        it('includes tenant column when tenancy enabled', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(true);
            $this->tenancyConfig->shouldReceive('getColumn')->andReturn('tenant_id');
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn(['tenant']);

            $this->keyGenerator->shouldReceive('generateClientId')->andReturn('test_xyz');
            $this->keyGenerator->shouldReceive('generateClientSecret')->andReturn('secret456');

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->shouldReceive('load')->with(['tenant'])->andReturnSelf();

            $this->repository->shouldReceive('create')
                ->with(Mockery::on(fn ($data) => $data['tenant_id'] === 42))
                ->andReturn($credential);

            $result = $this->service->generate(createdBy: 1, tenantId: 42);

            expect($result)->toHaveKey('credential');
        });

        it('sets expiration date when provided', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $this->keyGenerator->shouldReceive('generateClientId')->andReturn('test_exp');
            $this->keyGenerator->shouldReceive('generateClientSecret')->andReturn('secret789');

            $expiresAt = Carbon::now()->addDays(30);
            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->shouldReceive('load')->andReturnSelf();

            $this->repository->shouldReceive('create')
                ->with(Mockery::on(fn ($data) => $data['expires_at'] === $expiresAt))
                ->andReturn($credential);

            $result = $this->service->generate(
                createdBy: 1,
                expiresAt: $expiresAt
            );

            expect($result)->toHaveKey('credential');
        });
    });

    describe('rotateSecret', function () {
        it('generates new secret and stores old one', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_rotate';
            $credential->client_secret = 'old_secret';
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->keyGenerator->shouldReceive('generateClientSecret')
                ->andReturn('new_secret');

            $this->repository->shouldReceive('update')
                ->with($credential, Mockery::on(function ($data) {
                    return $data['old_client_secret'] === 'old_secret'
                        && $data['client_secret'] === 'new_secret'
                        && isset($data['old_secret_expires_at']);
                }))
                ->andReturn(true);

            $this->repository->shouldReceive('invalidateCache')
                ->with('test_rotate')
                ->andReturn(true);

            $result = $this->service->rotateSecret($credential, graceDays: 7);

            expect($result['new_secret'])->toBe('new_secret');
            expect($result)->toHaveKey('old_secret_expires_at');
        });
    });

    describe('regenerateClientId', function () {
        it('generates new client ID and invalidates old cache', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'old_client_id';
            $credential->environment = 'testing';
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->keyGenerator->shouldReceive('generateClientId')
                ->with('testing')
                ->andReturn('new_client_id');

            $this->repository->shouldReceive('update')
                ->with($credential, ['client_id' => 'new_client_id'])
                ->andReturn(true);

            $this->repository->shouldReceive('invalidateCache')
                ->with('old_client_id')
                ->andReturn(true);

            $result = $this->service->regenerateClientId($credential);

            expect($result)->toBe($credential);
        });
    });

    describe('toggleStatus', function () {
        it('deactivates active credential', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_toggle';
            $credential->is_active = true;
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->repository->shouldReceive('deactivate')
                ->with($credential)
                ->andReturn(true);
            $this->repository->shouldReceive('invalidateCache')
                ->with('test_toggle')
                ->andReturn(true);

            $result = $this->service->toggleStatus($credential);

            expect($result)->toBe($credential);
        });

        it('activates inactive credential', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_toggle';
            $credential->is_active = false;
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->repository->shouldReceive('activate')
                ->with($credential)
                ->andReturn(true);
            $this->repository->shouldReceive('invalidateCache')
                ->with('test_toggle')
                ->andReturn(true);

            $result = $this->service->toggleStatus($credential);

            expect($result)->toBe($credential);
        });
    });

    describe('deactivate', function () {
        it('deactivates credential and invalidates cache', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_deactivate';
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->repository->shouldReceive('deactivate')
                ->with($credential)
                ->andReturn(true);
            $this->repository->shouldReceive('invalidateCache')
                ->with('test_deactivate')
                ->andReturn(true);

            $result = $this->service->deactivate($credential);

            expect($result)->toBe($credential);
        });
    });

    describe('activate', function () {
        it('activates credential and invalidates cache', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_activate';
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->repository->shouldReceive('activate')
                ->with($credential)
                ->andReturn(true);
            $this->repository->shouldReceive('invalidateCache')
                ->with('test_activate')
                ->andReturn(true);

            $result = $this->service->activate($credential);

            expect($result)->toBe($credential);
        });
    });

    describe('setExpiration', function () {
        it('sets expiration date', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_expire';
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $expiresAt = Carbon::now()->addDays(30);

            $this->repository->shouldReceive('update')
                ->with($credential, ['expires_at' => $expiresAt])
                ->andReturn(true);
            $this->repository->shouldReceive('invalidateCache')
                ->with('test_expire')
                ->andReturn(true);

            $result = $this->service->setExpiration($credential, $expiresAt);

            expect($result)->toBe($credential);
        });

        it('removes expiration when null', function () {
            $this->tenancyConfig->shouldReceive('getDefaultRelations')->andReturn([]);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->client_id = 'test_expire_null';
            $credential->shouldReceive('fresh')->with([])->andReturnSelf();

            $this->repository->shouldReceive('update')
                ->with($credential, ['expires_at' => null])
                ->andReturn(true);
            $this->repository->shouldReceive('invalidateCache')
                ->with('test_expire_null')
                ->andReturn(true);

            $result = $this->service->setExpiration($credential, null);

            expect($result)->toBe($credential);
        });
    });

    describe('delete', function () {
        it('deletes credential and invalidates cache', function () {
            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->shouldReceive('getAttribute')->with('client_id')->andReturn('test_delete');
            $credential->client_id = 'test_delete';

            $this->repository->shouldReceive('invalidateCache')
                ->with('test_delete')
                ->andReturn(true);
            $this->repository->shouldReceive('delete')
                ->with($credential)
                ->andReturn(true);

            $result = $this->service->delete($credential);

            expect($result)->toBeTrue();
        });
    });
});
