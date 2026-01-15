<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

use HmacAuth\Contracts\TenancyConfigInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping for Eloquent models.
 *
 * Note: Add tenant column to $fillable and $hidden when tenancy is enabled.
 */
trait HasTenantScoping
{
    private ?TenancyConfigInterface $tenancyConfigCache = null;

    protected function getTenancyConfig(): TenancyConfigInterface
    {
        if ($this->tenancyConfigCache === null) {
            $this->tenancyConfigCache = app(TenancyConfigInterface::class);
        }

        return $this->tenancyConfigCache;
    }

    protected function isTenancyEnabled(): bool
    {
        return $this->getTenancyConfig()->isEnabled();
    }

    protected function getTenantColumn(): string
    {
        return $this->getTenancyConfig()->getColumn();
    }

    protected function getTenantModelClass(): string
    {
        return $this->getTenancyConfig()->getModelClass();
    }

    /** @return BelongsTo<Model, $this> */
    public function tenant(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->getTenantModelClass();

        return $this->belongsTo($modelClass, $this->getTenantColumn());
    }

    /** @param Builder<static> $query */
    protected function scopeForTenant(Builder $query, int|string $tenantId): void
    {
        if ($this->isTenancyEnabled()) {
            $query->where($this->getTenantColumn(), $tenantId);
        }
    }

    public function getTenantIdAttribute(): int|string|null
    {
        if (! $this->isTenancyEnabled()) {
            return null;
        }

        $column = $this->getTenantColumn();
        $value = $this->attributes[$column] ?? null;

        return is_int($value) || is_string($value) ? $value : null;
    }

    public function setTenantIdAttribute(int|string $value): void
    {
        if ($this->isTenancyEnabled()) {
            $this->attributes[$this->getTenantColumn()] = $value;
        }
    }
}
