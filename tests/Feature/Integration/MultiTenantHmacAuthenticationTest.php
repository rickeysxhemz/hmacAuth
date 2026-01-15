<?php

declare(strict_types=1);

use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Repositories\ApiCredentialRepository;
use HmacAuth\Services\SignatureService;
use Illuminate\Support\Facades\Route;

/**
 * Comprehensive multi-tenant HMAC authentication integration test.
 *
 * Tests complete authentication flow with tenant isolation and separation.
 */
describe('Multi-Tenant HMAC Authentication', function () {
    beforeEach(function () {
        // Enable tenancy configuration with test fixture Tenant model
        config(['hmac.tenancy.enabled' => true]);
        config(['hmac.tenancy.column' => 'tenant_id']);
        config(['hmac.tenancy.model' => \HmacAuth\Tests\Fixtures\Models\Tenant::class]);
        config(['hmac.enforce_environment' => false]);

        // Clear scoped bindings to force re-resolution with new config
        app()->forgetScopedInstances();

        // Create test tenants
        \HmacAuth\Tests\Fixtures\Models\Tenant::create(['id' => 1, 'name' => 'Tenant 1']);
        \HmacAuth\Tests\Fixtures\Models\Tenant::create(['id' => 2, 'name' => 'Tenant 2']);
        \HmacAuth\Tests\Fixtures\Models\Tenant::create(['id' => 3, 'name' => 'Tenant 3']);

        // Register test routes with HMAC middleware
        Route::middleware('hmac.verify')->group(function () {
            Route::get('/api/tenant/data', fn () => response()->json([
                'data' => 'protected tenant data',
                'credential' => request()->attributes->get('hmac_credential'),
            ]));

            Route::post('/api/tenant/resource', fn () => response()->json([
                'created' => true,
                'data' => request()->all(),
            ], 201));
        });

        // Helper function to create tenant credentials
        // Note: tenant_id is not in fillable, so we use forceFill/save
        $this->createTenantCredential = function (int $tenantId, string $prefix): array {
            $clientId = $prefix.'_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            $credential = new ApiCredential;
            $credential->forceFill([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_by' => 1,
            ]);
            $credential->save();

            return [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'credential' => $credential,
                'tenant_id' => $tenantId,
            ];
        };

        // Store credentials for different tenants
        $this->tenant1Credentials = ($this->createTenantCredential)(1, 'tenant1');
        $this->tenant2Credentials = ($this->createTenantCredential)(2, 'tenant2');
        $this->tenant3Credentials = ($this->createTenantCredential)(3, 'tenant3');
    });

    describe('tenant isolation', function () {
        it('authenticates request for tenant 1', function () {
            $creds = $this->tenant1Credentials;
            $path = '/api/tenant/data';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $creds['client_secret'], 'sha256');

            $response = $this->getJson($path, [
                'X-Api-Key' => $creds['client_id'],
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('data', 'protected tenant data');
        });

        it('authenticates request for tenant 2 with separate credentials', function () {
            $creds = $this->tenant2Credentials;
            $path = '/api/tenant/data';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $creds['client_secret'], 'sha256');

            $response = $this->getJson($path, [
                'X-Api-Key' => $creds['client_id'],
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(200);
        });

        it('ensures tenant 1 credentials do not access tenant 2 data context', function () {
            // Create two different credentials for different tenants
            $tenant1Creds = $this->tenant1Credentials;
            $tenant2Creds = $this->tenant2Credentials;

            // Verify tenant_id is properly stored and different
            expect($tenant1Creds['credential']->tenant_id)->toBe(1);
            expect($tenant2Creds['credential']->tenant_id)->toBe(2);
            expect($tenant1Creds['credential']->tenant_id)
                ->not->toBe($tenant2Creds['credential']->tenant_id);
        });
    });

    describe('tenant credential separation', function () {
        it('stores correct tenant_id with credentials', function () {
            expect($this->tenant1Credentials['credential']->tenant_id)->toBe(1);
            expect($this->tenant2Credentials['credential']->tenant_id)->toBe(2);
            expect($this->tenant3Credentials['credential']->tenant_id)->toBe(3);
        });

        it('cannot query tenant 2 credentials using tenant 1 scope', function () {
            // Create a repository with tenancy enabled
            $tenancyConfig = app(TenancyConfigInterface::class);
            $repository = new ApiCredentialRepository($tenancyConfig);

            // Get credentials for tenant 1 only
            $tenant1Creds = $repository->getByTenant(1);
            $tenant2Creds = $repository->getByTenant(2);

            // Verify isolation - tenant 1 should not see tenant 2's credentials
            $tenant1ClientIds = $tenant1Creds->pluck('client_id')->toArray();
            $tenant2ClientIds = $tenant2Creds->pluck('client_id')->toArray();

            expect($tenant1ClientIds)->toContain($this->tenant1Credentials['client_id']);
            expect($tenant1ClientIds)->not->toContain($this->tenant2Credentials['client_id']);

            expect($tenant2ClientIds)->toContain($this->tenant2Credentials['client_id']);
            expect($tenant2ClientIds)->not->toContain($this->tenant1Credentials['client_id']);
        });

        it('counts credentials per tenant correctly', function () {
            $tenancyConfig = app(TenancyConfigInterface::class);
            $repository = new ApiCredentialRepository($tenancyConfig);

            // Each tenant has exactly 1 active credential in this test
            expect($repository->countActiveByTenant(1))->toBe(1);
            expect($repository->countActiveByTenant(2))->toBe(1);
            expect($repository->countActiveByTenant(3))->toBe(1);

            // Non-existent tenant has 0
            expect($repository->countActiveByTenant(999))->toBe(0);
        });

        it('returns active credentials only for specific tenant', function () {
            // Deactivate tenant 1's credential
            $this->tenant1Credentials['credential']->update(['is_active' => false]);

            $tenancyConfig = app(TenancyConfigInterface::class);
            $repository = new ApiCredentialRepository($tenancyConfig);

            $active1 = $repository->getActiveByTenant(1);
            $active2 = $repository->getActiveByTenant(2);

            expect($active1)->toHaveCount(0);
            expect($active2)->toHaveCount(1);
        });
    });

    describe('cross-tenant security assertions', function () {
        it('rejects authentication attempt with wrong tenant credentials', function () {
            // Try to use tenant 1's secret with tenant 2's client_id (should fail)
            $path = '/api/tenant/data';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );

            // Sign with tenant 1's secret but send tenant 2's client_id
            $signature = $signatureService->generate(
                $payload,
                $this->tenant1Credentials['client_secret'],
                'sha256'
            );

            $response = $this->getJson($path, [
                'X-Api-Key' => $this->tenant2Credentials['client_id'],
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJsonPath('message', 'Invalid signature');
        });

        it('maintains tenant isolation in database queries', function () {
            // Create additional credentials for tenant 1 using forceFill
            $additionalCred1 = new ApiCredential;
            $additionalCred1->forceFill([
                'client_id' => 'extra1_'.bin2hex(random_bytes(16)),
                'client_secret' => generateTestSecret(),
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'tenant_id' => 1,
                'created_by' => 1,
            ]);
            $additionalCred1->save();

            $additionalCred2 = new ApiCredential;
            $additionalCred2->forceFill([
                'client_id' => 'extra2_'.bin2hex(random_bytes(16)),
                'client_secret' => generateTestSecret(),
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'tenant_id' => 1,
                'created_by' => 1,
            ]);
            $additionalCred2->save();

            $tenancyConfig = app(TenancyConfigInterface::class);
            $repository = new ApiCredentialRepository($tenancyConfig);

            // Tenant 1 should now have 3 credentials
            expect($repository->countActiveByTenant(1))->toBe(3);

            // Tenant 2 still has only 1
            expect($repository->countActiveByTenant(2))->toBe(1);

            // Total in database
            expect(ApiCredential::count())->toBe(5); // 3 for tenant 1, 1 for tenant 2, 1 for tenant 3
        });

        it('applies tenant scope to query builder correctly', function () {
            $tenancyConfig = app(TenancyConfigInterface::class);
            $repository = new ApiCredentialRepository($tenancyConfig);

            // Query base should return all
            $allCredentials = ApiCredential::all();
            expect($allCredentials)->toHaveCount(3);

            // Scoped query should only return tenant's credentials
            $tenant1Only = $repository->getByTenant(1);
            expect($tenant1Only)->toHaveCount(1);
            expect($tenant1Only->first()->tenant_id)->toBe(1);
        });
    });

    describe('multi-tenant POST operations', function () {
        it('creates resource with tenant context', function () {
            $creds = $this->tenant1Credentials;
            $path = '/api/tenant/resource';
            $body = json_encode(['name' => 'Tenant 1 Resource']);
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'POST',
                path: $path,
                body: $body,
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $creds['client_secret'], 'sha256');

            $response = $this->postJson($path, ['name' => 'Tenant 1 Resource'], [
                'X-Api-Key' => $creds['client_id'],
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('created', true)
                ->assertJsonPath('data.name', 'Tenant 1 Resource');
        });

        it('handles concurrent requests from multiple tenants', function () {
            $signatureService = new SignatureService;
            $path = '/api/tenant/data';

            // Prepare requests for all 3 tenants
            $requests = [];
            foreach ([$this->tenant1Credentials, $this->tenant2Credentials, $this->tenant3Credentials] as $creds) {
                $timestamp = (string) time();
                $nonce = bin2hex(random_bytes(16));

                $payload = new SignaturePayload(
                    method: 'GET',
                    path: $path,
                    body: '[]',
                    timestamp: $timestamp,
                    nonce: $nonce,
                );
                $signature = $signatureService->generate($payload, $creds['client_secret'], 'sha256');

                $requests[] = [
                    'headers' => [
                        'X-Api-Key' => $creds['client_id'],
                        'X-Signature' => $signature,
                        'X-Timestamp' => $timestamp,
                        'X-Nonce' => $nonce,
                    ],
                    'tenant_id' => $creds['tenant_id'],
                ];
            }

            // Execute requests (simulating concurrent access)
            foreach ($requests as $request) {
                $response = $this->getJson($path, $request['headers']);
                $response->assertStatus(200);
            }
        });
    });

    describe('tenant credential lifecycle', function () {
        it('tracks usage separately per tenant', function () {
            // Use tenant 1 credential
            $creds1 = $this->tenant1Credentials;
            $signatureService = new SignatureService;
            $path = '/api/tenant/data';

            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $creds1['client_secret'], 'sha256');

            $this->getJson($path, [
                'X-Api-Key' => $creds1['client_id'],
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ])->assertStatus(200);

            // Tenant 1's credential should have last_used_at updated
            $creds1['credential']->refresh();
            expect($creds1['credential']->last_used_at)->not->toBeNull();

            // Tenant 2's credential should still be null
            $this->tenant2Credentials['credential']->refresh();
            expect($this->tenant2Credentials['credential']->last_used_at)->toBeNull();
        });

        it('handles credential deactivation per tenant independently', function () {
            $tenancyConfig = app(TenancyConfigInterface::class);
            $repository = new ApiCredentialRepository($tenancyConfig);

            // Deactivate tenant 1's credential
            $this->tenant1Credentials['credential']->update(['is_active' => false]);

            // Verify tenant 1 has no active credentials
            expect($repository->countActiveByTenant(1))->toBe(0);

            // Verify tenant 2 and 3 still have active credentials
            expect($repository->countActiveByTenant(2))->toBe(1);
            expect($repository->countActiveByTenant(3))->toBe(1);

            // Verify authentication fails for deactivated tenant 1 credential
            $path = '/api/tenant/data';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $this->tenant1Credentials['client_secret'], 'sha256');

            $response = $this->getJson($path, [
                'X-Api-Key' => $this->tenant1Credentials['client_id'],
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401);
        });
    });
});
