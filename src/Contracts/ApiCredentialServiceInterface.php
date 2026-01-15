<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use Carbon\CarbonInterface;
use HmacAuth\Models\ApiCredential;

/**
 * Interface for API credential management operations.
 */
interface ApiCredentialServiceInterface
{
    /**
     * Generate new API credentials.
     *
     * When tenancy is enabled, tenantId is required.
     * When tenancy is disabled (standalone mode), tenantId is ignored.
     *
     * @return array{credential: ApiCredential, plain_secret: string}
     */
    public function generate(
        int $createdBy,
        string $environment = ApiCredential::ENVIRONMENT_TESTING,
        ?CarbonInterface $expiresAt = null,
        int|string|null $tenantId = null,
    ): array;

    /**
     * Rotate secret for existing credential.
     *
     * @return array{credential: ApiCredential, new_secret: string, old_secret_expires_at: string}
     */
    public function rotateSecret(ApiCredential $credential, int $graceDays = 7): array;

    /**
     * Regenerate client ID (keeping same secret and environment).
     */
    public function regenerateClientId(ApiCredential $credential): ApiCredential;

    /**
     * Toggle credential active status.
     */
    public function toggleStatus(ApiCredential $credential): ApiCredential;

    /**
     * Deactivate credential.
     */
    public function deactivate(ApiCredential $credential): ApiCredential;

    /**
     * Activate credential.
     */
    public function activate(ApiCredential $credential): ApiCredential;

    /**
     * Set expiration date.
     */
    public function setExpiration(ApiCredential $credential, ?CarbonInterface $expiresAt): ApiCredential;

    /**
     * Delete credential.
     */
    public function delete(ApiCredential $credential): bool;
}
