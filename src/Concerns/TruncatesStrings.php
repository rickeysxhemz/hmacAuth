<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * Trait for truncating strings to prevent database abuse.
 */
trait TruncatesStrings
{
    /**
     * Truncate a string to a maximum length.
     */
    protected function truncate(?string $value, int $maxLength, string $suffix = '...'): ?string
    {
        if ($value === null || $maxLength <= 0) {
            return $value;
        }

        $valueLength = mb_strlen($value, 'UTF-8');

        if ($valueLength <= $maxLength) {
            return $value;
        }

        $suffixLength = mb_strlen($suffix, 'UTF-8');
        $truncateLength = max(0, $maxLength - $suffixLength);

        return mb_substr($value, 0, $truncateLength, 'UTF-8').$suffix;
    }

    /**
     * Truncate a user agent string.
     */
    protected function truncateUserAgent(?string $userAgent, int $maxLength = 500): ?string
    {
        return $this->truncate($userAgent, $maxLength);
    }

    /**
     * Truncate a request path.
     */
    protected function truncatePath(string $path, int $maxLength = 500): string
    {
        return $this->truncate($path, $maxLength) ?? '';
    }
}
