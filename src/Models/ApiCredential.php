<?php

declare(strict_types=1);

namespace HmacAuth\Models;

use HmacAuth\Concerns\HasTenantScoping;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $client_id
 * @property string|null $client_secret
 * @property string|null $hmac_algorithm
 * @property string $environment
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $old_client_secret
 * @property \Illuminate\Support\Carbon|null $old_secret_expires_at
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
    /**
     * Valid environment values for API credentials.
     */
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

    /**
     * Automatically encrypt client_secret when setting
     */
    protected function setClientSecretAttribute(string $value): void
    {
        $this->attributes['client_secret'] = Crypt::encryptString($value);
    }

    /**
     * Automatically decrypt client_secret when getting
     * Returns null if decryption fails (e.g., APP_KEY changed)
     */
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

    /**
     * Automatically encrypt old_client_secret when setting
     */
    protected function setOldClientSecretAttribute(?string $value): void
    {
        $this->attributes['old_client_secret'] = $value !== null ? Crypt::encryptString($value) : null;
    }

    /**
     * Automatically decrypt old_client_secret when getting
     * Returns null if decryption fails (e.g., APP_KEY changed)
     */
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

    /**
     * Scope: Get only active credentials
     */
    protected function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Filter by environment
     */
    protected function scopeForEnvironment(Builder $query, string $environment): void
    {
        $query->where('environment', $environment);
    }

    /**
     * Scope: Get expired credentials (past expiration but still marked active)
     */
    protected function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('is_active', true);
    }

    /**
     * Scope: Get credentials expiring within specified days
     */
    protected function scopeExpiringSoon(Builder $query, int $days = 7): void
    {
        $now = now();
        $query->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays($days))
            ->where('is_active', true);
    }

    /**
     * Scope: Eager load default relations
     */
    protected function scopeWithDefaultRelations(Builder $query): void
    {
        $relations = ['creator'];

        if ($this->isTenancyEnabled()) {
            $relations[] = 'tenant';
        }

        $query->with($relations);
    }

    /**
     * Scope: Search by client ID or company name
     */
    protected function scopeSearchByTerm(Builder $query, string $term): void
    {
        $escapedTerm = addcslashes($term, '%_\\');

        $query->where(function (Builder $q) use ($escapedTerm): void {
            $q->where('client_id', 'like', '%'.$escapedTerm.'%');
        });
    }

    /**
     * Check if environment value is valid
     */
    public static function isValidEnvironment(string $environment): bool
    {
        return in_array($environment, self::VALID_ENVIRONMENTS, true);
    }

    /**
     * Check if credential matches the current application environment
     */
    public function matchesCurrentEnvironment(): bool
    {
        /** @var string $appEnv */
        $appEnv = config('app.env', 'local');

        return $this->matchesEnvironment($appEnv);
    }

    /**
     * Check if credential matches the given application environment
     */
    public function matchesEnvironment(string $appEnv): bool
    {
        $expectedCredentialEnv = $appEnv === 'production'
            ? self::ENVIRONMENT_PRODUCTION
            : self::ENVIRONMENT_TESTING;

        return $this->environment === $expectedCredentialEnv;
    }

    /**
     * Check if credential is for production
     */
    public function isProduction(): bool
    {
        return $this->environment === self::ENVIRONMENT_PRODUCTION;
    }

    /**
     * Check if credential is for testing
     */
    public function isTesting(): bool
    {
        return $this->environment === self::ENVIRONMENT_TESTING;
    }

    /**
     * Check if credential is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if credential is valid (active and not expired)
     */
    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /**
     * Verify secret against stored encrypted secret (for secret rotation)
     * Uses constant-time comparison to prevent timing attacks
     */
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

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get the user model class from config.
     */
    protected function getUserModelClass(): string
    {
        /** @var string */
        return config('hmac.models.user', 'App\\Models\\User');
    }

    /**
     * Relationships
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo($this->getUserModelClass(), 'created_by');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }
}
