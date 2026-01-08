<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provides multi-tenancy support for models.
 *
 * When tenancy is enabled via config, this trait:
 * - Adds the tenant column to fillable/hidden arrays
 * - Provides a tenant() relationship
 * - Provides a forTenant() query scope
 */
trait HasTenantScoping
{
    /**
     * Initialize the trait - adds tenant column to fillable/hidden when tenancy enabled.
     */
    public function initializeHasTenantScoping(): void
    {
        if ($this->isTenancyEnabled()) {
            $column = $this->getTenantColumn();

            if (! in_array($column, $this->fillable, true)) {
                $this->fillable[] = $column;
            }

            if (! in_array($column, $this->hidden, true)) {
                $this->hidden[] = $column;
            }
        }
    }

    /**
     * Check if multi-tenancy is enabled.
     */
    protected function isTenancyEnabled(): bool
    {
        return (bool) config('hmac.tenancy.enabled', false);
    }

    /**
     * Get the configured tenant column name.
     */
    protected function getTenantColumn(): string
    {
        return (string) config('hmac.tenancy.column', 'tenant_id');
    }

    /**
     * Get the configured tenant model class.
     */
    protected function getTenantModelClass(): string
    {
        return (string) config('hmac.tenancy.model', 'App\\Models\\Tenant');
    }

    /**
     * Get the tenant relationship.
     *
     * @return BelongsTo<Model, $this>
     */
    public function tenant(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->getTenantModelClass();

        return $this->belongsTo($modelClass, $this->getTenantColumn());
    }

    /**
     * Scope query to a specific tenant.
     *
     * @param  Builder<static>  $query
     */
    protected function scopeForTenant(Builder $query, int|string $tenantId): void
    {
        if ($this->isTenancyEnabled()) {
            $query->where($this->getTenantColumn(), $tenantId);
        }
    }

    /**
     * Get the tenant ID attribute dynamically.
     */
    public function getTenantIdAttribute(): int|string|null
    {
        if (! $this->isTenancyEnabled()) {
            return null;
        }

        $value = $this->getAttribute($this->getTenantColumn());

        return is_int($value) || is_string($value) ? $value : null;
    }

    /**
     * Set the tenant ID attribute dynamically.
     */
    public function setTenantIdAttribute(int|string $value): void
    {
        if ($this->isTenancyEnabled()) {
            $this->setAttribute($this->getTenantColumn(), $value);
        }
    }
}
