# Laravel HMAC Auth Documentation

Welcome to the Laravel HMAC Auth documentation. This package provides secure HMAC-based API authentication for Laravel applications.

## Table of Contents

### Getting Started

- [Installation](installation.md) - Setup and installation guide
- [Configuration](configuration.md) - Complete configuration reference

### Usage

- [Artisan Commands](artisan-commands.md) - CLI commands for credential management
- [Client Implementation](client-implementation.md) - Client-side integration examples

### Reference

- [API Reference](api-reference.md) - Services, DTOs, and interfaces

### Security & Operations

- [Security Best Practices](security-best-practices.md) - Recommendations for secure deployment
- [Troubleshooting](troubleshooting.md) - Common issues and solutions
- [Migration Guide](migration-guide.md) - Version upgrade instructions

## Version Compatibility

| Package Version | PHP Version | Laravel Version |
|-----------------|-------------|-----------------|
| 1.x             | 8.2 - 8.4   | 11.x - 12.x     |

## Quick Start

### 1. Install the package

```bash
composer require hmacauth/laravel-hmac-auth
```

### 2. Run the install command

```bash
# Standalone mode (default)
php artisan hmac:install

# Or with multi-tenancy support
php artisan hmac:install --with-tenancy --tenant-column=tenant_id
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Generate API credentials

```bash
# Standalone mode
php artisan hmac:generate --environment=production

# With tenancy enabled
php artisan hmac:generate --tenant=1 --environment=production
```

### 5. Protect your routes

```php
Route::middleware('hmac.verify')->group(function () {
    Route::get('/api/protected', [ApiController::class, 'index']);
});
```

### 6. Implement client-side signing

See [Client Implementation](client-implementation.md) for examples in PHP, JavaScript, Python, and cURL.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                           Client Request                             │
│  Headers: X-Api-Key, X-Signature, X-Timestamp, X-Nonce              │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      VerifyHmacSignature Middleware                  │
│  - Extracts headers from request                                     │
│  - Delegates to HmacVerificationService                             │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     HmacVerificationService                         │
│  1. Validate headers present                                         │
│  2. Check timestamp freshness                                        │
│  3. Check body size limits                                           │
│  4. Check IP not blocked                                             │
│  5. Check rate limits                                                │
│  6. Validate nonce format and uniqueness                            │
│  7. Look up credential by client ID                                  │
│  8. Verify credential not expired                                    │
│  9. Verify environment matches (optional)                            │
│  10. Verify signature (with rotation support)                        │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                          ┌────────┴────────┐
                          ▼                 ▼
                    ┌──────────┐      ┌──────────┐
                    │ Success  │      │ Failure  │
                    └──────────┘      └──────────┘
                          │                 │
                          ▼                 ▼
                    Store nonce       Log failure
                    Mark used         Record rate limit
                    Log success       Dispatch event
                    Reset limits      Return 401/429
                    Dispatch event
                    Set tenant_id*
                    Continue request

* tenant_id is set on request attributes when tenancy is enabled
```

## Standalone vs Multi-Tenant Mode

The package supports two operational modes:

### Standalone Mode (Default)

- No tenant scoping required
- Credentials work globally
- Simpler setup for single-tenant applications

### Multi-Tenant Mode

- Credentials scoped to tenants
- Configurable tenant column name
- Enable with `--with-tenancy` flag during install
- Or add later with `php artisan hmac:setup-tenancy`

See [Configuration](configuration.md) for detailed tenancy setup.

## Support

- **Documentation Issues**: Open an issue with the `documentation` label
- **Bug Reports**: Use the Bug Report template
- **Feature Requests**: Use the Feature Request template
- **Security Issues**: See [SECURITY.md](../SECURITY.md)
