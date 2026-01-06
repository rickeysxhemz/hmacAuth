<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use HmacAuth\DTOs\SignaturePayload;

/**
 * Interface for HMAC signature generation and verification.
 */
interface SignatureServiceInterface
{
    /**
     * Generate an HMAC signature for the given payload.
     */
    public function generate(SignaturePayload $payload, string $secret, string $algorithm = 'sha256'): string;

    /**
     * Verify that a signature matches the expected signature.
     */
    public function verify(string $expected, string $actual): bool;

    /**
     * Check if an algorithm is supported.
     */
    public function isAlgorithmSupported(string $algorithm): bool;

    /**
     * Get list of supported algorithms.
     *
     * @return list<string>
     */
    public function getSupportedAlgorithms(): array;
}
