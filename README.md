# Laravel HMAC Authentication

HMAC-based API authentication for Laravel 11/12.

## Install

```bash
composer require hmacauth/laravel-hmac-auth
php artisan hmac:install
php artisan migrate
php artisan hmac:generate
```

## Protect Routes

```php
Route::middleware('hmac.verify')->group(function () {
    Route::post('/api/resource', ResourceController::class);
});
```

## Documentation

See [docs/](docs/) for full documentation:

- [Getting Started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Client Examples](docs/clients/)
- [API Reference](docs/api-reference.md)
- [Commands](docs/commands.md)
- [Security](docs/security.md)
- [Troubleshooting](docs/troubleshooting.md)

## Requirements

- PHP 8.3+
- Laravel 11.x / 12.x
- Redis

## License

MIT
