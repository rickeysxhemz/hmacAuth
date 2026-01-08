# Security Best Practices

This guide covers recommendations for secure deployment and operation of HMAC authentication.

## Secret Management

### Storage

**Do:**
- Store client secrets in secure vault systems (HashiCorp Vault, AWS Secrets Manager, Azure Key Vault)
- Use environment variables on the client side
- Encrypt secrets at rest
- Limit access to secrets using IAM policies

**Don't:**
- Commit secrets to version control
- Log secrets or include them in error messages
- Store secrets in plain text files
- Share secrets via email or chat

### Example: Environment Variables

```bash
# .env (client-side)
HMAC_CLIENT_ID=prod_a1b2c3d4e5f6g7h8
HMAC_CLIENT_SECRET=your-secret-here
```

```php
// Access in code
$clientId = getenv('HMAC_CLIENT_ID');
$clientSecret = getenv('HMAC_CLIENT_SECRET');
```

---

## Transport Security

### Always Use HTTPS

HMAC headers are visible in transit. Without HTTPS, attackers can intercept:
- Client ID
- Signature
- Timestamp
- Nonce
- Request body

**Server Configuration:**

```nginx
# Nginx - Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
```

---

## Credential Rotation

### Schedule Regular Rotation

Rotate secrets on a schedule to limit exposure window:

| Environment | Rotation Frequency |
|-------------|-------------------|
| Production | Quarterly (90 days) |
| Staging | Monthly |
| Development | As needed |

### Rotation Process

1. **Announce** rotation to API consumers with grace period
2. **Generate** new secret: `php artisan hmac:rotate prod_client_id --grace-days=14`
3. **Distribute** new secret securely to consumers
4. **Monitor** for requests still using old secret
5. **Verify** all consumers migrated before grace period ends

### Automated Rotation

```php
// app/Console/Kernel.php
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $credentials = ApiCredential::where('environment', 'production')
        ->whereNull('old_client_secret')
        ->where('updated_at', '<', now()->subDays(90))
        ->get();

    foreach ($credentials as $credential) {
        // Rotate and notify
        $result = Hmac::rotateSecret($credential, graceDays: 14);

        // Send notification to credential owner
        Notification::send(
            $credential->company->admins,
            new SecretRotatedNotification($credential, $result)
        );
    }
})->quarterly();
```

---

## Environment Separation

### Use Separate Credentials

Never share credentials across environments:

| Environment | Client ID Prefix | Access |
|-------------|-----------------|--------|
| Production | `prod_` | Real data |
| Staging | `stg_` | Test data |
| Development | `dev_` | Local data |

### Enable Environment Enforcement

```php
// config/hmac.php
'enforce_environment' => true,
```

This prevents production credentials from working in staging/development and vice versa.

---

## Timestamp Configuration

### Balance Security and Usability

| Tolerance | Security | Usability | Use Case |
|-----------|----------|-----------|----------|
| 60 seconds | High | Requires tight clock sync | High-security APIs |
| 300 seconds | Medium | Reasonable tolerance | Standard APIs |
| 600 seconds | Lower | Accommodates clock drift | Mobile clients |

### Client Clock Synchronization

Clients should synchronize their clocks using NTP:

```bash
# Linux
timedatectl set-ntp true

# macOS
sudo sntp -sS time.apple.com
```

---

## Rate Limiting Tuning

### Recommended Settings

| Setting | Low Traffic | Medium Traffic | High Traffic |
|---------|-------------|----------------|--------------|
| `rate_limit_max_attempts` | 3 | 5 | 10 |
| `rate_limit_decay_seconds` | 60 | 60 | 30 |
| `ip_blocking_threshold` | 5 | 10 | 20 |
| `ip_blocking_duration` | 3600 | 1800 | 900 |

### Monitor Rate Limiting

Listen for `AuthenticationFailed` events:

```php
use HmacAuth\Events\AuthenticationFailed;
use HmacAuth\Enums\VerificationFailureReason;

class MonitorAuthFailures
{
    public function handle(AuthenticationFailed $event): void
    {
        if ($event->reason === VerificationFailureReason::RATE_LIMITED) {
            Log::warning('Client rate limited', [
                'client_id' => $event->clientId,
                'ip' => $event->request->ip(),
            ]);

            // Alert if this happens frequently
        }
    }
}
```

---

## Monitoring and Alerting

### Key Metrics to Monitor

1. **Authentication Success Rate** - Baseline and alert on drops
2. **Failed Authentication Attempts** - Alert on spikes
3. **Rate Limit Triggers** - May indicate attack or misconfiguration
4. **IP Blocks** - Review for legitimate vs malicious
5. **Credential Usage Patterns** - Detect anomalies

### Example Monitoring Setup

```php
use HmacAuth\Events\AuthenticationSucceeded;
use HmacAuth\Events\AuthenticationFailed;

// In EventServiceProvider
protected $listen = [
    AuthenticationSucceeded::class => [
        RecordSuccessMetric::class,
    ],
    AuthenticationFailed::class => [
        RecordFailureMetric::class,
        AlertOnSuspiciousActivity::class,
    ],
];

// RecordSuccessMetric.php
public function handle(AuthenticationSucceeded $event): void
{
    Metrics::increment('hmac.auth.success', [
        'client_id' => $event->credential->client_id,
        'environment' => $event->credential->environment,
    ]);
}

// AlertOnSuspiciousActivity.php
public function handle(AuthenticationFailed $event): void
{
    $recentFailures = Cache::increment("auth_failures:{$event->request->ip()}", 1);
    Cache::put("auth_failures:{$event->request->ip()}", $recentFailures, now()->addMinutes(5));

    if ($recentFailures > 20) {
        // Send alert
        Alert::send(new SuspiciousActivityAlert($event));
    }
}
```

---

## Audit Logging

### Review Logs Regularly

```bash
# Find failed attempts by client
php artisan tinker
>>> ApiRequestLog::where('status', 'failed')
...     ->where('client_id', 'prod_xxx')
...     ->latest()
...     ->take(100)
...     ->get(['created_at', 'ip_address', 'failure_reason']);

# Find patterns by IP
>>> ApiRequestLog::where('ip_address', '192.168.1.100')
...     ->where('status', 'failed')
...     ->groupBy('failure_reason')
...     ->selectRaw('failure_reason, count(*) as count')
...     ->get();
```

### Log Retention

Balance audit requirements with storage:

```php
// Clean old logs but keep summary
Schedule::command('hmac:cleanup --days=90')->daily();
```

---

## Security Checklist

### Initial Setup

- [ ] HTTPS enabled and enforced
- [ ] Redis secured with authentication
- [ ] Database credentials encrypted
- [ ] Environment enforcement enabled
- [ ] Appropriate timestamp tolerance set

### Ongoing Operations

- [ ] Credentials rotated quarterly
- [ ] Failed authentication alerts configured
- [ ] Rate limiting tuned for traffic patterns
- [ ] Audit logs reviewed monthly
- [ ] Dependencies updated regularly

### Incident Response

- [ ] Procedure for revoking compromised credentials
- [ ] Contact list for API consumers
- [ ] Runbook for handling attacks

---

## Common Vulnerabilities to Avoid

### Timing Attacks

The package uses `hash_equals()` for timing-safe comparison. If you implement custom verification, always use timing-safe comparison:

```php
// WRONG - vulnerable to timing attack
if ($expectedSignature === $actualSignature) { ... }

// RIGHT - timing-safe
if (hash_equals($expectedSignature, $actualSignature)) { ... }
```

### Replay Attacks

The package prevents replays using nonces. Ensure:
- Nonce TTL >= 2x timestamp tolerance
- Redis is properly configured and available

### Secret Leakage

Never log or expose secrets:

```php
// WRONG
Log::info('Request received', ['headers' => $request->headers->all()]);

// RIGHT
Log::info('Request received', [
    'client_id' => $request->header('X-Api-Key'),
    'path' => $request->path(),
]);
```
