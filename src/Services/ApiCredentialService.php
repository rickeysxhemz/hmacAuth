<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use Carbon\CarbonInterface;
use HmacAuth\Concerns\InvalidatesCredentialCache;
use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\KeyGeneratorInterface;
use HmacAuth\Models\ApiCredential;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Random\RandomException;

/**
 * Service for managing API credentials.
 */
final readonly class ApiCredentialService
{
    use InvalidatesCredentialCache;

    public function __construct(
        private ApiCredentialRepositoryInterface $repository,
        private KeyGeneratorInterface $keyGenerator,
    ) {}

    protected function getCredentialRepository(): ApiCredentialRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * Generate new API credentials for a company.
     *
     * @return array{credential: ApiCredential, plain_secret: string}
     *
     * @throws RandomException
     * @throws InvalidArgumentException
     */
    public function generate(
        int $companyId,
        int $createdBy,
        string $environment = ApiCredential::ENVIRONMENT_TESTING,
        ?CarbonInterface $expiresAt = null
    ): array {
        if (! ApiCredential::isValidEnvironment($environment)) {
            throw new InvalidArgumentException(
                sprintf('Invalid environment: %s. Valid values: %s', $environment, implode(', ', ApiCredential::VALID_ENVIRONMENTS))
            );
        }

        return DB::transaction(function () use ($companyId, $createdBy, $environment, $expiresAt): array {
            $clientId = $this->keyGenerator->generateClientId($environment);
            $plainSecret = $this->keyGenerator->generateClientSecret();

            $credential = $this->repository->create([
                'company_id' => $companyId,
                'client_id' => $clientId,
                'client_secret' => $plainSecret,
                'hmac_algorithm' => config('hmac.algorithm', 'sha256'),
                'environment' => $environment,
                'is_active' => true,
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
            ]);

            return [
                'credential' => $credential->load(['company', 'creator']),
                'plain_secret' => $plainSecret,
            ];
        });
    }

    /**
     * Rotate secret for existing credential.
     *
     * @return array{credential: ApiCredential, plain_secret: string}
     *
     * @throws RandomException
     */
    public function rotateSecret(ApiCredential $credential): array
    {
        return DB::transaction(function () use ($credential): array {
            $newPlainSecret = $this->keyGenerator->generateClientSecret();

            $this->repository->update($credential, [
                'old_client_secret' => $credential->client_secret,
                'old_secret_expires_at' => now()->addDays(7),
                'client_secret' => $newPlainSecret,
            ]);

            return [
                'credential' => $this->invalidateAndRefresh($credential, ['company', 'creator']),
                'plain_secret' => $newPlainSecret,
            ];
        });
    }

    /**
     * Regenerate client ID (keeping same secret and environment).
     *
     * @throws RandomException
     */
    public function regenerateClientId(ApiCredential $credential): ApiCredential
    {
        $oldClientId = $credential->client_id;
        /** @var string $environment */
        $environment = $credential->environment ?? ApiCredential::ENVIRONMENT_TESTING;

        $this->repository->update($credential, [
            'client_id' => $this->keyGenerator->generateClientId($environment),
        ]);

        $this->invalidateCacheFor($oldClientId);

        /** @var ApiCredential */
        return $credential->fresh(['company', 'creator']);
    }

    /**
     * Toggle credential active status.
     */
    public function toggleStatus(ApiCredential $credential): ApiCredential
    {
        if ($credential->is_active) {
            $this->repository->deactivate($credential);
        } else {
            $this->repository->activate($credential);
        }

        return $this->invalidateAndRefresh($credential);
    }

    /**
     * Deactivate credential.
     */
    public function deactivate(ApiCredential $credential): ApiCredential
    {
        $this->repository->deactivate($credential);

        return $this->invalidateAndRefresh($credential);
    }

    /**
     * Activate credential.
     */
    public function activate(ApiCredential $credential): ApiCredential
    {
        $this->repository->activate($credential);

        return $this->invalidateAndRefresh($credential);
    }

    /**
     * Set expiration date.
     */
    public function setExpiration(ApiCredential $credential, ?CarbonInterface $expiresAt): ApiCredential
    {
        $this->repository->update($credential, [
            'expires_at' => $expiresAt,
        ]);

        return $this->invalidateAndRefresh($credential);
    }

    /**
     * Delete credential.
     */
    public function delete(ApiCredential $credential): bool
    {
        $this->invalidateCacheFor($credential->client_id);

        return $this->repository->delete($credential);
    }
}
