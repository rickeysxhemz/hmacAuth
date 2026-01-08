# Troubleshooting

Common issues and their solutions when using Laravel HMAC Auth.

## Authentication Errors

### Invalid Signature (401)

**Symptoms:**
- Response: `{"error": "Invalid signature"}`
- Status code: 401
- Failure reason: `INVALID_SIGNATURE`

**Possible Causes:**

#### 1. Incorrect Payload Construction

The signature payload must be constructed in exact order:

```
METHOD\nPATH\nBODY\nTIMESTAMP\nNONCE
```

**Check:**
```php
// Payload should look like:
// POST
// /api/users
// {"name":"John"}
// 1704067200
// abc123...

$payload = implode("\n", [$method, $path, $body, $timestamp, $nonce]);
```

#### 2. Body Mismatch

The body used for signing must exactly match the body sent.

**Check:**
- No extra whitespace or newlines
- JSON uses consistent encoding (no pretty-print)
- Empty body = empty string, not `null` or `{}`

```php
// Wrong
$body = json_encode($data, JSON_PRETTY_PRINT);

// Right
$body = json_encode($data);

// For empty body
$body = '';  // Not 'null' or '{}'
```

#### 3. Path Issues

The path must include query parameters if present.

**Check:**
```php
// If request is GET /api/users?page=1

// Wrong
$path = '/api/users';

// Right
$path = '/api/users?page=1';
```

#### 4. Wrong Secret

Ensure you're using the correct secret for the client ID.

**Check:**
```bash
php artisan tinker
>>> $cred = ApiCredential::where('client_id', 'prod_xxx')->first();
>>> $cred->client_secret; // Note: This is encrypted
```

#### 5. Algorithm Mismatch

The signing algorithm must match the credential's algorithm.

**Check:**
```php
// Default is sha256, but credentials may use different algorithms
>>> $cred->hmac_algorithm; // 'sha256', 'sha384', or 'sha512'
```

---

### Invalid Timestamp (401)

**Symptoms:**
- Response: `{"error": "Invalid timestamp"}`
- Failure reason: `INVALID_TIMESTAMP`

**Possible Causes:**

#### 1. Clock Drift

Client and server clocks are out of sync.

**Check:**
```bash
# Server time
date +%s

# Compare with client time
# They should be within timestamp_tolerance (default: 300 seconds)
```

**Fix:**
```bash
# Sync client clock with NTP
sudo ntpdate pool.ntp.org

# Or use systemd
timedatectl set-ntp true
```

#### 2. Timezone Issues

Timestamps should be Unix timestamps (UTC), not local time.

**Check:**
```php
// Wrong - local time
$timestamp = strtotime('now');

// Right - UTC Unix timestamp
$timestamp = time();
```

#### 3. Tolerance Too Strict

The default tolerance is 300 seconds (5 minutes).

**Fix:**
```php
// config/hmac.php
'timestamp_tolerance' => 600, // Increase to 10 minutes
```

---

### Duplicate Nonce (401)

**Symptoms:**
- Response: `{"error": "Duplicate nonce"}`
- Failure reason: `DUPLICATE_NONCE`

**Possible Causes:**

#### 1. Nonce Reuse

Each request must have a unique nonce.

**Check:**
```php
// Ensure nonce is generated fresh for each request
$nonce = bin2hex(random_bytes(16)); // New each time
```

#### 2. Retry Without New Nonce

When retrying failed requests, generate a new nonce and timestamp.

**Fix:**
```php
function makeRequest($url, $data, $retries = 3) {
    for ($i = 0; $i < $retries; $i++) {
        // Generate NEW nonce and timestamp for each attempt
        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();

        $signature = generateSignature($data, $timestamp, $nonce);

        try {
            return sendRequest($url, $data, $signature, $timestamp, $nonce);
        } catch (Exception $e) {
            if ($i === $retries - 1) throw $e;
            sleep(1);
        }
    }
}
```

---

### Invalid Client ID (401)

**Symptoms:**
- Response: `{"error": "Invalid client ID"}`
- Failure reason: `INVALID_CLIENT_ID`

**Possible Causes:**

#### 1. Credential Not Found

The client ID doesn't exist in the database.

**Check:**
```bash
php artisan tinker
>>> ApiCredential::where('client_id', 'prod_xxx')->exists();
```

#### 2. Credential Inactive

The credential exists but is deactivated.

**Check:**
```bash
>>> ApiCredential::where('client_id', 'prod_xxx')->first()->is_active;
```

**Fix:**
```bash
>>> $cred = ApiCredential::where('client_id', 'prod_xxx')->first();
>>> $cred->is_active = true;
>>> $cred->save();
```

---

### Credential Expired (401)

**Symptoms:**
- Response: `{"error": "Credential expired"}`
- Failure reason: `CREDENTIAL_EXPIRED`

**Check:**
```bash
php artisan tinker
>>> $cred = ApiCredential::where('client_id', 'prod_xxx')->first();
>>> $cred->expires_at;  // Check expiration date
>>> $cred->isExpired(); // true/false
```

**Fix:**

Generate new credentials or extend expiration:
```bash
>>> $cred->expires_at = now()->addYear();
>>> $cred->save();
```

---

### Environment Mismatch (403)

**Symptoms:**
- Response: `{"error": "Environment mismatch"}`
- Failure reason: `ENVIRONMENT_MISMATCH`

**Cause:**

Credential environment doesn't match application environment.

**Check:**
```bash
php artisan tinker
>>> $cred = ApiCredential::where('client_id', 'prod_xxx')->first();
>>> $cred->environment; // 'production'
>>> config('app.env');  // Should match
```

**Fix Options:**

1. Use correct credentials for the environment
2. Disable environment enforcement (not recommended for production):
   ```php
   // config/hmac.php
   'enforce_environment' => false,
   ```

---

### Rate Limited (429)

**Symptoms:**
- Response: `{"error": "Rate limited"}`
- Status code: 429
- Failure reason: `RATE_LIMITED`

**Cause:**

Too many failed authentication attempts.

**Check:**
```bash
php artisan tinker
>>> $limiter = app(\HmacAuth\Contracts\RateLimiterInterface::class);
>>> $limiter->isLimited('prod_xxx');
```

**Fix:**

Wait for decay period to pass, or reset manually:
```bash
>>> $limiter->reset('prod_xxx');
```

---

### IP Blocked (403)

**Symptoms:**
- Response: `{"error": "IP blocked"}`
- Failure reason: `IP_BLOCKED`

**Cause:**

Too many failed attempts from the same IP address.

**Check:**
```bash
php artisan tinker
>>> $logger = app(\HmacAuth\Contracts\RequestLoggerInterface::class);
>>> $logger->hasExcessiveFailures('192.168.1.100');
```

**Fix:**

Clear the block in Redis:
```bash
redis-cli DEL hmac:ip_failures:192.168.1.100
```

---

## Redis Issues

### Connection Refused

**Symptoms:**
- Error: `Connection refused [tcp://127.0.0.1:6379]`

**Check:**
```bash
# Is Redis running?
redis-cli ping

# Check connection settings
cat .env | grep REDIS
```

**Fix:**
```bash
# Start Redis
sudo systemctl start redis

# Or with Docker
docker run -d -p 6379:6379 redis:latest
```

### Authentication Failed

**Symptoms:**
- Error: `NOAUTH Authentication required`

**Check:**
```bash
# Test Redis password
redis-cli -a your-password ping
```

**Fix:**
```env
REDIS_PASSWORD=your-password
```

---

## Database Issues

### Table Not Found

**Symptoms:**
- Error: `Table 'database.api_credentials' doesn't exist`

**Fix:**
```bash
# Publish and run migrations
php artisan vendor:publish --tag=hmac-migrations
php artisan migrate
```

### Encryption Errors

**Symptoms:**
- Error: `The payload is invalid`

**Cause:**

`APP_KEY` changed after credentials were created.

**Fix:**

Regenerate credentials with the new key:
```bash
php artisan hmac:generate --company=1
```

---

## Performance Issues

### Slow Authentication

**Possible Causes:**

1. **Redis latency** - Check Redis connection
2. **Database queries** - Check credential lookup
3. **Large request bodies** - Consider reducing `max_body_size`

**Debug:**
```php
// Enable query logging
DB::enableQueryLog();

// Make request...

// Check queries
dd(DB::getQueryLog());
```

### Memory Issues

**Cause:**

Large request bodies being processed.

**Fix:**
```php
// config/hmac.php
'max_body_size' => 524288, // Reduce to 512KB
```

---

## Debugging Tips

### Enable Debug Logging

```php
// AppServiceProvider.php
use HmacAuth\Events\AuthenticationFailed;

Event::listen(AuthenticationFailed::class, function ($event) {
    Log::debug('HMAC auth failed', [
        'reason' => $event->reason->value,
        'client_id' => $event->clientId,
        'ip' => $event->request->ip(),
        'path' => $event->request->path(),
        'headers' => [
            'timestamp' => $event->request->header('X-Timestamp'),
            'nonce' => $event->request->header('X-Nonce'),
        ],
    ]);
});
```

### Test Signature Generation

```php
use HmacAuth\Services\SignatureService;
use HmacAuth\DTOs\SignaturePayload;

$service = app(SignatureService::class);

$payload = new SignaturePayload(
    method: 'POST',
    path: '/api/test',
    body: '{"test":true}',
    timestamp: '1704067200',
    nonce: 'test-nonce-123'
);

// Generate expected signature
$expected = $service->generate($payload, 'your-secret');
echo "Expected: {$expected}\n";

// Compare with client signature
echo "Match: " . ($expected === $clientSignature ? 'yes' : 'no') . "\n";
```

### Verify Request Headers

```bash
# Use curl with verbose output
curl -v -X POST \
    -H "X-Api-Key: prod_xxx" \
    -H "X-Signature: $SIGNATURE" \
    -H "X-Timestamp: $TIMESTAMP" \
    -H "X-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -d '{"test":true}' \
    https://api.example.com/api/test
```
