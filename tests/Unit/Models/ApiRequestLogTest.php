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

    describe('fillable', function () {
        it('has correct fillable attributes', function () {
            $log = new ApiRequestLog;

            expect($log->getFillable())->toBe([
                'api_credential_id',
                'company_id',
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

    describe('hidden attributes', function () {
        it('hides company_id in array', function () {
            $log = new ApiRequestLog;
            $log->company_id = 123;
            $log->client_id = 'test-client';

            $array = $log->toArray();

            expect($array)->not->toHaveKey('company_id');
        });
    });

    describe('attribute assignment', function () {
        it('can assign all fillable attributes', function () {
            $log = new ApiRequestLog([
                'api_credential_id' => 1,
                'company_id' => 2,
                'client_id' => 'test-client',
                'request_method' => 'POST',
                'request_path' => '/api/users',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
                'signature_valid' => true,
                'response_status' => 200,
            ]);

            expect($log->api_credential_id)->toBe(1)
                ->and($log->company_id)->toBe(2)
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

        it('has forCompany scope method', function () {
            expect(method_exists(ApiRequestLog::class, 'scopeForCompany'))->toBeTrue();
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

        it('has company relationship method', function () {
            expect(method_exists(ApiRequestLog::class, 'company'))->toBeTrue();
        });
    });
});
