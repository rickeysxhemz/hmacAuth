# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `HmacConfig`: Added tenancy properties (`tenancyEnabled`, `tenancyColumn`, `tenancyModel`, `databaseRedisPrefix`)
- `VerifyHmacSignature`: Injected `Dispatcher`, uses `HmacConfig` for tenancy
- `NonceStore`: Fixed prefix stripping bug, extracted `isTestingMode()`
- `EncodesBase64Url`: `base64UrlDecode()` returns `string|false`
- `HasTenantScoping`: Fixed infinite recursion in `setTenantIdAttribute()`
- Removed redundant docblocks from `ApiCredential`

### Fixed

- Stack overflow when setting tenant ID on models
- Redis prefix stripping could match mid-string

## [1.0.0] - 2026-01-08

### Added

- Initial release of Laravel HMAC Auth package
- HMAC signature verification with support for SHA-256, SHA-384, and SHA-512 algorithms
- API credential management with encrypted client secrets
- Replay attack prevention via Redis-backed nonce store
- Rate limiting per client ID with configurable thresholds
- IP-based blocking after excessive authentication failures
- Secret rotation with configurable grace period (default 7 days)
- Environment-based credential validation (production, staging, testing)
- Request logging for audit trails
- Events for authentication lifecycle:
  - `AuthenticationSucceeded` - Dispatched on successful authentication
  - `AuthenticationFailed` - Dispatched on failed authentication with reason
- Artisan commands for credential management:
  - `hmac:install` - Interactive setup wizard
  - `hmac:generate` - Generate new API credentials
  - `hmac:rotate` - Rotate credential secrets with grace period
  - `hmac:cleanup` - Clean up old request logs
- Middleware for protecting routes:
  - `hmac.auth` - Full HMAC authentication
  - `hmac.verify` - Signature verification only
- Facade `Hmac` for convenient access to all services
- Comprehensive configuration via `config/hmac.php`
- Database migrations for `api_credentials` and `api_request_logs` tables
- Support for Laravel 11.x and 12.x
- Support for PHP 8.2, 8.3, and 8.4
- PHPStan level 8 static analysis compliance
- 90%+ test coverage with Pest PHP

### Security

- Timing-safe signature comparison using `hash_equals()`
- Cryptographically secure random generation for client IDs, secrets, and nonces
- Encrypted storage of client secrets in database
- Configurable timestamp tolerance to prevent replay attacks
- Nonce uniqueness enforcement with TTL-based expiration

[Unreleased]: https://github.com/rickeysxhemz/hmacAuth/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/rickeysxhemz/hmacAuth/releases/tag/v1.0.0
