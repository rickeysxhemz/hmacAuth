<?php

declare(strict_types=1);

use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Tenancy\DatabaseTenancyStrategy;

describe('DatabaseTenancyStrategy', function () {
    beforeEach(function () {
        $this->config = Mockery::mock(TenancyConfigInterface::class);
        $this->config->shouldReceive('getColumn')->andReturn('tenant_id');
        $this->strategy = new DatabaseTenancyStrategy($this->config);
    });

    describe('isActive', function () {
        it('returns true when tenancy is enabled', function () {
            expect($this->strategy->isActive())->toBeTrue();
        });
    });

    describe('getTenantColumn', function () {
        it('returns configured column name', function () {
            expect($this->strategy->getTenantColumn())->toBe('tenant_id');
        });

        it('returns custom column name from config', function () {
            $config = Mockery::mock(TenancyConfigInterface::class);
            $config->shouldReceive('getColumn')->andReturn('organization_id');
            $strategy = new DatabaseTenancyStrategy($config);

            expect($strategy->getTenantColumn())->toBe('organization_id');
        });
    });

    describe('applyScope', function () {
        it('adds tenant where clause to query', function () {
            $query = ApiCredential::query();

            $result = $this->strategy->applyScope($query, 123);
            $wheres = $result->getQuery()->wheres;

            expect($wheres)->toHaveCount(1);
            expect($wheres[0]['column'])->toBe('tenant_id');
            expect($wheres[0]['value'])->toBe(123);
        });

        it('works with string tenant IDs', function () {
            $query = ApiCredential::query();

            $result = $this->strategy->applyScope($query, 'tenant-uuid');
            $wheres = $result->getQuery()->wheres;

            expect($wheres[0]['value'])->toBe('tenant-uuid');
        });

        it('uses configured column name for scoping', function () {
            $config = Mockery::mock(TenancyConfigInterface::class);
            $config->shouldReceive('getColumn')->andReturn('org_id');
            $strategy = new DatabaseTenancyStrategy($config);

            $query = ApiCredential::query();
            $result = $strategy->applyScope($query, 1);

            expect($result->getQuery()->wheres[0]['column'])->toBe('org_id');
        });
    });

    describe('getFillable', function () {
        it('returns array with tenant column', function () {
            expect($this->strategy->getFillable())->toBe(['tenant_id']);
        });

        it('returns configured column name in array', function () {
            $config = Mockery::mock(TenancyConfigInterface::class);
            $config->shouldReceive('getColumn')->andReturn('company_id');
            $strategy = new DatabaseTenancyStrategy($config);

            expect($strategy->getFillable())->toBe(['company_id']);
        });
    });

    describe('getHidden', function () {
        it('returns array with tenant column', function () {
            expect($this->strategy->getHidden())->toBe(['tenant_id']);
        });

        it('returns configured column name in array', function () {
            $config = Mockery::mock(TenancyConfigInterface::class);
            $config->shouldReceive('getColumn')->andReturn('workspace_id');
            $strategy = new DatabaseTenancyStrategy($config);

            expect($strategy->getHidden())->toBe(['workspace_id']);
        });
    });
});
