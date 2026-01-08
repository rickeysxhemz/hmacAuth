<?php

declare(strict_types=1);

namespace HmacAuth\Repositories;

use Carbon\CarbonInterface;
use HmacAuth\Contracts\ApiRequestLogRepositoryInterface;
use HmacAuth\Models\ApiRequestLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use stdClass;

/**
 * Repository for API request log data access operations.
 *
 * @phpstan-type LogBuilder Builder<ApiRequestLog>
 */
final readonly class ApiRequestLogRepository implements ApiRequestLogRepositoryInterface
{
    /**
     * @return Builder<ApiRequestLog>
     */
    public function query(): Builder
    {
        return ApiRequestLog::query();
    }

    /**
     * Create new log entry
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApiRequestLog
    {
        /** @var ApiRequestLog */
        return $this->query()->create($data);
    }

    /**
     * Get logs for a company
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getByCompany(int $companyId, int $limit = 100): Collection
    {
        return $this->query()
            ->forCompany($companyId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs for a client
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getByClient(string $clientId, int $limit = 100): Collection
    {
        return $this->query()
            ->forClient($clientId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed attempts for a client
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getFailedAttempts(string $clientId, int $limit = 100): Collection
    {
        return $this->query()
            ->forClient($clientId)
            ->failed()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent failed attempts by IP
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getRecentFailedByIp(string $ipAddress, int $minutes = 10, int $limit = 100): Collection
    {
        return $this->query()
            ->fromIp($ipAddress)
            ->failed()
            ->recent($minutes)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Count failed attempts for client in time period
     */
    public function countFailedAttempts(string $clientId, int $minutes = 10): int
    {
        return $this->query()
            ->forClient($clientId)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    /**
     * Count failed attempts by IP in time period
     */
    public function countFailedByIp(string $ipAddress, int $minutes = 10): int
    {
        return $this->query()
            ->fromIp($ipAddress)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    /**
     * Get logs with pagination
     *
     * @return LengthAwarePaginator<int, ApiRequestLog>
     */
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return $this->query()
            ->with(['company', 'apiCredential'])->latest()
            ->paginate($perPage);
    }

    /**
     * Get logs by date range with limit
     *
     * @return Collection<int, ApiRequestLog>
     */
    public function getByDateRange(CarbonInterface $from, CarbonInterface $to, int $limit = 1000): Collection
    {
        return $this->query()
            ->dateRange($from, $to)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get paginated logs by date range
     *
     * @return LengthAwarePaginator<int, ApiRequestLog>
     */
    public function paginateByDateRange(CarbonInterface $from, CarbonInterface $to, int $perPage = 50): LengthAwarePaginator
    {
        return $this->query()
            ->dateRange($from, $to)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get statistics for company (optimized single query)
     *
     * @return array{total: int, successful: int, failed: int, success_rate: float}
     */
    public function getStats(int $companyId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $result = $this->query()
            ->forCompany($companyId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN signature_valid = 1 THEN 1 ELSE 0 END) as successful')
            ->selectRaw('SUM(CASE WHEN signature_valid = 0 THEN 1 ELSE 0 END) as failed')
            ->first();

        $totalValue = $result?->getAttribute('total');
        $successfulValue = $result?->getAttribute('successful');
        $failedValue = $result?->getAttribute('failed');

        $total = is_numeric($totalValue) ? (int) $totalValue : 0;
        $successful = is_numeric($successfulValue) ? (int) $successfulValue : 0;
        $failed = is_numeric($failedValue) ? (int) $failedValue : 0;

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * Count logs older than specified days.
     */
    public function countOlderThan(int $days): int
    {
        $cutoffDate = now()->subDays($days);

        return $this->query()
            ->where('created_at', '<', $cutoffDate)
            ->count();
    }

    /**
     * Delete old logs in chunks using ID-based batching.
     *
     * Octane-safe: Uses primitive arrays instead of Collections to prevent memory leaks.
     * Uses primary key ordering for consistent, index-optimized deletes.
     */
    public function deleteOlderThan(
        int $days,
        int $chunkSize = 1000,
        int $sleepMicroseconds = 0
    ): int {
        $totalDeleted = 0;
        $cutoffDate = now()->subDays($days);

        while (true) {
            /** @var array<int, int> $idsToDelete */
            $idsToDelete = $this->query()
                ->where('created_at', '<', $cutoffDate)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            $count = count($idsToDelete);

            if ($count === 0) {
                break;
            }

            /** @var int $deleted */
            $deleted = $this->query()
                ->whereIn('id', $idsToDelete)
                ->delete();

            $totalDeleted += $deleted;
            unset($idsToDelete);

            if ($deleted < $chunkSize) {
                break;
            }

            if ($sleepMicroseconds > 0) {
                usleep($sleepMicroseconds);
            }
        }

        return $totalDeleted;
    }

    /**
     * Delete recent failed attempts for a specific IP address.
     */
    public function deleteFailedByIp(string $ipAddress, int $minutes = 10): int
    {
        /** @var int */
        return $this->query()
            ->fromIp($ipAddress)
            ->failed()
            ->recent($minutes)
            ->delete();
    }

    /**
     * Get IPs that have exceeded the failure threshold.
     *
     * @return SupportCollection<int, stdClass>
     */
    public function getBlockedIps(int $threshold = 10, int $minutes = 10): SupportCollection
    {
        $results = $this->query()
            ->failed()
            ->recent($minutes)
            ->selectRaw('ip_address, COUNT(*) as failure_count')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->orderByDesc('failure_count')
            ->get();

        $mapped = collect();
        foreach ($results as $row) {
            $failureCount = $row->getAttribute('failure_count');
            $mapped->push((object) [
                'ip_address' => $row->ip_address,
                'failure_count' => is_numeric($failureCount) ? (int) $failureCount : 0,
            ]);
        }

        /** @var SupportCollection<int, stdClass> */
        return $mapped;
    }

    /**
     * Clear all recent failed attempts within the time window.
     */
    public function clearAllFailedRecent(int $minutes = 10): int
    {
        /** @var int */
        return $this->query()
            ->failed()
            ->recent($minutes)
            ->delete();
    }
}
