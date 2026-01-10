<?php

declare(strict_types=1);

namespace HmacAuth\DTOs;

use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Models\ApiCredential;
use LogicException;

/**
 * Value object representing the result of HMAC verification.
 */
final readonly class VerificationResult
{
    private function __construct(
        public bool $valid,
        public ?ApiCredential $credential,
        public ?VerificationFailureReason $failureReason,
    ) {}

    /**
     * Create a successful verification result.
     */
    public static function success(ApiCredential $credential): self
    {
        return new self(
            valid: true,
            credential: $credential,
            failureReason: null,
        );
    }

    /**
     * Create a failed verification result.
     */
    public static function failure(VerificationFailureReason $reason, ?ApiCredential $credential = null): self
    {
        return new self(
            valid: false,
            credential: $credential,
            failureReason: $reason,
        );
    }

    /**
     * Check if verification was successful.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if verification failed.
     */
    public function isFailure(): bool
    {
        return ! $this->valid;
    }

    /**
     * @throws LogicException
     */
    public function getCredential(): ApiCredential
    {
        if ($this->credential === null) {
            throw new LogicException('Cannot get credential from failed verification result');
        }

        return $this->credential;
    }

    /**
     * Get the error message (returns null if successful).
     */
    public function getErrorMessage(): ?string
    {
        return $this->failureReason?->getMessage();
    }

    /**
     * Get the HTTP status code for this result.
     */
    public function getHttpStatus(): int
    {
        if ($this->valid) {
            return 200;
        }

        return $this->failureReason?->getHttpStatus() ?? 401;
    }

    /**
     * Check if rate limit should be incremented.
     */
    public function shouldIncrementRateLimit(): bool
    {
        return $this->failureReason?->shouldIncrementRateLimit() ?? false;
    }

    /**
     * Convert to array for backward compatibility.
     *
     * @return array{valid: bool, credential: ?ApiCredential, error: ?string}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'credential' => $this->credential,
            'error' => $this->getErrorMessage(),
        ];
    }
}
