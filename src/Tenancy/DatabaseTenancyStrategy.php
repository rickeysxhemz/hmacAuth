<?php

declare(strict_types=1);

namespace HmacAuth\Tenancy;

use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\Contracts\TenancyScopeStrategyInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Database strategy for when tenancy is enabled.
 *
 * @implements TenancyScopeStrategyInterface<Model>
 */
final readonly class DatabaseTenancyStrategy implements TenancyScopeStrategyInterface
{
    public function __construct(
        private TenancyConfigInterface $config,
    ) {}

    public function isActive(): bool
    {
        return true;
    }

    public function getTenantColumn(): string
    {
        return $this->config->getColumn();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyScope(Builder $query, int|string $tenantId): Builder
    {
        return $query->where($this->getTenantColumn(), $tenantId);
    }

    /**
     * @return array<string>
     */
    public function getFillable(): array
    {
        return [$this->getTenantColumn()];
    }

    /**
     * @return array<string>
     */
    public function getHidden(): array
    {
        return [$this->getTenantColumn()];
    }
}
