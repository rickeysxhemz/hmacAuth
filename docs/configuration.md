# Configuration

Reference for `config/hmac.php`.

```bash
php artisan vendor:publish --tag=hmac-config
```

## Options

### General

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `enabled` | `true` | `HMAC_ENABLED` | Enable HMAC authentication |
| `algorithm` | `sha256` | `HMAC_ALGORITHM` | Hash algorithm (`sha256`, `sha384`, `sha512`) |

### Timing

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `timestamp_tolerance` | `300` | `HMAC_TIMESTAMP_TOLERANCE` | Max request age in seconds |
| `nonce_ttl` | `600` | `HMAC_NONCE_TTL` | Nonce storage duration (should be 2x tolerance) |
| `min_nonce_length` | `16` | `HMAC_MIN_NONCE_LENGTH` | Minimum nonce length |

### Limits

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `max_body_size` | `1048576` | `HMAC_MAX_BODY_SIZE` | Max body size in bytes (1 MB) |

### Rate Limiting

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `rate_limit_max_attempts` | `5` | `HMAC_RATE_LIMIT_MAX_ATTEMPTS` | Failed attempts before limiting |
| `rate_limit_decay_seconds` | `60` | `HMAC_RATE_LIMIT_DECAY_SECONDS` | Reset window in seconds |

### IP Blocking

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `ip_blocking_threshold` | `10` | `HMAC_IP_BLOCKING_THRESHOLD` | Failed attempts before blocking |
| `ip_blocking_duration` | `3600` | `HMAC_IP_BLOCKING_DURATION` | Block duration in seconds (1 hour) |

### Environment

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `enforce_environment` | `true` | `HMAC_ENFORCE_ENVIRONMENT` | Require credential/app environment match |

### Redis

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `redis.connection` | `default` | `HMAC_REDIS_CONNECTION` | Connection name from `database.php` |
| `redis.prefix` | `hmac:` | `HMAC_REDIS_PREFIX` | Key prefix |

### Headers

| Option | Default | Env |
|--------|---------|-----|
| `headers.api_key` | `X-Api-Key` | `HMAC_HEADER_API_KEY` |
| `headers.signature` | `X-Signature` | `HMAC_HEADER_SIGNATURE` |
| `headers.timestamp` | `X-Timestamp` | `HMAC_HEADER_TIMESTAMP` |
| `headers.nonce` | `X-Nonce` | `HMAC_HEADER_NONCE` |

### Multi-Tenancy

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `tenancy.enabled` | `false` | `HMAC_TENANCY_ENABLED` | Enable tenant scoping |
| `tenancy.column` | `tenant_id` | `HMAC_TENANT_COLUMN` | Foreign key column name |
| `tenancy.model` | `App\Models\Tenant` | `HMAC_TENANT_MODEL` | Tenant model class |

### Models

| Option | Default | Env | Description |
|--------|---------|-----|-------------|
| `models.user` | `App\Models\User` | `HMAC_USER_MODEL` | User model class |

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
    'enabled' => env('HMAC_AUTH_ENABLED', true),
    'algorithm' => env('HMAC_ALGORITHM', 'sha256'),

    'timestamp_tolerance' => env('HMAC_TIMESTAMP_TOLERANCE', 300),

    'nonce_ttl' => env('HMAC_NONCE_TTL', 600),
    'min_nonce_length' => env('HMAC_MIN_NONCE_LENGTH', 32),

    'max_body_size' => env('HMAC_MAX_BODY_SIZE', 1048576),

    'enforce_environment' => env('HMAC_ENFORCE_ENVIRONMENT', true),

    'rate_limit' => [
        'enabled' => env('HMAC_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('HMAC_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('HMAC_RATE_LIMIT_DECAY', 1),
    ],

    'ip_blocking' => [
        'enabled' => env('HMAC_IP_BLOCKING_ENABLED', true),
        'threshold' => env('HMAC_IP_FAILURE_THRESHOLD', 10),
        'window_minutes' => env('HMAC_IP_FAILURE_WINDOW', 10),
    ],

    'redis' => [
        'connection' => env('HMAC_REDIS_CONNECTION', 'default'),
        'prefix' => env('HMAC_REDIS_PREFIX', 'hmac:'),
        'fail_on_error' => env('HMAC_REDIS_STRICT', false),
    ],

    'headers' => [
        'api-key' => 'X-Api-Key',
        'signature' => 'X-Signature',
        'timestamp' => 'X-Timestamp',
        'nonce' => 'X-Nonce',
    ],

    'tenancy' => [
        'enabled' => env('HMAC_TENANCY_ENABLED', false),
        'column' => env('HMAC_TENANT_COLUMN', 'tenant_id'),
        'model' => env('HMAC_TENANT_MODEL', 'App\\Models\\Tenant'),
    ],

    'models' => [
        'user' => env('HMAC_USER_MODEL', 'App\\Models\\User'),
    ],
];
```
