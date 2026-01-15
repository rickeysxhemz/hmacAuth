<?php

declare(strict_types=1);

use HmacAuth\Models\ApiCredential;

describe('GenerateCredentialsCommand', function () {
    beforeEach(function () {
        config(['hmac.tenancy.enabled' => false]);
    });

    describe('without tenancy', function () {
        it('generates credentials with default options', function () {
            $this->artisan('hmac:generate')
                ->expectsOutput('Generating API credentials...')
                ->expectsOutput('API credentials generated successfully!')
                ->expectsOutputToContain('Client ID')
                ->expectsOutputToContain('Client Secret')
                ->assertSuccessful();

            expect(ApiCredential::count())->toBe(1);
        });

        it('generates credentials with custom environment', function () {
            $this->artisan('hmac:generate', ['--environment' => 'production'])
                ->assertSuccessful();

            $credential = ApiCredential::first();
            expect($credential->environment)->toBe('production');
        });

        it('generates credentials with expiration', function () {
            $this->artisan('hmac:generate', ['--expires' => '30'])
                ->assertSuccessful();

            $credential = ApiCredential::first();
            expect($credential->expires_at)->not->toBeNull();
        });

        it('fails with invalid environment', function () {
            $this->artisan('hmac:generate', ['--environment' => 'invalid'])
                ->expectsOutput('Environment must be "production" or "testing"')
                ->assertFailed();
        });

        it('generates testing credentials by default', function () {
            $this->artisan('hmac:generate')->assertSuccessful();

            $credential = ApiCredential::first();
            expect($credential->environment)->toBe('testing');
        });
    });

    describe('with tenancy enabled', function () {
        beforeEach(function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
            config(['hmac.tenancy.model' => \HmacAuth\Tests\Fixtures\Models\Tenant::class]);
            // Clear scoped bindings to force re-resolution with new config
            app()->forgetScopedInstances();

            // Create test tenants
            \HmacAuth\Tests\Fixtures\Models\Tenant::insert(['id' => 123, 'name' => 'Test Tenant 123']);
            \HmacAuth\Tests\Fixtures\Models\Tenant::insert(['id' => 456, 'name' => 'Test Tenant 456']);
        });

        it('requires tenant ID when tenancy is enabled', function () {
            $this->artisan('hmac:generate', ['--tenant' => '123'])
                ->expectsOutput('Generating API credentials...')
                ->assertSuccessful();

            $credential = ApiCredential::first();
            // Access raw attribute since accessor relies on cached container binding
            expect((int) $credential->getAttributes()['tenant_id'])->toBe(123);
        });

        it('prompts for tenant ID if not provided', function () {
            $this->artisan('hmac:generate')
                ->expectsQuestion('Tenant ID', '456')
                ->assertSuccessful();

            $credential = ApiCredential::first();
            // Access raw attribute since accessor relies on cached container binding
            expect((int) $credential->getAttributes()['tenant_id'])->toBe(456);
        });

        it('fails if tenant ID is not numeric', function () {
            $this->artisan('hmac:generate')
                ->expectsQuestion('Tenant ID', 'invalid')
                ->expectsOutput('Tenant ID must be a number')
                ->assertFailed();
        });
    });
});
