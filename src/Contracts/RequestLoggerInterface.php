<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;

/**
 * Interface for logging HMAC authentication requests.
 */
interface RequestLoggerInterface
{
    /**
     * Log a successful authentication attempt.
     */
    public function logSuccessfulAttempt(Request $request, ApiCredential $credential): void;

    /**
     * Log a failed authentication attempt.
     */
    public function logFailedAttempt(
        Request $request,
        string $clientId,
        string $reason,
        ?ApiCredential $credential = null
    ): void;

    /**
     * Check if an IP address has too many failed attempts.
     */
    public function hasExcessiveFailures(string $ipAddress, ?int $threshold = null, ?int $minutes = null): bool;

    /**
     * Check if a client has too many failed attempts.
     */
    public function hasExcessiveClientFailures(string $clientId, ?int $threshold = null, ?int $minutes = null): bool;

    /**
     * Clear failed attempts for a specific IP address.
     */
    public function clearFailuresForIp(string $ipAddress, ?int $minutes = null): int;

    /**
     * Check if IP blocking is enabled.
     */
    public function isIpBlockingEnabled(): bool;

    /**
     * Get IP blocking configuration values.
     *
     * @return array{threshold: int, window_minutes: int}
     */
    public function getIpBlockingConfig(): array;
}