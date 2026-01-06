<?php

declare(strict_types=1);

namespace HmacAuth;

use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\ApiRequestLogRepositoryInterface;
use HmacAuth\Contracts\KeyGeneratorInterface;
use HmacAuth\Contracts\NonceStoreInterface;
use HmacAuth\Contracts\RateLimiterInterface;
use HmacAuth\Contracts\RequestLoggerInterface;
use HmacAuth\Contracts\SignatureServiceInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Http\Middleware\VerifyHmacSignature;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Policies\ApiCredentialPolicy;
use HmacAuth\Repositories\ApiCredentialRepository;
use HmacAuth\Repositories\ApiRequestLogRepository;
use HmacAuth\Services\ApiCredentialService;
use HmacAuth\Services\HmacVerificationService;
use HmacAuth\Services\NonceStore;
use HmacAuth\Services\RateLimiterService;
use HmacAuth\Services\RequestLogger;
use HmacAuth\Services\SecureKeyGenerator;
use HmacAuth\Services\SignatureService;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for HMAC authentication services.
 */
final class HmacAuthServiceProvider extends ServiceProvider
{
    /**
     * Interface to implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ApiCredentialRepositoryInterface::class => ApiCredentialRepository::class,
        ApiRequestLogRepositoryInterface::class => ApiRequestLogRepository::class,
        KeyGeneratorInterface::class => SecureKeyGenerator::class,
        RateLimiterInterface::class => RateLimiterService::class,
        RequestLoggerInterface::class => RequestLogger::class,
        SignatureServiceInterface::class => SignatureService::class,
    ];

    /**
     * Singleton services (auto-resolved by container).
     *
     * @var array<class-string, class-string|null>
     */
    public array $singletons = [
        ApiCredentialRepository::class => null,
        ApiRequestLogRepository::class => null,
        SignatureService::class => null,
        SecureKeyGenerator::class => null,
        RateLimiterService::class => null,
        RequestLogger::class => null,
        HmacVerificationService::class => null,
        ApiCredentialService::class => null,
    ];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hmac.php', 'hmac');

        $this->app->singleton(HmacConfig::class, fn (): HmacConfig => HmacConfig::fromConfig());

        // Skip Redis-dependent services in testing environment
        if ($this->app->environment('testing')) {
            $this->app->singleton(NonceStore::class, fn ($app): NonceStore => new NonceStore(
                null,
                $app->make(HmacConfig::class)
            ));
        } else {
            $this->app->singleton(NonceStore::class, fn ($app): NonceStore => new NonceStore(
                Redis::connection(config('hmac.redis.connection', 'default')),
                $app->make(HmacConfig::class)
            ));
        }

        $this->app->bind(NonceStoreInterface::class, NonceStore::class);
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerPolicies();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hmac-auth');
    }

    private function registerMiddleware(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('hmac.verify', VerifyHmacSignature::class);
    }

    private function registerPolicies(): void
    {
        Gate::policy(ApiCredential::class, ApiCredentialPolicy::class);
    }

    private function registerPublishing(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/hmac.php' => config_path('hmac.php'),
        ], 'hmac-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/create_api_credentials_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_api_credentials_table.php'),
            __DIR__.'/../database/migrations/create_api_request_logs_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_api_request_logs_table.php'),
        ], 'hmac-migrations');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/hmac-auth'),
        ], 'hmac-views');

        // Publish CSS assets
        $this->publishes([
            __DIR__.'/../resources/css' => public_path('vendor/hmac-auth/css'),
        ], 'hmac-assets');

        // Publish all assets together
        $this->publishes([
            __DIR__.'/../config/hmac.php' => config_path('hmac.php'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/hmac-auth'),
            __DIR__.'/../resources/css' => public_path('vendor/hmac-auth/css'),
        ], 'hmac-auth');
    }
}
