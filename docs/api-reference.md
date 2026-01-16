# API Reference

## Facade

### `Hmac`

```php
use HmacAuth\Facades\Hmac;

// Verify a request
$result = Hmac::verify($request);

// Generate signature
$signature = Hmac::generateSignature($payload, $secret);

// Generate credentials
$credentials = Hmac::generateCredentials(
    createdBy: 1,
    environment: 'production',
    tenantId: 1 // only when tenancy enabled
);

// Rotate secret
$result = Hmac::rotateSecret($credential, graceDays: 7);

// Generate utilities
$clientId = Hmac::generateClientId('production');
$secret = Hmac::generateClientSecret();
$nonce = Hmac::generateNonce();
```

---

## Services

### `HmacVerificationService`

#### `verify(Request $request): VerificationResult`

```php
use HmacAuth\Services\HmacVerificationService;

$service = app(HmacVerificationService::class);
$result = $service->verify($request);

if ($result->isValid()) {
    $credential = $result->getCredential();
    // Process authenticated request
} else {
    $reason = $result->getReason();
    // Handle failure
}
```

---

### `SignatureService`

#### `generate(SignaturePayload $payload, string $secret, string $algorithm = 'sha256'): string`

```php
use HmacAuth\Services\SignatureService;
use HmacAuth\DTOs\SignaturePayload;

$service = app(SignatureService::class);
$payload = new SignaturePayload(
    method: 'POST',
    path: '/api/data',
    body: '{"key":"value"}',
    timestamp: (string) time(),
    nonce: 'unique-nonce-123'
);

$signature = $service->generate($payload, $secret, 'sha256');
```

#### `verify(string $expected, string $actual): bool`

Timing-safe comparison.

```php
$isValid = $service->verify($expectedSignature, $actualSignature);
```

#### `isAlgorithmSupported(string $algorithm): bool`

```php
$service->isAlgorithmSupported('sha512'); // true
$service->isAlgorithmSupported('md5');    // false
```

#### `getSupportedAlgorithms(): array`

```php
$service->getSupportedAlgorithms(); // ['sha256', 'sha384', 'sha512']
```

---

### `ApiCredentialService`

#### `generate(int $createdBy, string $environment, ?CarbonInterface $expiresAt, int|string|null $tenantId): array`

```php
use HmacAuth\Services\ApiCredentialService;

$service = app(ApiCredentialService::class);
$result = $service->generate(
    createdBy: auth()->id(),
    environment: 'production',
    expiresAt: now()->addYear(),
    tenantId: 1 // only required when tenancy enabled
);

$credential = $result['credential'];
$plainSecret = $result['plain_secret']; // Show once, cannot retrieve later
```

#### `rotateSecret(ApiCredential $credential, int $graceDays = 7): array`

```php
$result = $service->rotateSecret($credential, graceDays: 14);

$newSecret = $result['new_secret'];
$oldSecretExpiresAt = $result['old_secret_expires_at'];
```

---

### `SecureKeyGenerator`

#### `generateClientId(string $environment): string`

```php
use HmacAuth\Services\SecureKeyGenerator;

$generator = app(SecureKeyGenerator::class);
$generator->generateClientId('production'); // "prod_a1b2c3d4e5f6g7h8"
```

#### `generateClientSecret(): string`

```php
$generator->generateClientSecret(); // 64-char hex
```

#### `generateNonce(): string`

```php
$generator->generateNonce(); // 32-char hex
```

---

### `NonceStore`

#### `exists(string $nonce): bool`

```php
use HmacAuth\Contracts\NonceStoreInterface;

$store = app(NonceStoreInterface::class);
$alreadyUsed = $store->exists($nonce);
```

#### `store(string $nonce): void`

Stores a nonce to prevent reuse.

```php
$store->store($nonce);
```

#### `clear(): void`

Clears all nonces from storage. Only available in non-production environments for testing purposes.

```php
// Only in testing/development
$store->clear();
```

> **Warning**: This method throws `RuntimeException` if called in production.

---

### `RateLimiterService`

#### `isLimited(string $clientId): bool`

```php
use HmacAuth\Contracts\RateLimiterInterface;

$limiter = app(RateLimiterInterface::class);
$isLimited = $limiter->isLimited($clientId);
```

#### `recordFailure(string $clientId): void`

#### `reset(string $clientId): void`

---

### `RequestLogger`

#### `logSuccessfulAttempt(Request $request, ApiCredential $credential): void`

#### `logFailedAttempt(Request $request, string $clientId, string $reason, ?ApiCredential $credential): void`

#### `hasExcessiveFailures(string $ipAddress): bool`

---

## DTOs

### `SignaturePayload`

```php
use HmacAuth\DTOs\SignaturePayload;

$payload = new SignaturePayload(
    method: 'POST',
    path: '/api/data',
    body: '{"key":"value"}',
    timestamp: '1704067200',
    nonce: 'unique-nonce'
);

// Or from request
$payload = SignaturePayload::fromRequest($request, $timestamp, $nonce);

$payload->toCanonicalString();
// "POST\n/api/data\n{\"key\":\"value\"}\n1704067200\nunique-nonce"
```

---

### `VerificationResult`

```php
if ($result->isValid()) {
    $credential = $result->getCredential();
} else {
    $reason = $result->getReason(); // VerificationFailureReason enum
}
```

---

### `HmacConfig`

Immutable configuration DTO. Access via `app(HmacConfig::class)`.

| Property | Type | Description |
|----------|------|-------------|
| `enabled` | bool | HMAC enabled |
| `algorithm` | string | Default algorithm |
| `timestampTolerance` | int | Tolerance in seconds |
| `nonceTtl` | int | Nonce TTL |
| `maxBodySize` | int | Max body bytes |
| `apiKeyHeader` | string | Header name |
| `signatureHeader` | string | Header name |
| `timestampHeader` | string | Header name |
| `nonceHeader` | string | Header name |
| `rateLimitEnabled` | bool | Rate limiting on |
| `rateLimitMaxAttempts` | int | Max attempts |
| `enforceEnvironment` | bool | Environment check |
| `tenancyEnabled` | bool | Multi-tenancy on |
| `tenancyColumn` | string | Tenant FK column |
| `tenancyModel` | string | Tenant model class |

---

## Enums

### `HmacAlgorithm`

```php
HmacAlgorithm::SHA256;  // 'sha256'
HmacAlgorithm::SHA384;  // 'sha384'
HmacAlgorithm::SHA512;  // 'sha512'

HmacAlgorithm::default();           // SHA256
HmacAlgorithm::tryFromString('sha512');
HmacAlgorithm::supportedNames();    // ['sha256', 'sha384', 'sha512']
```

---

### `VerificationFailureReason`

| Reason | HTTP | Description |
|--------|------|-------------|
| `MISSING_HEADERS` | 401 | Required headers missing |
| `INVALID_TIMESTAMP` | 401 | Timestamp outside tolerance |
| `BODY_TOO_LARGE` | 413 | Body exceeds limit |
| `IP_BLOCKED` | 403 | IP blocked |
| `RATE_LIMITED` | 429 | Rate limited |
| `INVALID_NONCE` | 401 | Bad nonce format |
| `DUPLICATE_NONCE` | 401 | Nonce reused |
| `INVALID_CLIENT_ID` | 401 | Client not found |
| `CREDENTIAL_EXPIRED` | 401 | Expired |
| `ENVIRONMENT_MISMATCH` | 403 | Wrong environment |
| `INVALID_SIGNATURE` | 401 | Signature mismatch |

---

## Events

### `AuthenticationSucceeded`

```php
// Properties: $credential, $request
Event::listen(AuthenticationSucceeded::class, function ($event) {
    Log::info('Auth success', ['client' => $event->credential->client_id]);
});
```

### `AuthenticationFailed`

```php
// Properties: $reason, $clientId, $request
Event::listen(AuthenticationFailed::class, function ($event) {
    Log::warning('Auth failed', ['reason' => $event->reason->value]);
});
```

---

## Contracts

| Interface | Implementation |
|-----------|----------------|
| `HmacVerifierInterface` | `HmacVerificationService` |
| `SignatureServiceInterface` | `SignatureService` |
| `ApiCredentialRepositoryInterface` | `EloquentApiCredentialRepository` |
| `NonceStoreInterface` | `NonceStore` |
| `RateLimiterInterface` | `RateLimiterService` |
| `RequestLoggerInterface` | `RequestLogger` |

Override in a service provider:

```php
$this->app->bind(NonceStoreInterface::class, CustomNonceStore::class);
```
