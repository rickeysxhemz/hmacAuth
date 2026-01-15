<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

/**
 * Interface for tenancy configuration.
 */
interface TenancyConfigInterface
{
    /**
     * Check if tenancy is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the tenant column name.
     */
    public function getColumn(): string;

    /**
     * Get the tenant model class.
     */
    public function getModelClass(): string;

    /**
     * Get the default relations to load based on tenancy status.
     *
     * @return list<string>
     */
    public function getDefaultRelations(): array;
}
