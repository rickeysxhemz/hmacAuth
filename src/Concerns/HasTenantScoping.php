<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasTenantScoping
{
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

    protected function isTenancyEnabled(): bool
    {
        return (bool) config('hmac.tenancy.enabled', false);
    }

    protected function getTenantColumn(): string
    {
        return (string) config('hmac.tenancy.column', 'tenant_id');
    }

    protected function getTenantModelClass(): string
    {
        return (string) config('hmac.tenancy.model', 'App\\Models\\Tenant');
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
