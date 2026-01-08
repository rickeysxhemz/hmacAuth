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
composer require your-vendor/laravel-hmac-auth
```

### 2. Publish configuration and run migrations

```bash
php artisan hmac:install
```

### 3. Generate API credentials

```bash
php artisan hmac:generate --company=1 --environment=production
```

### 4. Protect your routes

```php
Route::middleware('hmac.auth')->group(function () {
    Route::get('/api/protected', [ApiController::class, 'index']);
});
```

### 5. Implement client-side signing

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
│                        HmacAuthMiddleware                           │
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
                    Reset limits      Return 401/403
                    Dispatch event
                    Continue request
```

## Support

- **Documentation Issues**: Open an issue with the `documentation` label
- **Bug Reports**: Use the [Bug Report template](https://github.com/your-username/laravel-hmac-auth/issues/new?template=bug_report.yml)
- **Feature Requests**: Use the [Feature Request template](https://github.com/your-username/laravel-hmac-auth/issues/new?template=feature_request.yml)
- **Security Issues**: See [SECURITY.md](../SECURITY.md)
