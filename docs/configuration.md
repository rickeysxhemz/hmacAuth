# Configuration

Complete reference for `config/hmac.php` settings.

## Publishing Configuration

```bash
php artisan vendor:publish --tag=hmac-config
```

## Configuration Options

### General Settings

#### `enabled`

Enable or disable HMAC authentication globally.

```php
'enabled' => env('HMAC_ENABLED', true),
```

- **Type**: `bool`
- **Default**: `true`
- **Environment**: `HMAC_ENABLED`

When disabled, the middleware passes all requests through without verification.

---

#### `algorithm`

Default HMAC algorithm for new credentials.

```php
'algorithm' => env('HMAC_ALGORITHM', 'sha256'),
```

- **Type**: `string`
- **Default**: `'sha256'`
- **Options**: `'sha256'`, `'sha384'`, `'sha512'`
- **Environment**: `HMAC_ALGORITHM`

Individual credentials can override this with their own algorithm setting.

---

### Timestamp Settings

#### `timestamp_tolerance`

Maximum age (in seconds) for request timestamps.

```php
'timestamp_tolerance' => env('HMAC_TIMESTAMP_TOLERANCE', 300),
```

- **Type**: `int`
- **Default**: `300` (5 minutes)
- **Environment**: `HMAC_TIMESTAMP_TOLERANCE`

Requests with timestamps older than this are rejected. Balance security (shorter) with clock drift tolerance (longer).

---

### Nonce Settings

#### `nonce_ttl`

How long (in seconds) nonces are stored to prevent replay attacks.

```php
'nonce_ttl' => env('HMAC_NONCE_TTL', 600),
```

- **Type**: `int`
- **Default**: `600` (10 minutes)
- **Environment**: `HMAC_NONCE_TTL`

Should be at least double the `timestamp_tolerance` to ensure nonces outlive valid timestamps.

---

#### `min_nonce_length`

Minimum required length for nonces.

```php
'min_nonce_length' => env('HMAC_MIN_NONCE_LENGTH', 16),
```

- **Type**: `int`
- **Default**: `16`
- **Environment**: `HMAC_MIN_NONCE_LENGTH`

Shorter nonces are rejected as a security measure.

---

### Body Size Limits

#### `max_body_size`

Maximum request body size (in bytes) that will be processed.

```php
'max_body_size' => env('HMAC_MAX_BODY_SIZE', 1048576),
```

- **Type**: `int`
- **Default**: `1048576` (1 MB)
- **Environment**: `HMAC_MAX_BODY_SIZE`

Requests with larger bodies are rejected. Adjust based on your API's needs.

---

### Rate Limiting

#### `rate_limit_max_attempts`

Maximum failed authentication attempts before rate limiting kicks in.

```php
'rate_limit_max_attempts' => env('HMAC_RATE_LIMIT_MAX_ATTEMPTS', 5),
```

- **Type**: `int`
- **Default**: `5`
- **Environment**: `HMAC_RATE_LIMIT_MAX_ATTEMPTS`

---

#### `rate_limit_decay_seconds`

Time window (in seconds) for rate limit tracking.

```php
'rate_limit_decay_seconds' => env('HMAC_RATE_LIMIT_DECAY_SECONDS', 60),
```

- **Type**: `int`
- **Default**: `60` (1 minute)
- **Environment**: `HMAC_RATE_LIMIT_DECAY_SECONDS`

After this period without failures, the failure count resets.

---

### IP Blocking

#### `ip_blocking_threshold`

Number of failed attempts from an IP before blocking.

```php
'ip_blocking_threshold' => env('HMAC_IP_BLOCKING_THRESHOLD', 10),
```

- **Type**: `int`
- **Default**: `10`
- **Environment**: `HMAC_IP_BLOCKING_THRESHOLD`

---

#### `ip_blocking_duration`

How long (in seconds) an IP remains blocked.

```php
'ip_blocking_duration' => env('HMAC_IP_BLOCKING_DURATION', 3600),
```

- **Type**: `int`
- **Default**: `3600` (1 hour)
- **Environment**: `HMAC_IP_BLOCKING_DURATION`

---

### Environment Enforcement

#### `enforce_environment`

Whether to enforce environment matching between credentials and application.

```php
'enforce_environment' => env('HMAC_ENFORCE_ENVIRONMENT', true),
```

- **Type**: `bool`
- **Default**: `true`
- **Environment**: `HMAC_ENFORCE_ENVIRONMENT`

When enabled, production credentials can only be used in production, etc.

---

### Redis Configuration

#### `redis.connection`

Redis connection name from `config/database.php`.

```php
'redis' => [
    'connection' => env('HMAC_REDIS_CONNECTION', 'default'),
    'prefix' => env('HMAC_REDIS_PREFIX', 'hmac:'),
],
```

- **Type**: `string`
- **Default**: `'default'`
- **Environment**: `HMAC_REDIS_CONNECTION`

---

#### `redis.prefix`

Prefix for all Redis keys used by HMAC Auth.

```php
'prefix' => env('HMAC_REDIS_PREFIX', 'hmac:'),
```

- **Type**: `string`
- **Default**: `'hmac:'`
- **Environment**: `HMAC_REDIS_PREFIX`

---

### Custom Header Names

#### `headers`

Customize the HTTP header names used for authentication.

```php
'headers' => [
    'api_key' => env('HMAC_HEADER_API_KEY', 'X-Api-Key'),
    'signature' => env('HMAC_HEADER_SIGNATURE', 'X-Signature'),
    'timestamp' => env('HMAC_HEADER_TIMESTAMP', 'X-Timestamp'),
    'nonce' => env('HMAC_HEADER_NONCE', 'X-Nonce'),
],
```

| Header | Default | Environment |
|--------|---------|-------------|
| API Key | `X-Api-Key` | `HMAC_HEADER_API_KEY` |
| Signature | `X-Signature` | `HMAC_HEADER_SIGNATURE` |
| Timestamp | `X-Timestamp` | `HMAC_HEADER_TIMESTAMP` |
| Nonce | `X-Nonce` | `HMAC_HEADER_NONCE` |

---

### Model Configuration

#### `models`

Customize the Eloquent models used by the package.

```php
'models' => [
    'api_credential' => \HmacAuth\Models\ApiCredential::class,
    'api_request_log' => \HmacAuth\Models\ApiRequestLog::class,
],
```

Override these if you need to extend the default models with custom functionality.

---

## Environment-Specific Configuration

### Production

```env
HMAC_ENABLED=true
HMAC_ALGORITHM=sha256
HMAC_TIMESTAMP_TOLERANCE=300
HMAC_NONCE_TTL=600
HMAC_ENFORCE_ENVIRONMENT=true
HMAC_RATE_LIMIT_MAX_ATTEMPTS=5
HMAC_IP_BLOCKING_THRESHOLD=10
```

### Development

```env
HMAC_ENABLED=true
HMAC_ALGORITHM=sha256
HMAC_TIMESTAMP_TOLERANCE=600
HMAC_NONCE_TTL=1200
HMAC_ENFORCE_ENVIRONMENT=false
HMAC_RATE_LIMIT_MAX_ATTEMPTS=100
HMAC_IP_BLOCKING_THRESHOLD=1000
```

### Testing

```env
HMAC_ENABLED=false
```

Or in `phpunit.xml`:

```xml
<env name="HMAC_ENABLED" value="false"/>
```

---

## Full Configuration Example

```php
<?php

return [
    'enabled' => env('HMAC_ENABLED', true),
    'algorithm' => env('HMAC_ALGORITHM', 'sha256'),

    'timestamp_tolerance' => env('HMAC_TIMESTAMP_TOLERANCE', 300),

    'nonce_ttl' => env('HMAC_NONCE_TTL', 600),
    'min_nonce_length' => env('HMAC_MIN_NONCE_LENGTH', 16),

    'max_body_size' => env('HMAC_MAX_BODY_SIZE', 1048576),

    'rate_limit_max_attempts' => env('HMAC_RATE_LIMIT_MAX_ATTEMPTS', 5),
    'rate_limit_decay_seconds' => env('HMAC_RATE_LIMIT_DECAY_SECONDS', 60),

    'ip_blocking_threshold' => env('HMAC_IP_BLOCKING_THRESHOLD', 10),
    'ip_blocking_duration' => env('HMAC_IP_BLOCKING_DURATION', 3600),

    'enforce_environment' => env('HMAC_ENFORCE_ENVIRONMENT', true),

    'redis' => [
        'connection' => env('HMAC_REDIS_CONNECTION', 'default'),
        'prefix' => env('HMAC_REDIS_PREFIX', 'hmac:'),
    ],

    'headers' => [
        'api_key' => env('HMAC_HEADER_API_KEY', 'X-Api-Key'),
        'signature' => env('HMAC_HEADER_SIGNATURE', 'X-Signature'),
        'timestamp' => env('HMAC_HEADER_TIMESTAMP', 'X-Timestamp'),
        'nonce' => env('HMAC_HEADER_NONCE', 'X-Nonce'),
    ],

    'models' => [
        'api_credential' => \HmacAuth\Models\ApiCredential::class,
        'api_request_log' => \HmacAuth\Models\ApiRequestLog::class,
    ],
];
```
