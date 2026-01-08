# Installation

This guide covers the installation and initial setup of Laravel HMAC Auth.

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- Redis (for nonce storage and rate limiting)
- Composer 2.x

## Installation Steps

### 1. Install via Composer

```bash
composer require your-vendor/laravel-hmac-auth
```

### 2. Run the Installation Command

The easiest way to set up the package is using the interactive installer:

```bash
php artisan hmac:install
```

This command will:
- Publish the configuration file
- Publish and run database migrations
- Optionally generate your first API credentials

### 3. Manual Installation (Alternative)

If you prefer manual setup:

#### Publish Configuration

```bash
php artisan vendor:publish --tag=hmac-config
```

#### Publish Migrations

```bash
php artisan vendor:publish --tag=hmac-migrations
```

#### Run Migrations

```bash
php artisan migrate
```

## Redis Configuration

HMAC Auth uses Redis for nonce storage and rate limiting. Ensure Redis is configured in your Laravel application.

### config/database.php

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
    ],

    // Dedicated connection for HMAC (optional but recommended)
    'hmac' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_HMAC_DB', 1),
    ],
],
```

### Environment Variables

Add to your `.env` file:

```env
# Redis connection for HMAC Auth
REDIS_HMAC_DB=1

# HMAC Configuration
HMAC_ENABLED=true
HMAC_ALGORITHM=sha256
HMAC_TIMESTAMP_TOLERANCE=300
HMAC_NONCE_TTL=600
HMAC_ENFORCE_ENVIRONMENT=true
```

## Middleware Registration

The package automatically registers the middleware. You can use them in your routes:

### Route Middleware

```php
// Full authentication (signature verification + credential validation)
Route::middleware('hmac.auth')->group(function () {
    Route::get('/api/protected', [ApiController::class, 'index']);
});

// Signature verification only (no credential lookup)
Route::middleware('hmac.verify')->group(function () {
    Route::post('/webhooks', [WebhookController::class, 'handle']);
});
```

### Global Middleware (Optional)

If you want HMAC authentication on all API routes, add to `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \HmacAuth\Middleware\HmacAuthMiddleware::class,
    ]);
})
```

## Generating Credentials

After installation, generate your first API credentials:

```bash
php artisan hmac:generate --company=1 --environment=production
```

This outputs:
- **Client ID**: Use in the `X-Api-Key` header
- **Client Secret**: Use for signing requests (store securely!)

## Verification

Verify the installation is working:

### 1. Check Configuration

```bash
php artisan config:show hmac
```

### 2. Check Database Tables

```bash
php artisan db:table api_credentials
php artisan db:table api_request_logs
```

### 3. Test Authentication

Create a test route:

```php
Route::middleware('hmac.auth')->get('/api/test', function () {
    return response()->json(['status' => 'authenticated']);
});
```

Send a properly signed request and verify you receive a 200 response.

## Next Steps

- [Configuration Reference](configuration.md) - Customize settings
- [Client Implementation](client-implementation.md) - Implement signing in your clients
- [Artisan Commands](artisan-commands.md) - Manage credentials via CLI
