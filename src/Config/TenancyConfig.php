<?php

declare(strict_types=1);

namespace HmacAuth\Config;

use HmacAuth\Contracts\TenancyConfigInterface;

/**
 * Tenancy configuration implementation.
 */
final readonly class TenancyConfig implements TenancyConfigInterface
{
    public function __construct(
        private bool $enabled,
        private string $column,
        private string $modelClass,
    ) {}

    /**
     * Create instance from Laravel config.
     */
    public static function fromConfig(): self
    {
        return new self(
            enabled: (bool) config('hmac.tenancy.enabled', false),
            column: (string) config('hmac.tenancy.column', 'tenant_id'),
            modelClass: (string) config('hmac.tenancy.model', 'App\\Models\\Tenant'),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * @return list<string>
     */
    public function getDefaultRelations(): array
    {
        $relations = ['creator'];

        if ($this->enabled) {
            $relations[] = 'tenant';
        }

        return $relations;
    }
}
