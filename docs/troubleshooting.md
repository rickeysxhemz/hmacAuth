# Troubleshooting

## Invalid Signature (401)

**Causes:**
- Payload order wrong (must be: `METHOD\nPATH\nBODY\nTIMESTAMP\nNONCE`)
- Body mismatch (no pretty-print, empty = `""` not `null`)
- Path missing query params (`/api/users?page=1` not `/api/users`)
- Wrong secret or algorithm

```php
$payload = implode("\n", [$method, $path, $body, $timestamp, $nonce]);
```

---

## Invalid Timestamp (401)

**Causes:**
- Clock drift > tolerance (default 300s)
- Using local time instead of Unix timestamp

**Fix:** Sync with NTP: `timedatectl set-ntp true`

---

## Duplicate Nonce (401)

**Cause:** Reusing nonce on retry.

**Fix:** Generate new nonce for each request:
```php
$nonce = bin2hex(random_bytes(16));
```

---

## Invalid Client ID (401)

**Check:**
```bash
php artisan tinker
>>> ApiCredential::where('client_id', 'prod_xxx')->first();
>>> $cred->is_active; // Must be true
```

---

## Credential Expired (401)

**Check:**
```bash
>>> $cred->expires_at;
>>> $cred->isExpired();
```

---

## Environment Mismatch (403)

Production credentials only work when `APP_ENV=production`.

**Fix:** Use correct credentials or disable enforcement:
```php
'enforce_environment' => false, // Not recommended
```

---

## Rate Limited (429)

**Reset:**
```bash
>>> app(RateLimiterInterface::class)->reset('prod_xxx');
```

---

## IP Blocked (403)

**Clear:**
```bash
redis-cli DEL hmac:ip_failures:192.168.1.100
```

---

## Redis Connection Failed

```bash
redis-cli ping  # Should return PONG
```

---

## Table Not Found

```bash
php artisan vendor:publish --tag=hmac-migrations
php artisan migrate
```

---

## Encryption Errors

`APP_KEY` changed after credentials created. Regenerate credentials.

---

## Debug

```php
Event::listen(AuthenticationFailed::class, function ($event) {
    Log::debug('HMAC failed', [
        'reason' => $event->reason->value,
        'client_id' => $event->clientId,
    ]);
});
```

Test signature:
```php
$service = app(SignatureService::class);
$payload = new SignaturePayload('POST', '/api/test', '{}', time(), 'nonce');
$expected = $service->generate($payload, 'secret');
```
