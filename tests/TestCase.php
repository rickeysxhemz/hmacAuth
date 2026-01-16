<?php

declare(strict_types=1);

namespace HmacAuth\Tests;

use HmacAuth\HmacAuthServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for testing
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            HmacAuthServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup HMAC config
        $app['config']->set('hmac.enabled', true);
        $app['config']->set('hmac.algorithm', 'sha256');
        $app['config']->set('hmac.key_prefix', 'test');
        $app['config']->set('hmac.timestamp_tolerance', 300);
        $app['config']->set('hmac.nonce_ttl', 600);
        $app['config']->set('hmac.secret_length', 48);
        $app['config']->set('hmac.client_id_length', 16);
        $app['config']->set('hmac.max_body_size', 1048576);
        $app['config']->set('hmac.min_nonce_length', 32);
        $app['config']->set('hmac.negative_cache_ttl', 60);
        $app['config']->set('hmac.enforce_environment', false);
        $app['config']->set('hmac.rate_limit.enabled', true);
        $app['config']->set('hmac.rate_limit.max_attempts', 60);
        $app['config']->set('hmac.rate_limit.decay_minutes', 1);
        $app['config']->set('hmac.ip_blocking.enabled', true);
        $app['config']->set('hmac.ip_blocking.threshold', 10);
        $app['config']->set('hmac.ip_blocking.window_minutes', 10);
        $app['config']->set('hmac.cache.store', null);
        $app['config']->set('hmac.cache.prefix', 'hmac:nonce:');
        $app['config']->set('hmac.headers', [
            'api-key' => 'X-Api-Key',
            'signature' => 'X-Signature',
            'timestamp' => 'X-Timestamp',
            'nonce' => 'X-Nonce',
        ]);

        // Set app key for encryption
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');

        // Set cache driver to array for testing (no external dependencies)
        $app['config']->set('cache.default', 'array');

        // Use test fixtures
        $app['config']->set('hmac.models.user', \HmacAuth\Tests\Fixtures\Models\User::class);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Create a test API credential.
     *
     * @return array{client_id: string, client_secret: string, credential: \HmacAuth\Models\ApiCredential}
     */
    protected function createTestCredential(array $attributes = []): array
    {
        $clientId = generateTestClientId('test');
        $clientSecret = generateTestSecret();

        $credential = \HmacAuth\Models\ApiCredential::create(array_merge([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => true,
            'created_by' => 1,
        ], $attributes));

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'credential' => $credential,
        ];
    }

    /**
     * Generate valid HMAC headers for a request.
     */
    protected function generateHmacHeaders(
        string $clientId,
        string $secret,
        string $method,
        string $path,
        string $body = '',
        string $algorithm = 'sha256'
    ): array {
        return createValidHmacHeaders($clientId, $secret, $method, $path, $body, $algorithm);
    }
}
