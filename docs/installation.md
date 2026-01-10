# Installation

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- Redis

## Quick Install

```bash
composer require your-vendor/laravel-hmac-auth
php artisan hmac:install
```

## Manual Install

```bash
php artisan vendor:publish --tag=hmac-config
php artisan vendor:publish --tag=hmac-migrations
php artisan migrate
```

## Redis Setup

```php
// config/database.php
'redis' => [
    'hmac' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_HMAC_DB', 1),
    ],
],
```

## Middleware

```php
// Route protection
Route::middleware('hmac.auth')->group(function () {
    Route::get('/api/protected', [ApiController::class, 'index']);
});

// Signature-only verification
Route::middleware('hmac.verify')->group(function () {
    Route::post('/webhooks', [WebhookController::class, 'handle']);
});
```

## Generate Credentials

```bash
php artisan hmac:generate --company=1 --environment=production
```

Outputs Client ID (for `X-Api-Key` header) and Client Secret (for signing).

## Verify

```bash
php artisan config:show hmac
php artisan db:table api_credentials
```

## Next Steps

- [Configuration](configuration.md)
- [Client Implementation](client-implementation.md)
- [Artisan Commands](artisan-commands.md)
