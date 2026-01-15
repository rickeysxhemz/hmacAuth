<?php

declare(strict_types=1);

namespace HmacAuth\Repositories;

use HmacAuth\Concerns\CacheRepositoryConcerns;
use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\Models\ApiCredential;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Repository for API credential data access operations.
 *
 * @phpstan-type CredentialBuilder Builder<ApiCredential>
 */
final readonly class ApiCredentialRepository implements ApiCredentialRepositoryInterface
{
    use CacheRepositoryConcerns;

    private const int CACHE_TTL_SECONDS = 60;

    public function __construct(
        private TenancyConfigInterface $tenancyConfig,
    ) {}

    private const int CACHE_LOCK_TIMEOUT = 10;

    private const int NEGATIVE_CACHE_TTL = 60;

    private const string NOT_FOUND_MARKER = '__NOT_FOUND__';

    private const int MARK_USED_DEBOUNCE_SECONDS = 60;

    private const int MIN_SEARCH_TERM_LENGTH = 3;

    private const int MAX_COLLECTION_LIMIT = 100;

    protected function getCachePrefix(): string
    {
        return 'api_credential';
    }

    /**
     * @return Builder<ApiCredential>
     */
    public function query(): Builder
    {
        return ApiCredential::query();
    }

    /**
     * Find credential by client ID with caching.
     */
    public function findByClientId(string $clientId): ?ApiCredential
    {
        $hashedId = $this->hashIdentifier($clientId);
        $cacheKey = $this->getCacheKey('by_id', $hashedId);

        $cached = Cache::get($cacheKey);

        if ($cached instanceof ApiCredential) {
            return $cached;
        }

        if ($cached === self::NOT_FOUND_MARKER) {
            return null;
        }

        /** @var ApiCredential|null $credential */
        $credential = $this->query()
            ->where('client_id', $clientId)
            ->first();

        if ($credential !== null) {
            Cache::put($cacheKey, $credential, self::CACHE_TTL_SECONDS);
        } else {
            Cache::put($cacheKey, self::NOT_FOUND_MARKER, self::NEGATIVE_CACHE_TTL);
        }

        return $credential;
    }

    /**
     * Find active credential by client ID with caching and stampede protection.
     */
    public function findActiveByClientId(string $clientId): ?ApiCredential
    {
        $hashedId = $this->hashIdentifier($clientId);
        $cacheKey = $this->getCacheKey('active', $hashedId);

        $cached = Cache::get($cacheKey);

        if ($cached instanceof ApiCredential) {
            return $cached;
        }

        if ($cached === self::NOT_FOUND_MARKER) {
            return null;
        }

        $lockKey = $this->getLockKey($hashedId);

        try {
            /** @var ApiCredential|null */
            return Cache::lock($lockKey, self::CACHE_LOCK_TIMEOUT)->block(self::CACHE_LOCK_TIMEOUT, function () use ($cacheKey, $clientId): ?ApiCredential {
                $cached = Cache::get($cacheKey);

                if ($cached instanceof ApiCredential) {
                    return $cached;
                }

                if ($cached === self::NOT_FOUND_MARKER) {
                    return null;
                }

                /** @var ApiCredential|null $credential */
                $credential = $this->query()
                    ->where('client_id', $clientId)
                    ->active()
                    ->first();

                if ($credential !== null) {
                    Cache::put($cacheKey, $credential, self::CACHE_TTL_SECONDS);
                } else {
                    Cache::put($cacheKey, self::NOT_FOUND_MARKER, self::NEGATIVE_CACHE_TTL);
                }

                return $credential;
            });
        } catch (LockTimeoutException) {
            Log::warning('Cache lock timeout for credential lookup', [
                'client_id' => $this->sanitizeForLog($clientId),
            ]);

            /** @var ApiCredential|null */
            return $this->query()
                ->where('client_id', $clientId)
                ->active()
                ->first();
        }
    }

    public function invalidateCache(string $clientId): bool
    {
        $hashedId = $this->hashIdentifier($clientId);

        Cache::forget($this->getCacheKey('by_id', $hashedId));
        Cache::forget($this->getCacheKey('last_used', $hashedId));

        return Cache::forget($this->getCacheKey('active', $hashedId));
    }

    /**
     * Get all credentials for a tenant (limited).
     *
     * @return Collection<int, ApiCredential>
     *
     * @throws RuntimeException When tenancy is not enabled
     */
    public function getByTenant(int|string $tenantId, int $limit = self::MAX_COLLECTION_LIMIT): Collection
    {
        if (! $this->tenancyConfig->isEnabled()) {
            throw new RuntimeException('Tenancy is not enabled. Enable tenancy in config/hmac.php to use this method.');
        }

        return $this->query()
            ->forTenant($tenantId)
            ->withDefaultRelations()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get active credentials for a tenant (limited).
     *
     * @return Collection<int, ApiCredential>
     *
     * @throws RuntimeException When tenancy is not enabled
     */
    public function getActiveByTenant(int|string $tenantId, int $limit = self::MAX_COLLECTION_LIMIT): Collection
    {
        if (! $this->tenancyConfig->isEnabled()) {
            throw new RuntimeException('Tenancy is not enabled. Enable tenancy in config/hmac.php to use this method.');
        }

        return $this->query()
            ->forTenant($tenantId)
            ->active()
            ->withDefaultRelations()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Create new credential.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApiCredential
    {
        /** @var ApiCredential */
        return $this->query()->create($data);
    }

    /**
     * Update credential and invalidate cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ApiCredential $credential, array $data): bool
    {
        $clientId = $credential->client_id;

        $result = $credential->update($data);

        // Invalidate cache after update
        if ($result && is_string($clientId)) {
            $this->invalidateCache($clientId);
        }

        return $result;
    }

    /**
     * Delete credential and invalidate cache.
     */
    public function delete(ApiCredential $credential): bool
    {
        $clientId = $credential->client_id;

        $result = (bool) $credential->delete();

        // Invalidate cache after deletion
        if ($result && is_string($clientId)) {
            $this->invalidateCache($clientId);
        }

        return $result;
    }

    /**
     * Mark credential as used with debouncing to prevent write amplification.
     */
    public function markAsUsed(ApiCredential $credential): bool
    {
        $clientId = $credential->client_id;
        if (! is_string($clientId)) {
            return false;
        }

        $hashedId = $this->hashIdentifier($clientId);
        $cacheKey = $this->getCacheKey('last_used', $hashedId);

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, self::MARK_USED_DEBOUNCE_SECONDS);

        return $this->update($credential, ['last_used_at' => now()]);
    }

    /**
     * Deactivate credential.
     */
    public function deactivate(ApiCredential $credential): bool
    {
        return $this->update($credential, ['is_active' => false]);
    }

    /**
     * Activate credential.
     */
    public function activate(ApiCredential $credential): bool
    {
        return $this->update($credential, ['is_active' => true]);
    }

    /**
     * Get expired credentials with limit.
     *
     * @return Collection<int, ApiCredential>
     */
    public function getExpired(int $limit = self::MAX_COLLECTION_LIMIT): Collection
    {
        return $this->query()
            ->expired()
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Cleanup expired credentials (deactivate them).
     */
    public function cleanupExpired(): int
    {
        return $this->query()
            ->expired()
            ->update(['is_active' => false]);
    }

    /**
     * Get credentials expiring soon.
     *
     * @return Collection<int, ApiCredential>
     */
    public function getExpiringSoon(int $days = 7, int $limit = self::MAX_COLLECTION_LIMIT): Collection
    {
        return $this->query()
            ->expiringSoon($days)
            ->withDefaultRelations()
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Count active credentials for tenant.
     *
     * @throws RuntimeException When tenancy is not enabled
     */
    public function countActiveByTenant(int|string $tenantId): int
    {
        if (! $this->tenancyConfig->isEnabled()) {
            throw new RuntimeException('Tenancy is not enabled. Enable tenancy in config/hmac.php to use this method.');
        }

        return $this->query()
            ->forTenant($tenantId)
            ->active()
            ->count();
    }

    /**
     * Get all credentials with pagination.
     *
     * @return LengthAwarePaginator<int, ApiCredential>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->withDefaultRelations()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search credentials with pagination and validation.
     *
     * @return LengthAwarePaginator<int, ApiCredential>
     */
    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        $term = trim($term);

        if (strlen($term) < self::MIN_SEARCH_TERM_LENGTH) {
            return $this->query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return $this->query()
            ->searchByTerm($term)
            ->withDefaultRelations()
            ->latest()
            ->paginate($perPage);
    }
}
