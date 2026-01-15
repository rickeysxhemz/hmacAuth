<?php

declare(strict_types=1);

use HmacAuth\Models\ApiCredential;
use HmacAuth\Tenancy\NullTenancyStrategy;

describe('NullTenancyStrategy', function () {
    beforeEach(function () {
        $this->strategy = new NullTenancyStrategy;
    });

    describe('isActive', function () {
        it('returns false when tenancy is disabled', function () {
            expect($this->strategy->isActive())->toBeFalse();
        });
    });

    describe('getTenantColumn', function () {
        it('returns empty string when tenancy is disabled', function () {
            expect($this->strategy->getTenantColumn())->toBe('');
        });
    });

    describe('applyScope', function () {
        it('returns unmodified query when tenancy is disabled', function () {
            $query = ApiCredential::query();
            $originalSql = $query->toSql();

            $result = $this->strategy->applyScope($query, 1);

            expect($result->toSql())->toBe($originalSql);
        });

        it('does not add where clause for tenant', function () {
            $query = ApiCredential::query();

            $result = $this->strategy->applyScope($query, 123);

            expect($result->getQuery()->wheres)->toBeEmpty();
        });
    });

    describe('getFillable', function () {
        it('returns empty array when tenancy is disabled', function () {
            expect($this->strategy->getFillable())->toBe([]);
        });
    });

    describe('getHidden', function () {
        it('returns empty array when tenancy is disabled', function () {
            expect($this->strategy->getHidden())->toBe([]);
        });
    });
});
