# Laravel HMAC Authentication

Enterprise-grade HMAC-based API authentication for Laravel 11/12 with Octane support.

## Features

- **Cryptographically Secure** - 384-bit entropy secrets with AES-256 encryption at rest
- **Replay Attack Prevention** - Redis-based nonce tracking and timestamp validation
- **Rate Limiting & IP Blocking** - Automatic protection against brute force attacks
- **Secret Rotation** - Zero-downtime credential migration with 7-day overlap
- **Standalone or Multi-Tenant** - Works out of the box, optional tenant scoping
- **Octane Compatible** - Stateless services with no memory leaks
- **SOLID Architecture** - Interface-driven design with DTOs and enums

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- Redis (for nonce storage)

## Quick Start

### 1. Install

```bash
composer require hmacauth/laravel-hmac-auth
```

### 2. Setup

```bash
# Standalone mode (default)
php artisan hmac:install

# Or with multi-tenancy
php artisan hmac:install --with-tenancy
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Generate Credentials

```bash
# Standalone
php artisan hmac:generate

# With tenancy
php artisan hmac:generate --tenant=1
```

### 5. Protect Routes

```php
Route::middleware('hmac.verify')->group(function () {
    Route::post('/api/resource', [ApiController::class, 'store']);
});
```

### 6. Access Authenticated Context

```php
public function store(Request $request)
{
    $credential = $request->attributes->get('hmac_credential');

    // With tenancy enabled
    $tenantId = $request->attributes->get('tenant_id');
}
```

## Documentation

Full documentation is available in the [docs/](docs/) directory:

| Guide | Description |
|-------|-------------|
| [Installation](docs/installation.md) | Detailed setup instructions |
| [Configuration](docs/configuration.md) | All configuration options |
| [Artisan Commands](docs/artisan-commands.md) | CLI commands reference |
| [Client Implementation](docs/client-implementation.md) | PHP, JS, Python, cURL examples |
| [API Reference](docs/api-reference.md) | Services, DTOs, interfaces |
| [Security Best Practices](docs/security-best-practices.md) | Production recommendations |
| [Troubleshooting](docs/troubleshooting.md) | Common issues and solutions |
| [Migration Guide](docs/migration-guide.md) | Version upgrade instructions |

## Multi-Tenancy

The package supports both standalone and multi-tenant modes:

**Standalone (Default):** Credentials are not scoped to any tenant.

**Multi-Tenant:** Enable via configuration to scope credentials to tenants.

```bash
# Add tenancy to existing installation
php artisan hmac:setup-tenancy --column=tenant_id
```

```env
HMAC_TENANCY_ENABLED=true
HMAC_TENANT_COLUMN=tenant_id
HMAC_TENANT_MODEL=App\Models\Tenant
```

See [Configuration](docs/configuration.md) for details.

## Architecture

```
src/
├── Concerns/           # Reusable traits
├── Contracts/          # Interface definitions
├── DTOs/               # Data transfer objects
├── Enums/              # Type-safe enumerations
├── Events/             # Domain events
├── Http/Middleware/    # HMAC verification middleware
├── Models/             # Eloquent models
├── Repositories/       # Data access layer
├── Services/           # Business logic
└── HmacAuthServiceProvider.php
```

## License

MIT License. See [LICENSE](LICENSE) for details.
