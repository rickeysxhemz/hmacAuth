<?php

declare(strict_types=1);

namespace HmacAuth;

use HmacAuth\Config\TenancyConfig;
use HmacAuth\Console\Commands\CleanupLogsCommand;
use HmacAuth\Console\Commands\GenerateCredentialsCommand;
use HmacAuth\Console\Commands\InstallCommand;
use HmacAuth\Console\Commands\RotateSecretCommand;
use HmacAuth\Console\Commands\SetupTenancyCommand;
use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\ApiCredentialServiceInterface;
use HmacAuth\Contracts\ApiRequestLogRepositoryInterface;
use HmacAuth\Contracts\HmacConfigFactoryInterface;
use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\Contracts\KeyGeneratorInterface;
use HmacAuth\Contracts\NonceStoreInterface;
use HmacAuth\Contracts\RateLimiterInterface;
use HmacAuth\Contracts\RequestLoggerInterface;
use HmacAuth\Contracts\SignatureServiceInterface;
use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\Contracts\TenancyScopeStrategyInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Factories\HmacConfigFactory;
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
use HmacAuth\Tenancy\DatabaseTenancyStrategy;
use HmacAuth\Tenancy\NullTenancyStrategy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

final class HmacAuthServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hmac.php', 'hmac');

        $this->registerConfig();
        $this->registerStatelessServices();
        $this->registerScopedServices();
        $this->registerFacade();
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'hmac');

        $this->registerMiddleware();
        $this->registerPolicies();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();

            if (class_exists(AboutCommand::class)) {
                $this->registerAboutCommand();
            }
        }
    }

    private function registerConfig(): void
    {
        // Factory for creating config DTOs - inject Laravel's config repository
        $this->app->singleton(HmacConfigFactoryInterface::class, HmacConfigFactory::class);

        // Scoped for Octane compatibility - fresh config per request
        $this->app->scoped(HmacConfig::class, fn (Application $app): HmacConfig => $app->make(HmacConfigFactoryInterface::class)->create());
        $this->app->scoped(TenancyConfigInterface::class, fn (): TenancyConfig => TenancyConfig::fromConfig());

        // Register tenancy strategy based on config
        $this->app->scoped(TenancyScopeStrategyInterface::class, function (Application $app): TenancyScopeStrategyInterface {
            $tenancyConfig = $app->make(TenancyConfigInterface::class);

            if ($tenancyConfig->isEnabled()) {
                return new DatabaseTenancyStrategy($tenancyConfig);
            }

            return new NullTenancyStrategy;
        });
    }

    private function registerStatelessServices(): void
    {
        // Stateless services - safe as singletons in Octane
        $this->app->singleton(SignatureServiceInterface::class, SignatureService::class);
        $this->app->singleton(KeyGeneratorInterface::class, SecureKeyGenerator::class);
    }

    private function registerScopedServices(): void
    {
        // Scoped services - flushed per request in Octane
        $this->app->scoped(NonceStoreInterface::class, function (Application $app): NonceStore {
            $config = $app->make(HmacConfig::class);

            if ($app->environment('testing')) {
                return new NonceStore(null, $config);
            }

            return new NonceStore(
                Redis::connection(config('hmac.redis.connection', 'default')),
                $config
            );
        });

        $this->app->scoped(ApiCredentialRepositoryInterface::class, ApiCredentialRepository::class);
        $this->app->scoped(ApiRequestLogRepositoryInterface::class, ApiRequestLogRepository::class);
        $this->app->scoped(RateLimiterInterface::class, RateLimiterService::class);
        $this->app->scoped(RequestLoggerInterface::class, RequestLogger::class);
        $this->app->scoped(HmacVerifierInterface::class, HmacVerificationService::class);
        $this->app->scoped(ApiCredentialServiceInterface::class, ApiCredentialService::class);
    }

    private function registerFacade(): void
    {
        // Scoped - depends on scoped services
        $this->app->scoped('hmac', fn (Application $app): HmacManager => new HmacManager(
            $app->make(HmacVerifierInterface::class),
            $app->make(SignatureServiceInterface::class),
            $app->make(ApiCredentialServiceInterface::class),
            $app->make(KeyGeneratorInterface::class),
        ));
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('hmac.verify', VerifyHmacSignature::class);
    }

    private function registerPolicies(): void
    {
        Gate::policy(ApiCredential::class, ApiCredentialPolicy::class);
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/hmac.php' => config_path('hmac.php'),
        ], 'hmac-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'hmac-migrations');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/hmac'),
        ], 'hmac-lang');
    }

    private function registerCommands(): void
    {
        $this->commands([
            InstallCommand::class,
            GenerateCredentialsCommand::class,
            RotateSecretCommand::class,
            CleanupLogsCommand::class,
            SetupTenancyCommand::class,
        ]);
    }

    private function registerAboutCommand(): void
    {
        AboutCommand::add('HMAC Auth', fn (): array => [
            'Version' => '1.0.0',
            'Algorithm' => config('hmac.algorithm', 'sha256'),
            'Enabled' => config('hmac.enabled', true) ? '<fg=green;options=bold>YES</>' : '<fg=red;options=bold>NO</>',
            'Rate Limiting' => config('hmac.rate_limit.enabled', true) ? '<fg=green;options=bold>YES</>' : '<fg=red;options=bold>NO</>',
        ]);
    }
}
