<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

/**
 * Interface for nonce storage to prevent replay attacks.
 */
interface NonceStoreInterface
{
    /**
     * Check if nonce already exists (indicates replay attack).
     */
    public function exists(string $nonce): bool;

    /**
     * Store nonce with TTL.
     */
    public function store(string $nonce): void;

    /**
     * Clear all nonce's (only for testing environments).
     */
    public function clear(): void;
}
