# Laravel HMAC Auth

HMAC-based API authentication for Laravel.

## Docs

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Artisan Commands](artisan-commands.md)
- [Client Implementation](client-implementation.md)
- [API Reference](api-reference.md)
- [Security](security-best-practices.md)
- [Troubleshooting](troubleshooting.md)
- [Migration Guide](migration-guide.md)

## Quick Start

```bash
composer require hmacauth/laravel-hmac-auth
php artisan hmac:install
php artisan hmac:generate --environment=production
```

```php
Route::middleware('hmac.verify')->group(function () {
    Route::get('/api/protected', [ApiController::class, 'index']);
});
```

## Compatibility

| Package | PHP | Laravel |
|---------|-----|---------|
| 1.x | 8.2 - 8.4 | 11.x - 12.x |

## Flow

```
Request → Middleware → HmacVerificationService
                              ↓
           1. Validate headers
           2. Check timestamp
           3. Check rate limits
           4. Verify nonce
           5. Lookup credential
           6. Verify signature
                              ↓
                    Success → Continue
                    Failure → 401/429
```

## Modes

**Standalone (default):** Credentials work globally.

**Multi-tenant:** Credentials scoped to tenants via configurable column.
