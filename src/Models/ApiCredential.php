<?php

declare(strict_types=1);

namespace HmacAuth\Models;

use HmacAuth\Concerns\HasTenantScoping;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $client_id
 * @property string|null $client_secret
 * @property string|null $hmac_algorithm
 * @property string $environment
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property string|null $old_client_secret
 * @property Carbon|null $old_secret_expires_at
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> active()
 * @method static Builder<static> forTenant(int|string $tenantId)
 * @method static Builder<static> forEnvironment(string $environment)
 * @method static Builder<static> expired()
 * @method static Builder<static> expiringSoon(int $days = 7)
 * @method static Builder<static> withDefaultRelations()
 * @method static Builder<static> searchByTerm(string $term)
 *
 * @mixin Builder<ApiCredential>
 */
class ApiCredential extends Model
{
    use HasTenantScoping;

    public const string ENVIRONMENT_PRODUCTION = 'production';

    public const string ENVIRONMENT_TESTING = 'testing';

    /**
     * @var list<string>
     */
    public const array VALID_ENVIRONMENTS = [
        self::ENVIRONMENT_PRODUCTION,
        self::ENVIRONMENT_TESTING,
    ];

    protected $fillable = [
        'client_id',
        'client_secret',
        'hmac_algorithm',
        'environment',
        'is_active',
        'last_used_at',
        'expires_at',
        'old_client_secret',
        'old_secret_expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'old_secret_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret',
        'old_client_secret',
    ];

    protected function setClientSecretAttribute(string $value): void
    {
        $this->attributes['client_secret'] = Crypt::encryptString($value);
    }

    protected function getClientSecretAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $decryptException) {
            Log::error('Failed to decrypt client_secret', [
                'credential_id' => $this->id,
                'client_id' => $this->attributes['client_id'] ?? 'unknown',
                'error' => $decryptException->getMessage(),
            ]);

            return null;
        }
    }

    protected function setOldClientSecretAttribute(?string $value): void
    {
        $this->attributes['old_client_secret'] = $value !== null ? Crypt::encryptString($value) : null;
    }

    protected function getOldClientSecretAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::warning('Failed to decrypt old_client_secret', [
                'credential_id' => $this->id,
                'client_id' => $this->attributes['client_id'] ?? 'unknown',
            ]);

            return null;
        }
    }

    protected function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    protected function scopeForEnvironment(Builder $query, string $environment): void
    {
        $query->where('environment', $environment);
    }

    protected function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('is_active', true);
    }

    protected function scopeExpiringSoon(Builder $query, int $days = 7): void
    {
        $now = now();
        $query->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays($days))
            ->where('is_active', true);
    }

    protected function scopeWithDefaultRelations(Builder $query): void
    {
        $relations = ['creator'];

        if ($this->isTenancyEnabled()) {
            $relations[] = 'tenant';
        }

        $query->with($relations);
    }

    protected function scopeSearchByTerm(Builder $query, string $term): void
    {
        $escapedTerm = addcslashes($term, '%_\\');
        $query->where('client_id', 'like', '%'.$escapedTerm.'%');
    }

    public static function isValidEnvironment(string $environment): bool
    {
        return in_array($environment, self::VALID_ENVIRONMENTS, true);
    }

    public function matchesCurrentEnvironment(): bool
    {
        /** @var string $appEnv */
        $appEnv = config('app.env', 'local');

        return $this->matchesEnvironment($appEnv);
    }

    public function matchesEnvironment(string $appEnv): bool
    {
        $expectedCredentialEnv = $appEnv === 'production'
            ? self::ENVIRONMENT_PRODUCTION
            : self::ENVIRONMENT_TESTING;

        return $this->environment === $expectedCredentialEnv;
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENVIRONMENT_PRODUCTION;
    }

    public function isTesting(): bool
    {
        return $this->environment === self::ENVIRONMENT_TESTING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /** Uses constant-time comparison to prevent timing attacks. */
    public function verifySecret(string $secret): bool
    {
        // Check the current secret (decrypted automatically)
        if ($this->client_secret !== null && hash_equals($this->client_secret, $secret)) {
            return true;
        }

        // Check old secret if in overlap period
        if ($this->old_client_secret !== null &&
            $this->old_secret_expires_at !== null &&
            $this->old_secret_expires_at->isFuture()) {
            return hash_equals($this->old_client_secret, $secret);
        }

        return false;
    }

    public function markAsUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    protected function getUserModelClass(): string
    {
        /** @var string */
        return config('hmac.models.user', 'App\\Models\\User');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo($this->getUserModelClass(), 'created_by');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }
}
