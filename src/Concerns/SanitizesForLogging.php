<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * Sanitizes sensitive data before logging to prevent log injection.
 */
trait SanitizesForLogging
{
    /**
     * Sanitize a value for safe logging by removing special characters.
     */
    protected function sanitizeForLog(string $value, int $maxLength = 20): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($value, 0, $maxLength));

        return ($sanitized ?? '').'...';
    }

    /**
     * Mask a sensitive value showing only first N characters.
     */
    protected function maskSensitiveValue(string $value, int $visibleChars = 8): string
    {
        if (strlen($value) <= $visibleChars) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, $visibleChars).'...';
    }
}
