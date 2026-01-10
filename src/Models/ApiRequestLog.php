<?php

declare(strict_types=1);

namespace HmacAuth\Models;

use Carbon\CarbonInterface;
use HmacAuth\Concerns\HasTenantScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $api_credential_id
 * @property string $client_id
 * @property string $request_method
 * @property string $request_path
 * @property string $ip_address
 * @property string|null $user_agent
 * @property bool $signature_valid
 * @property int $response_status
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @method static Builder<static> failed()
 * @method static Builder<static> successful()
 * @method static Builder<static> forTenant(int|string $tenantId)
 * @method static Builder<static> forClient(string $clientId)
 * @method static Builder<static> dateRange(CarbonInterface $from, CarbonInterface $to)
 * @method static Builder<static> recent(int $minutes)
 * @method static Builder<static> fromIp(string $ipAddress)
 *
 * @mixin Builder<ApiRequestLog>
 */
class ApiRequestLog extends Model
{
    use HasTenantScoping;
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'api_credential_id',
        'client_id',
        'request_method',
        'request_path',
        'ip_address',
        'user_agent',
        'signature_valid',
        'response_status',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'response_status' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the prunable model query for logs older than configured days.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('hmac.log_retention_days', 30);

        return static::where('created_at', '<', now()->subDays($days));
    }

    /**
     * Scope: Get failed authentication attempts
     */
    protected function scopeFailed(Builder $query): void
    {
        $query->where('signature_valid', false);
    }

    /**
     * Scope: Get successful authentication attempts
     */
    protected function scopeSuccessful(Builder $query): void
    {
        $query->where('signature_valid', true);
    }

    /**
     * Scope: Filter by client
     */
    protected function scopeForClient(Builder $query, string $clientId): void
    {
        $query->where('client_id', $clientId);
    }

    /**
     * Scope: Filter by date range
     */
    protected function scopeDateRange(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope: Filter records from last N minutes
     */
    protected function scopeRecent(Builder $query, int $minutes): void
    {
        $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope: Filter by IP address
     */
    protected function scopeFromIp(Builder $query, string $ipAddress): void
    {
        $query->where('ip_address', $ipAddress);
    }

    /**
     * Relationships
     */
    public function apiCredential(): BelongsTo
    {
        return $this->belongsTo(ApiCredential::class);
    }
}
