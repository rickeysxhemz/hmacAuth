<?php

declare(strict_types=1);

use HmacAuth\Models\ApiRequestLog;

describe('ApiRequestLog', function () {
    describe('timestamps', function () {
        it('has timestamps disabled', function () {
            $log = new ApiRequestLog;

            expect($log->timestamps)->toBeFalse();
        });
    });

    describe('fillable (standalone mode)', function () {
        beforeEach(function () {
            config(['hmac.tenancy.enabled' => false]);
        });

        it('has correct fillable attributes in standalone mode', function () {
            $log = new ApiRequestLog;

            expect($log->getFillable())->toBe([
                'api_credential_id',
                'client_id',
                'request_method',
                'request_path',
                'ip_address',
                'user_agent',
                'signature_valid',
                'response_status',
            ]);
        });
    });

    describe('tenancy trait', function () {
        beforeEach(function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
        });

        afterEach(function () {
            config(['hmac.tenancy.enabled' => false]);
        });

        it('uses HasTenantScoping trait', function () {
            $traits = class_uses_recursive(ApiRequestLog::class);

            expect($traits)->toContain('HmacAuth\Concerns\HasTenantScoping');
        });
    });

    describe('casts', function () {
        it('casts signature_valid to boolean', function () {
            $log = new ApiRequestLog;
            $log->signature_valid = 1;

            expect($log->signature_valid)->toBeTrue();

            $log->signature_valid = 0;
            expect($log->signature_valid)->toBeFalse();
        });

        it('casts response_status to integer', function () {
            $log = new ApiRequestLog;
            $log->response_status = '200';

            expect($log->response_status)->toBe(200);
        });

        it('casts created_at to datetime', function () {
            $log = new ApiRequestLog;
            $log->created_at = '2024-01-01 12:00:00';

            expect($log->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('hidden attributes (tenancy mode)', function () {
        beforeEach(function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
        });

        afterEach(function () {
            config(['hmac.tenancy.enabled' => false]);
        });

        it('hides tenant_id in array when tenancy enabled', function () {
            $log = new ApiRequestLog;
            $log->tenant_id = 123;
            $log->client_id = 'test-client';

            $array = $log->toArray();

            expect($array)->not->toHaveKey('tenant_id');
        });
    });

    describe('attribute assignment', function () {
        it('can assign all fillable attributes', function () {
            $log = new ApiRequestLog([
                'api_credential_id' => 1,
                'client_id' => 'test-client',
                'request_method' => 'POST',
                'request_path' => '/api/users',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
                'signature_valid' => true,
                'response_status' => 200,
            ]);

            expect($log->api_credential_id)->toBe(1)
                ->and($log->client_id)->toBe('test-client')
                ->and($log->request_method)->toBe('POST')
                ->and($log->request_path)->toBe('/api/users')
                ->and($log->ip_address)->toBe('192.168.1.1')
                ->and($log->user_agent)->toBe('Mozilla/5.0')
                ->and($log->signature_valid)->toBeTrue()
                ->and($log->response_status)->toBe(200);
        });

        it('can have null api_credential_id', function () {
            $log = new ApiRequestLog([
                'api_credential_id' => null,
                'client_id' => 'test-client',
            ]);

            expect($log->api_credential_id)->toBeNull();
        });

        it('can have null user_agent', function () {
            $log = new ApiRequestLog([
                'client_id' => 'test-client',
                'user_agent' => null,
            ]);

            expect($log->user_agent)->toBeNull();
        });
    });

    describe('scope methods', function () {
        // These tests would require database integration
        // For unit tests, we just verify the methods exist
        it('has failed scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeFailed'))->toBeTrue();
        });

        it('has successful scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeSuccessful'))->toBeTrue();
        });

        it('has forTenant scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeForTenant'))->toBeTrue();
        });

        it('has forClient scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeForClient'))->toBeTrue();
        });

        it('has dateRange scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeDateRange'))->toBeTrue();
        });

        it('has recent scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeRecent'))->toBeTrue();
        });

        it('has fromIp scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeFromIp'))->toBeTrue();
        });
    });

    describe('relationships', function () {
        it('has apiCredential relationship method', function () {
            expect(method_exists(ApiRequestLog::class, 'apiCredential'))->toBeTrue();
        });

        it('has tenant relationship method', function () {
            expect(method_exists(ApiRequestLog::class, 'tenant'))->toBeTrue();
        });

        it('apiCredential returns BelongsTo relationship', function () {
            $log = new ApiRequestLog;
            $relation = $log->apiCredential();

            expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        });
    });

    describe('prunable', function () {
        it('uses MassPrunable trait', function () {
            $traits = class_uses_recursive(ApiRequestLog::class);

            expect($traits)->toContain('Illuminate\Database\Eloquent\MassPrunable');
        });

        it('has prunable method', function () {
            expect(method_exists(ApiRequestLog::class, 'prunable'))->toBeTrue();
        });

        it('returns query for logs older than configured days', function () {
            config(['hmac.log_retention_days' => 30]);

            $log = new ApiRequestLog;
            $query = $log->prunable();

            expect($query)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
        });
    });

    describe('scopes with database', function () {
        beforeEach(function () {
            // Create test logs
            ApiRequestLog::insert([
                'client_id' => 'test-client-1',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '192.168.1.1',
                'signature_valid' => true,
                'response_status' => 200,
                'created_at' => now(),
            ]);

            ApiRequestLog::insert([
                'client_id' => 'test-client-2',
                'request_method' => 'POST',
                'request_path' => '/api/users',
                'ip_address' => '192.168.1.2',
                'signature_valid' => false,
                'response_status' => 401,
                'created_at' => now(),
            ]);
        });

        it('failed scope filters failed attempts', function () {
            $failedLogs = ApiRequestLog::failed()->get();

            expect($failedLogs)->toHaveCount(1)
                ->and($failedLogs->first()->signature_valid)->toBeFalse();
        });

        it('successful scope filters successful attempts', function () {
            $successfulLogs = ApiRequestLog::successful()->get();

            expect($successfulLogs)->toHaveCount(1)
                ->and($successfulLogs->first()->signature_valid)->toBeTrue();
        });

        it('forClient scope filters by client', function () {
            $logs = ApiRequestLog::forClient('test-client-1')->get();

            expect($logs)->toHaveCount(1)
                ->and($logs->first()->client_id)->toBe('test-client-1');
        });

        it('fromIp scope filters by IP address', function () {
            $logs = ApiRequestLog::fromIp('192.168.1.1')->get();

            expect($logs)->toHaveCount(1)
                ->and($logs->first()->ip_address)->toBe('192.168.1.1');
        });

        it('recent scope filters by time', function () {
            $logs = ApiRequestLog::recent(60)->get();

            expect($logs)->toHaveCount(2);
        });

        it('dateRange scope filters by date range', function () {
            $from = now()->subHour();
            $to = now()->addHour();

            $logs = ApiRequestLog::dateRange($from, $to)->get();

            expect($logs)->toHaveCount(2);
        });
    });
});
