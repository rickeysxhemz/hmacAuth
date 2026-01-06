<?php

declare(strict_types=1);

namespace HmacAuth\Enums;

/**
 * Enum representing reasons for HMAC verification failure.
 */
enum VerificationFailureReason: string
{
    case MISSING_HEADERS = 'missing_headers';
    case INVALID_TIMESTAMP = 'invalid_timestamp';
    case BODY_TOO_LARGE = 'body_too_large';
    case IP_BLOCKED = 'ip_blocked';
    case RATE_LIMITED = 'rate_limited';
    case INVALID_NONCE = 'invalid_nonce';
    case DUPLICATE_NONCE = 'duplicate_nonce';
    case INVALID_CLIENT_ID = 'invalid_client_id';
    case CREDENTIAL_EXPIRED = 'credential_expired';
    case ENVIRONMENT_MISMATCH = 'environment_mismatch';
    case INVALID_SECRET = 'invalid_secret';
    case INVALID_SIGNATURE = 'invalid_signature';

    /**
     * Get human-readable error message.
     */
    public function getMessage(): string
    {
        return match ($this) {
            self::MISSING_HEADERS => 'Missing required headers',
            self::INVALID_TIMESTAMP => 'Invalid or expired timestamp',
            self::BODY_TOO_LARGE => 'Request body exceeds maximum size',
            self::IP_BLOCKED => 'Too many failed attempts from this IP',
            self::RATE_LIMITED => 'Rate limit exceeded',
            self::INVALID_NONCE => 'Nonce too short',
            self::DUPLICATE_NONCE => 'Duplicate nonce detected',
            self::INVALID_CLIENT_ID => 'Invalid client ID',
            self::CREDENTIAL_EXPIRED => 'API credential has expired',
            self::ENVIRONMENT_MISMATCH => 'Credential environment mismatch',
            self::INVALID_SECRET => 'Invalid client secret',
            self::INVALID_SIGNATURE => 'Invalid signature',
        };
    }

    /**
     * Get HTTP status code for this failure.
     */
    public function getHttpStatus(): int
    {
        return match ($this) {
            self::RATE_LIMITED, self::IP_BLOCKED => 429,
            self::BODY_TOO_LARGE => 413,
            default => 401,
        };
    }

    /**
     * Check if this failure should increment rate limit.
     */
    public function shouldIncrementRateLimit(): bool
    {
        return match ($this) {
            self::INVALID_CLIENT_ID,
            self::ENVIRONMENT_MISMATCH,
            self::INVALID_SIGNATURE => true,
            default => false,
        };
    }
}
