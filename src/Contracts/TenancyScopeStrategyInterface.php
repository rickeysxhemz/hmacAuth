<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Strategy interface for applying tenant scoping to queries and models.
 *
 * @template TModel of Model
 */
interface TenancyScopeStrategyInterface
{
    /**
     * Check if tenancy scoping is active.
     */
    public function isActive(): bool;

    /**
     * Get the tenant column name.
     */
    public function getTenantColumn(): string;

    /**
     * Apply tenant scope to a query builder.
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyScope(Builder $query, int|string $tenantId): Builder;

    /**
     * Get the fillable fields for tenant scoping.
     *
     * @return array<string>
     */
    public function getFillable(): array;

    /**
     * Get the hidden fields for tenant scoping.
     *
     * @return array<string>
     */
    public function getHidden(): array;
}
