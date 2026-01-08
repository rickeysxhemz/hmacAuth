<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use Carbon\CarbonInterface;
use HmacAuth\Models\ApiRequestLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use stdClass;

/**
 * Interface for API request log repository operations.
 */
interface ApiRequestLogRepositoryInterface
{
    /**
     * Create a new log entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApiRequestLog;

    /**
     * Get logs for a company.
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getByCompany(int $companyId, int $limit = 100): Collection;

    /**
     * Get logs for a client.
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getByClient(string $clientId, int $limit = 100): Collection;

    /**
     * Get failed attempts for a client.
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getFailedAttempts(string $clientId, int $limit = 100): Collection;

    /**
     * Get recent failed attempts by IP.
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getRecentFailedByIp(string $ipAddress, int $minutes = 10, int $limit = 100): Collection;

    /**
     * Count failed attempts for a client in time period.
     */
    public function countFailedAttempts(string $clientId, int $minutes = 10): int;

    /**
     * Count failed attempts by IP in time period.
     */
    public function countFailedByIp(string $ipAddress, int $minutes = 10): int;

    /**
     * Get logs with pagination.
     *
     * @return LengthAwarePaginator<int, ApiRequestLog>
     */
    public function paginate(int $perPage = 50): LengthAwarePaginator;

    /**
     * Get logs by date range with limit.
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getByDateRange(CarbonInterface $from, CarbonInterface $to, int $limit = 1000): Collection;

    /**
     * Get paginated logs by date range.
     *
     * @return LengthAwarePaginator<int, ApiRequestLog>
     */
    public function paginateByDateRange(CarbonInterface $from, CarbonInterface $to, int $perPage = 50): LengthAwarePaginator;

    /**
     * Get statistics for company.
     *
     * @return array{total: int, successful: int, failed: int, success_rate: float}
     */
    public function getStats(int $companyId, int $days = 7): array;

    /**
     * Delete old logs in chunks (Octane-safe).
     */
    public function deleteOlderThan(int $days, int $chunkSize = 1000, int $sleepMicroseconds = 0): int;

    /**
     * Delete recent failed attempts for a specific IP address.
     */
    public function deleteFailedByIp(string $ipAddress, int $minutes = 10): int;

    /**
     * Get IPs that have exceeded the failure threshold.
     *
     * @return SupportCollection<int, stdClass>
     */
    public function getBlockedIps(int $threshold = 10, int $minutes = 10): SupportCollection;

    /**
     * Clear all recent failed attempts within the time window.
     */
    public function clearAllFailedRecent(int $minutes = 10): int;
}
