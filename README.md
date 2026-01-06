# Laravel HMAC Authentication

Enterprise-grade HMAC-based API authentication for Laravel 11/12 with Octane support.

## Features

- **Cryptographically Secure**: 384-bit entropy secrets with AES-256 encryption at rest
- **Replay Attack Prevention**: Redis-based nonce tracking and timestamp validation
- **Rate Limiting**: Atomic rate limiting on failed authentication attempts
- **IP-Based Blocking**: Automatic blocking after excessive failures
- **Secret Rotation**: 7-day overlap period for zero-downtime credential migration
- **Cache Stampede Protection**: Lock-based credential lookups prevent thundering herd
- **Comprehensive Logging**: Full audit trail with truncation protection
- **Multi-Tenant Support**: Company-scoped credentials
- **Octane Compatible**: Stateless, readonly services with no memory leaks
- **SOLID Architecture**: Interface-driven design with DTOs and enums

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- Redis (for nonce storage)

## Installation

### 1. Install via Composer

```bash
composer require hmacauth/laravel-hmac-auth
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=hmac-config
```

### 3. Publish Migrations

```bash
php artisan vendor:publish --tag=hmac-migrations
php artisan migrate
```

### 4. Publish Views (Optional)

```bash
php artisan vendor:publish --tag=hmac-views
```

### 5. Publish CSS Assets (Optional)

```bash
php artisan vendor:publish --tag=hmac-assets
```

### 6. Publish All Resources

```bash
php artisan vendor:publish --tag=hmac-auth
```

### 7. Configure Redis

Ensure Redis is configured in your `.env`:

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Configuration

The configuration file will be published to `config/hmac.php`. Key options:

```php
return [
    'enabled' => env('HMAC_AUTH_ENABLED', true),
    'algorithm' => env('HMAC_ALGORITHM', 'sha256'),
    'key_prefix' => env('HMAC_KEY_PREFIX', 'hmac'),
    'timestamp_tolerance' => env('HMAC_TIMESTAMP_TOLERANCE', 300),
    'nonce_ttl' => env('HMAC_NONCE_TTL', 600),

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

    // Configure your model classes
    'models' => [
        'company' => 'App\\Models\\Company',
        'user' => 'App\\Models\\User',
    ],
];
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `HMAC_AUTH_ENABLED` | `true` | Enable/disable HMAC authentication |
| `HMAC_ALGORITHM` | `sha256` | HMAC algorithm (sha256, sha384, sha512) |
| `HMAC_KEY_PREFIX` | `hmac` | Prefix for generated client IDs |
| `HMAC_TIMESTAMP_TOLERANCE` | `300` | Timestamp tolerance in seconds |
| `HMAC_NONCE_TTL` | `600` | Nonce TTL in Redis (seconds) |
| `HMAC_RATE_LIMIT_ENABLED` | `true` | Enable per-client rate limiting |
| `HMAC_RATE_LIMIT_ATTEMPTS` | `60` | Max failed attempts before rate limiting |
| `HMAC_IP_BLOCKING_ENABLED` | `true` | Enable IP-based blocking |
| `HMAC_IP_FAILURE_THRESHOLD` | `10` | Failed attempts before IP blocked |
| `HMAC_REDIS_STRICT` | `false` | Fail on Redis errors |

## Usage

### Protecting Routes

Add the `hmac.verify` middleware to routes that require authentication:

```php
Route::middleware(['hmac.verify'])->group(function () {
    Route::post('/api/search', [SearchController::class, 'search']);
    Route::post('/api/bookings', [BookingController::class, 'store']);
});
```

### Generating Credentials

```php
use HmacAuth\Services\ApiCredentialService;
use HmacAuth\Models\ApiCredential;

$service = app(ApiCredentialService::class);

$result = $service->generate(
    companyId: 1,
    createdBy: auth()->id(),
    environment: ApiCredential::ENVIRONMENT_TESTING,
    expiresAt: now()->addYear()
);

$clientId = $result['credential']->client_id;
$clientSecret = $result['plain_secret']; // Save immediately - shown only once
```

### Rotating Secrets

```php
$result = $service->rotateSecret($credential);
$newSecret = $result['plain_secret'];
// Old secret remains valid for 7 days
```

### Accessing Authenticated Credential

In your controller, access the authenticated credential:

```php
public function index(Request $request)
{
    $credential = $request->attributes->get('hmac_credential');
    $companyId = $request->attributes->get('company_id');

    // Use $companyId for tenant scoping
}
```

## Client Implementation

### Required Headers

Every authenticated request must include these headers:

| Header | Description |
|--------|-------------|
| `X-Api-Key` | Client ID |
| `X-Signature` | HMAC signature (Base64URL encoded) |
| `X-Timestamp` | Unix timestamp |
| `X-Nonce` | Unique request identifier (min 32 chars) |

### Signature Generation

The signature payload format:

```
METHOD\n
PATH\n
BODY\n
TIMESTAMP\n
NONCE
```

**PHP Example:**

```php
function generateHmacSignature(
    string $method,
    string $path,
    string $body,
    string $timestamp,
    string $nonce,
    string $secret
): string {
    $payload = implode("\n", [
        strtoupper($method),
        $path,
        $body,
        $timestamp,
        $nonce
    ]);

    $hmac = hash_hmac('sha256', $payload, $secret, true);

    // Base64URL encoding (no padding)
    return rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');
}

$nonce = bin2hex(random_bytes(16)); // 32 characters
$timestamp = (string) time();

$signature = generateHmacSignature(
    'POST',
    '/api/search',
    json_encode(['query' => 'test']),
    $timestamp,
    $nonce,
    $clientSecret
);
```

**JavaScript Example:**

```javascript
const crypto = require('crypto');

function generateHmacSignature(method, path, body, timestamp, nonce, secret) {
    const payload = [
        method.toUpperCase(),
        path,
        body,
        timestamp,
        nonce
    ].join('\n');

    return crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('base64')
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

const nonce = crypto.randomBytes(16).toString('hex');
const timestamp = Math.floor(Date.now() / 1000).toString();
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: hmac_live_abc123..." \
  -H "X-Signature: generated_signature" \
  -H "X-Timestamp: 1697123456" \
  -H "X-Nonce: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6" \
  -d '{"query": "test"}'
```

## Events

The package dispatches events for monitoring:

```php
use HmacAuth\Events\AuthenticationSucceeded;
use HmacAuth\Events\AuthenticationFailed;

// In your EventServiceProvider
protected $listen = [
    AuthenticationSucceeded::class => [
        LogSuccessfulAuth::class,
    ],
    AuthenticationFailed::class => [
        AlertOnFailedAuth::class,
    ],
];
```

## Architecture

```
src/
├── Concerns/           # Reusable traits
├── Contracts/          # Interface definitions
├── DTOs/               # Data transfer objects
├── Enums/              # Type-safe enumerations
├── Events/             # Domain events
├── Exceptions/         # Custom exceptions
├── Http/Middleware/    # HMAC verification middleware
├── Models/             # Eloquent models
├── Repositories/       # Data access layer
├── Services/           # Business logic
└── HmacAuthServiceProvider.php
```

### Key Components

| Component | Purpose |
|-----------|---------|
| `HmacVerificationService` | Main verification orchestrator |
| `ApiCredentialService` | Credential management |
| `SignatureService` | HMAC signature generation/verification |
| `NonceStore` | Redis-based replay attack prevention |
| `RateLimiterService` | Failed attempt rate limiting |
| `RequestLogger` | Audit trail logging |

## Security Considerations

1. **Store secrets securely** - Use environment variables or secret managers
2. **HTTPS only** - HMAC authenticates but does not encrypt
3. **Rotate regularly** - Use the built-in rotation with 7-day overlap
4. **Monitor logs** - Watch `api_request_logs` for anomalies
5. **Set expiration** - Credentials should have finite lifetime
6. **One credential per integration** - Easier revocation
7. **Enable Redis strict mode** - Set `HMAC_REDIS_STRICT=true` in production

## Troubleshooting

| Error | Solution |
|-------|----------|
| Missing required headers | Ensure all 4 headers are present |
| Invalid timestamp | Synchronize client/server clocks (tolerance: ±5 min) |
| Nonce too short | Nonce must be at least 32 characters |
| Duplicate nonce | Each request needs a unique nonce |
| Invalid signature | Verify payload order and Base64URL encoding |

## License

MIT License. See [LICENSE](LICENSE) for details.
