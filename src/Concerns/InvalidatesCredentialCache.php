<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Models\ApiCredential;

/**
 * Provides cache invalidation and model refresh for credentials.
 */
trait InvalidatesCredentialCache
{
    abstract protected function getCredentialRepository(): ApiCredentialRepositoryInterface;

    /**
     * Invalidate cache and refresh the credential model.
     *
     * @param  array<string>  $relations
     */
    protected function invalidateAndRefresh(
        ApiCredential $credential,
        array $relations = []
    ): ApiCredential {
        $this->getCredentialRepository()->invalidateCache($credential->client_id);

        /** @var ApiCredential */
        return $credential->fresh($relations);
    }

    /**
     * Invalidate cache for a given client ID.
     */
    protected function invalidateCacheFor(string $clientId): void
    {
        $this->getCredentialRepository()->invalidateCache($clientId);
    }
}
