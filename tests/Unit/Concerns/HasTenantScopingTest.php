<?php

declare(strict_types=1);

use HmacAuth\Models\ApiCredential;
use HmacAuth\Tests\Fixtures\Models\Tenant;

describe('HasTenantScoping', function () {
    describe('getTenantIdAttribute', function () {
        it('returns null when tenancy is disabled', function () {
            config(['hmac.tenancy.enabled' => false]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $credential->setRawAttributes(['tenant_id' => 123], true);

            expect($credential->tenant_id)->toBeNull();
        });

        it('returns tenant_id when tenancy is enabled', function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => Tenant::class]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $credential->setRawAttributes(['tenant_id' => 456], true);

            expect($credential->tenant_id)->toBe(456);
        });

        it('returns string tenant_id', function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => Tenant::class]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $credential->setRawAttributes(['tenant_id' => 'tenant-uuid-123'], true);

            expect($credential->tenant_id)->toBe('tenant-uuid-123');
        });

        it('returns null for invalid types', function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => Tenant::class]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $credential->setRawAttributes(['tenant_id' => ['array']], true);

            expect($credential->tenant_id)->toBeNull();
        });
    });

    describe('setTenantIdAttribute', function () {
        it('does not set tenant_id when tenancy is disabled', function () {
            config(['hmac.tenancy.enabled' => false]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $credential->tenant_id = 123;

            expect($credential->getAttributes())->not->toHaveKey('tenant_id');
        });

        it('sets tenant_id when tenancy is enabled', function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => Tenant::class]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $credential->tenant_id = 789;

            expect($credential->getAttributes()['tenant_id'])->toBe(789);
        });
    });

    describe('forTenant scope', function () {
        beforeEach(function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => Tenant::class]);
            app()->forgetScopedInstances();

            // Create tenant first
            Tenant::insert(['id' => 1, 'name' => 'Test Tenant']);

            // Create test credentials with tenant
            ApiCredential::insert([
                'client_id' => generateTestClientId('test').'1',
                'client_secret' => 'encrypted_secret',
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
                'tenant_id' => 1,
            ]);

            ApiCredential::insert([
                'client_id' => generateTestClientId('test').'2',
                'client_secret' => 'encrypted_secret',
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
                'tenant_id' => 2,
            ]);
        });

        afterEach(function () {
            config(['hmac.tenancy.enabled' => false]);
            app()->forgetScopedInstances();
        });

        it('filters by tenant when tenancy is enabled', function () {
            $credentials = ApiCredential::forTenant(1)->get();

            expect($credentials)->toHaveCount(1);
        });

        it('returns empty when no credentials for tenant', function () {
            $credentials = ApiCredential::forTenant(999)->get();

            expect($credentials)->toHaveCount(0);
        });
    });

    describe('tenant relationship', function () {
        it('returns BelongsTo relationship', function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => Tenant::class]);
            app()->forgetScopedInstances();

            $credential = new ApiCredential;
            $relation = $credential->tenant();

            expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        });
    });
});
