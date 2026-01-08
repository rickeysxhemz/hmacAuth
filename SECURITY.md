# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues.**

### How to Report

1. **GitHub Security Advisories (Preferred)**
   - Go to the [Security Advisories](https://github.com/your-username/laravel-hmac-auth/security/advisories) page
   - Click "Report a vulnerability"
   - Provide detailed information about the vulnerability

2. **Email**
   - Send details to: security@your-domain.com
   - Use the subject line: `[SECURITY] Laravel HMAC Auth - Brief Description`

### What to Include

- Type of vulnerability (e.g., signature bypass, timing attack, injection)
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue and how an attacker might exploit it

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 5 business days
- **Resolution Target**: Within 30 days for critical issues

### Process

1. Your report will be acknowledged within 48 hours
2. We will investigate and determine the severity
3. You will receive updates on the progress
4. Once fixed, we will coordinate disclosure with you
5. Security advisories will be published after the fix is released

### Recognition

We appreciate responsible disclosure and will:

- Acknowledge your contribution in the security advisory
- Credit you in the CHANGELOG (unless you prefer anonymity)
- Work with you on coordinated disclosure timing

## Security Features

This package implements several security measures:

### Signature Security

- **Timing-safe comparison**: Uses `hash_equals()` for signature verification to prevent timing attacks
- **Strong algorithms**: Supports SHA-256, SHA-384, and SHA-512
- **Canonical request format**: Consistent signature payload construction

### Replay Attack Prevention

- **Timestamp validation**: Configurable tolerance window (default: 5 minutes)
- **Nonce uniqueness**: Redis-backed nonce store with TTL expiration
- **One-time use**: Each nonce can only be used once within its TTL

### Credential Security

- **Encrypted secrets**: Client secrets are encrypted in the database using Laravel's encryption
- **Secure generation**: Cryptographically secure random generation for all credentials
- **Secret rotation**: Support for graceful secret rotation with configurable overlap period

### Rate Limiting & Blocking

- **Per-client rate limiting**: Configurable request limits per client ID
- **IP blocking**: Automatic blocking after excessive failed attempts
- **Graduated backoff**: Increasing penalties for repeated failures

### Environment Isolation

- **Environment tagging**: Credentials can be restricted to specific environments
- **Enforcement option**: Optional strict environment matching

## Best Practices

When using this package, we recommend:

1. **Use HTTPS**: Always use HTTPS in production to protect headers in transit
2. **Short timestamp tolerance**: Use the shortest practical timestamp tolerance
3. **Rotate secrets regularly**: Implement a secret rotation schedule (quarterly recommended)
4. **Monitor failures**: Set up alerts on `AuthenticationFailed` events
5. **Separate environments**: Use different credentials for production, staging, and development
6. **Secure secret storage**: Store client secrets securely on the client side
7. **Audit logs**: Regularly review request logs for suspicious patterns

## Known Limitations

- **Header exposure**: HMAC headers are visible in transit without HTTPS
- **Clock synchronization**: Requires reasonably synchronized clocks between client and server
- **Redis dependency**: Nonce store and rate limiting require Redis

## Security Updates

Security updates will be released as patch versions (e.g., 1.0.1) and announced via:

- GitHub Security Advisories
- Release notes in CHANGELOG.md
- GitHub Releases

We recommend:

- Enabling Dependabot alerts for this repository
- Subscribing to releases for update notifications
- Regularly updating to the latest patch version
