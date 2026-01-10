# Security Best Practices

## Secret Management

**Do:** Use vault systems (HashiCorp Vault, AWS Secrets Manager), environment variables, encrypt at rest.

**Don't:** Commit to version control, log secrets, store in plain text, share via email.

```bash
# Client .env
HMAC_CLIENT_ID=prod_a1b2c3d4e5f6g7h8
HMAC_CLIENT_SECRET=your-secret-here
```

---

## Transport Security

Always use HTTPS. HMAC headers are visible in transit.

```nginx
server {
    listen 443 ssl http2;
    ssl_protocols TLSv1.2 TLSv1.3;
    add_header Strict-Transport-Security "max-age=31536000" always;
}
```

---

## Credential Rotation

| Environment | Frequency |
|-------------|-----------|
| Production | Quarterly |
| Staging | Monthly |
| Development | As needed |

```bash
php artisan hmac:rotate prod_client_id --grace-days=14
```

---

## Environment Separation

| Environment | Prefix |
|-------------|--------|
| Production | `prod_` |
| Staging | `stg_` |
| Development | `dev_` |

Enable enforcement: `'enforce_environment' => true`

---

## Timestamp Tolerance

| Tolerance | Use Case |
|-----------|----------|
| 60s | High-security |
| 300s | Standard (default) |
| 600s | Mobile clients |

Sync clocks with NTP: `timedatectl set-ntp true`

---

## Rate Limiting

| Traffic | `max_attempts` | `decay` | `ip_threshold` |
|---------|----------------|---------|----------------|
| Low | 3 | 60s | 5 |
| Medium | 5 | 60s | 10 |
| High | 10 | 30s | 20 |

---

## Monitoring

Key metrics:
- Authentication success rate
- Failed attempt spikes
- Rate limit triggers
- IP blocks

---

## Checklist

**Setup:**
- [ ] HTTPS enforced
- [ ] Redis secured
- [ ] Environment enforcement enabled

**Operations:**
- [ ] Credentials rotated quarterly
- [ ] Failure alerts configured
- [ ] Logs reviewed monthly

---

## Avoid

**Timing attacks:** Use `hash_equals()` for comparisons.

**Replay attacks:** Nonce TTL should be >= 2x timestamp tolerance.

**Secret leakage:** Never log `$request->headers->all()`.
