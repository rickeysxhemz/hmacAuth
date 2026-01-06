<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use HmacAuth\Models\ApiCredential;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for API credential repository operations.
 */
interface ApiCredentialRepositoryInterface
{
    /**
     * Find credential by client ID.
     */
    public function findByClientId(string $clientId): ?ApiCredential;

    /**
     * Find active credential by client ID (with caching).
     */
    public function findActiveByClientId(string $clientId): ?ApiCredential;

    /**
     * Invalidate cached credential.
     */
    public function invalidateCache(string $clientId): bool;

    /**
     * Get all credentials for a company.
     *
     * @return Collection<int, ApiCredential>
     */
    public function getByCompany(int $companyId): Collection;

    /**
     * Get active credentials for a company.
     *
     * @return Collection<int, ApiCredential>
     */
    public function getActiveByCompany(int $companyId): Collection;

    /**
     * Create new credential.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApiCredential;

    /**
     * Update credential.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ApiCredential $credential, array $data): bool;

    /**
     * Delete credential.
     */
    public function delete(ApiCredential $credential): bool;

    /**
     * Mark credential as used.
     */
    public function markAsUsed(ApiCredential $credential): bool;

    /**
     * Deactivate credential.
     */
    public function deactivate(ApiCredential $credential): bool;

    /**
     * Activate credential.
     */
    public function activate(ApiCredential $credential): bool;

    /**
     * Get expired credentials with a limit.
     *
     * @return Collection<int, ApiCredential>
     */
    public function getExpired(int $limit = 100): Collection;

    /**
     * Cleanup expired credentials (deactivate them).
     */
    public function cleanupExpired(): int;

    /**
     * Get credentials expiring soon.
     *
     * @return Collection<int, ApiCredential>
     */
    public function getExpiringSoon(int $days = 7): Collection;

    /**
     * Count active credentials for company.
     */
    public function countActiveByCompany(int $companyId): int;

    /**
     * Get all credentials with pagination.
     *
     * @return LengthAwarePaginator<int, ApiCredential>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Search credentials with pagination.
     *
     * @return LengthAwarePaginator<int, ApiCredential>
     */
    public function search(string $term, int $perPage = 15): LengthAwarePaginator;
}