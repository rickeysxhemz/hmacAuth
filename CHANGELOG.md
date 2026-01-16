# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-16

### Features

- HMAC-based API authentication with signature verification
- Nonce storage using Laravel Cache (supports array, file, database, Redis, Memcached)
- Rate limiting per client
- IP blocking for excessive failed attempts
- Secret rotation with grace period
- Multi-tenancy support
- Request logging with configurable retention
- Artisan commands for credential management

### Configuration

```php
'cache' => [
    'store' => env('HMAC_CACHE_STORE', null), // null = use default cache store
    'prefix' => env('HMAC_CACHE_PREFIX', 'hmac:nonce:'),
],
```

### Dependencies

- **deps:** Bump github/codeql-action from 3 to 4 by @dependabot[bot]
- **deps:** Bump actions/labeler from 5 to 6 by @dependabot[bot]


