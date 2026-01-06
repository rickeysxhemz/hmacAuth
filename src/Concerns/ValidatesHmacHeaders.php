<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * Validates HMAC authentication headers and request parameters.
 */
trait ValidatesHmacHeaders
{
    /**
     * Check if all required headers are present and non-empty strings.
     */
    protected function hasValidHeaders(mixed ...$headers): bool
    {
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if timestamp is within acceptable tolerance.
     */
    protected function isTimestampValid(int $timestamp, int $toleranceSeconds): bool
    {
        return abs(time() - $timestamp) <= $toleranceSeconds;
    }

    /**
     * Check if request body size is within limits.
     */
    protected function isBodySizeValid(string $content, int $maxBytes): bool
    {
        return strlen($content) <= $maxBytes;
    }

    /**
     * Check if nonce meets minimum length requirement.
     */
    protected function isNonceLengthValid(string $nonce, int $minLength): bool
    {
        return strlen($nonce) >= $minLength;
    }
}
