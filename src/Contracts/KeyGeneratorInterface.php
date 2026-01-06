<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

/**
 * Interface for cryptographic key generation.
 */
interface KeyGeneratorInterface
{
    /**
     * Generate a cryptographically secure Client ID.
     *
     * @param  string  $environment  The credential environment (production or testing)
     */
    public function generateClientId(string $environment): string;

    /**
     * Generate a cryptographically secure Client Secret.
     */
    public function generateClientSecret(): string;

    /**
     * Generate a secure nonce for request uniqueness.
     */
    public function generateNonce(): string;
}