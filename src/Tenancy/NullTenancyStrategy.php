<?php

declare(strict_types=1);

namespace HmacAuth\Tenancy;

use HmacAuth\Contracts\TenancyScopeStrategyInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Null strategy for when tenancy is disabled.
 *
 * @implements TenancyScopeStrategyInterface<Model>
 */
final readonly class NullTenancyStrategy implements TenancyScopeStrategyInterface
{
    public function isActive(): bool
    {
        return false;
    }

    public function getTenantColumn(): string
    {
        return '';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyScope(Builder $query, int|string $tenantId): Builder
    {
        // No-op: tenancy is disabled
        return $query;
    }

    /**
     * @return array<string>
     */
    public function getFillable(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getHidden(): array
    {
        return [];
    }
}
