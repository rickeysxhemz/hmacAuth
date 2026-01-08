# API Reference

Complete reference for services, DTOs, enums, and events provided by the package.

## Facade

### `Hmac`

The main entry point for programmatic access.

```php
use HmacAuth\Facades\Hmac;

// Verify a request
$result = Hmac::verify($request);

// Generate signature
$signature = Hmac::generateSignature($payload, $secret);

// Generate credentials
$credentials = Hmac::generateCredentials(
    companyId: 1,
    createdBy: 1,
    environment: 'production'
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

Main service for verifying HMAC-signed requests.

#### `verify(Request $request): VerificationResult`

Verifies an incoming request's HMAC signature.

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

Generates and verifies HMAC signatures.

#### `generate(SignaturePayload $payload, string $secret, string $algorithm = 'sha256'): string`

Generates an HMAC signature for a payload.

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

Compares signatures using timing-safe comparison.

```php
$isValid = $service->verify($expectedSignature, $actualSignature);
```

#### `isAlgorithmSupported(string $algorithm): bool`

Checks if an algorithm is supported.

```php
$supported = $service->isAlgorithmSupported('sha512'); // true
$supported = $service->isAlgorithmSupported('md5');    // false
```

#### `getSupportedAlgorithms(): array`

Returns list of supported algorithms.

```php
$algorithms = $service->getSupportedAlgorithms();
// ['sha256', 'sha384', 'sha512']
```

---

### `ApiCredentialService`

Manages API credentials.

#### `generate(int $companyId, int $createdBy, string $environment, ?CarbonInterface $expiresAt): array`

Creates new API credentials.

```php
use HmacAuth\Services\ApiCredentialService;

$service = app(ApiCredentialService::class);
$result = $service->generate(
    companyId: 1,
    createdBy: auth()->id(),
    environment: 'production',
    expiresAt: now()->addYear()
);

$credential = $result['credential'];   // ApiCredential model
$plainSecret = $result['plain_secret']; // Show this to user once!
```

#### `rotateSecret(ApiCredential $credential, int $graceDays = 7): array`

Rotates the secret for a credential.

```php
$result = $service->rotateSecret($credential, graceDays: 14);

$newSecret = $result['new_secret'];
$oldSecretExpiresAt = $result['old_secret_expires_at'];
```

---

### `SecureKeyGenerator`

Generates cryptographically secure random values.

#### `generateClientId(string $environment): string`

Generates a new client ID with environment prefix.

```php
use HmacAuth\Services\SecureKeyGenerator;

$generator = app(SecureKeyGenerator::class);
$clientId = $generator->generateClientId('production');
// "prod_a1b2c3d4e5f6g7h8"
```

#### `generateClientSecret(): string`

Generates a new client secret.

```php
$secret = $generator->generateClientSecret();
// 64-character hex string
```

#### `generateNonce(): string`

Generates a new nonce for request signing.

```php
$nonce = $generator->generateNonce();
// 32-character hex string
```

---

### `NonceStore`

Manages nonce storage for replay attack prevention.

#### `exists(string $nonce): bool`

Checks if a nonce has been used.

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

#### `clear(string $nonce): void`

Removes a nonce from storage.

```php
$store->clear($nonce);
```

---

### `RateLimiterService`

Manages per-client rate limiting.

#### `isLimited(string $clientId): bool`

Checks if a client is rate limited.

```php
use HmacAuth\Contracts\RateLimiterInterface;

$limiter = app(RateLimiterInterface::class);
$isLimited = $limiter->isLimited($clientId);
```

#### `recordFailure(string $clientId): void`

Records a failed authentication attempt.

```php
$limiter->recordFailure($clientId);
```

#### `reset(string $clientId): void`

Resets the failure count for a client.

```php
$limiter->reset($clientId);
```

---

### `RequestLogger`

Logs authentication attempts for auditing.

#### `logSuccessfulAttempt(Request $request, ApiCredential $credential): void`

Logs a successful authentication.

#### `logFailedAttempt(Request $request, string $clientId, string $reason, ?ApiCredential $credential): void`

Logs a failed authentication attempt.

#### `hasExcessiveFailures(string $ipAddress): bool`

Checks if an IP has excessive failures and should be blocked.

---

## DTOs

### `SignaturePayload`

Represents the data used to generate a signature.

```php
use HmacAuth\DTOs\SignaturePayload;

// Create from components
$payload = new SignaturePayload(
    method: 'POST',
    path: '/api/data',
    body: '{"key":"value"}',
    timestamp: '1704067200',
    nonce: 'unique-nonce'
);

// Create from a request
$payload = SignaturePayload::fromRequest($request, $timestamp, $nonce);

// Get canonical string for signing
$canonical = $payload->toCanonicalString();
// "POST\n/api/data\n{\"key\":\"value\"}\n1704067200\nunique-nonce"
```

---

### `VerificationResult`

Represents the outcome of request verification.

```php
use HmacAuth\DTOs\VerificationResult;

// Check if valid
if ($result->isValid()) {
    $credential = $result->getCredential();
}

// Get failure reason
if (!$result->isValid()) {
    $reason = $result->getReason(); // VerificationFailureReason enum
}
```

---

### `HmacConfig`

Configuration value object passed to services.

```php
use HmacAuth\DTOs\HmacConfig;

$config = app(HmacConfig::class);

$config->enabled;            // bool
$config->algorithm;          // string
$config->timestampTolerance; // int
$config->nonceTtl;           // int
$config->apiKeyHeader;       // string
$config->signatureHeader;    // string
// ... etc
```

---

## Enums

### `HmacAlgorithm`

Supported HMAC algorithms.

```php
use HmacAuth\Enums\HmacAlgorithm;

HmacAlgorithm::SHA256; // 'sha256'
HmacAlgorithm::SHA384; // 'sha384'
HmacAlgorithm::SHA512; // 'sha512'

// Get default
$default = HmacAlgorithm::default(); // SHA256

// Parse from string
$algo = HmacAlgorithm::tryFromString('sha512'); // SHA512 or null

// List supported names
$names = HmacAlgorithm::supportedNames(); // ['sha256', 'sha384', 'sha512']
```

---

### `VerificationFailureReason`

All possible reasons for authentication failure.

```php
use HmacAuth\Enums\VerificationFailureReason;

VerificationFailureReason::MISSING_HEADERS;      // Required headers missing
VerificationFailureReason::INVALID_TIMESTAMP;    // Timestamp outside tolerance
VerificationFailureReason::BODY_TOO_LARGE;       // Request body exceeds limit
VerificationFailureReason::IP_BLOCKED;           // IP address blocked
VerificationFailureReason::RATE_LIMITED;         // Client rate limited
VerificationFailureReason::INVALID_NONCE;        // Nonce format invalid
VerificationFailureReason::DUPLICATE_NONCE;      // Nonce already used
VerificationFailureReason::INVALID_CLIENT_ID;    // Client ID not found
VerificationFailureReason::CREDENTIAL_EXPIRED;   // Credential has expired
VerificationFailureReason::ENVIRONMENT_MISMATCH; // Wrong environment
VerificationFailureReason::INVALID_SECRET;       // Secret is invalid
VerificationFailureReason::INVALID_SIGNATURE;    // Signature doesn't match

// Get HTTP status code for reason
$status = $reason->httpStatusCode(); // 401 or 403
```

---

## Events

### `AuthenticationSucceeded`

Dispatched when authentication succeeds.

```php
use HmacAuth\Events\AuthenticationSucceeded;

// In EventServiceProvider
protected $listen = [
    AuthenticationSucceeded::class => [
        LogSuccessfulAuth::class,
    ],
];

// In listener
public function handle(AuthenticationSucceeded $event): void
{
    $credential = $event->credential;
    $request = $event->request;

    Log::info('API authenticated', [
        'client_id' => $credential->client_id,
        'ip' => $request->ip(),
    ]);
}
```

---

### `AuthenticationFailed`

Dispatched when authentication fails.

```php
use HmacAuth\Events\AuthenticationFailed;

// In EventServiceProvider
protected $listen = [
    AuthenticationFailed::class => [
        AlertOnAuthFailure::class,
    ],
];

// In listener
public function handle(AuthenticationFailed $event): void
{
    $reason = $event->reason;    // VerificationFailureReason
    $clientId = $event->clientId;
    $request = $event->request;

    if ($reason === VerificationFailureReason::INVALID_SIGNATURE) {
        // Alert on signature failures
    }
}
```

---

## Contracts (Interfaces)

The package uses dependency injection with interfaces for testability:

| Interface | Default Implementation |
|-----------|----------------------|
| `HmacVerifierInterface` | `HmacVerificationService` |
| `SignatureServiceInterface` | `SignatureService` |
| `ApiCredentialRepositoryInterface` | `EloquentApiCredentialRepository` |
| `NonceStoreInterface` | `RedisNonceStore` |
| `RateLimiterInterface` | `RedisRateLimiter` |
| `RequestLoggerInterface` | `DatabaseRequestLogger` |

You can bind custom implementations in a service provider:

```php
$this->app->bind(
    NonceStoreInterface::class,
    CustomNonceStore::class
);
```
