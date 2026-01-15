<?php

declare(strict_types=1);

use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\Models\ApiRequestLog;
use HmacAuth\Repositories\ApiRequestLogRepository;

describe('ApiRequestLogRepository', function () {
    beforeEach(function () {
        $this->tenancyConfig = Mockery::mock(TenancyConfigInterface::class);
        $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false)->byDefault();
        $this->repository = new ApiRequestLogRepository($this->tenancyConfig);
    });

    describe('create', function () {
        it('creates new log entry', function () {
            $log = $this->repository->create([
                'client_id' => 'test_create',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now(),
            ]);

            expect($log)->toBeInstanceOf(ApiRequestLog::class);
            expect($log->exists)->toBeTrue();
        });
    });

    describe('getByClient', function () {
        it('returns logs for specific client', function () {
            ApiRequestLog::create([
                'client_id' => 'test_client1',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now(),
            ]);

            ApiRequestLog::create([
                'client_id' => 'test_client2',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now(),
            ]);

            $logs = $this->repository->getByClient('test_client1');

            expect($logs)->toHaveCount(1);
            expect($logs->first()->client_id)->toBe('test_client1');
        });

        it('respects limit parameter', function () {
            for ($i = 0; $i < 10; $i++) {
                ApiRequestLog::create([
                    'client_id' => 'test_limit',
                    'request_method' => 'GET',
                    'request_path' => '/api/test',
                    'ip_address' => '127.0.0.1',
                    'response_status' => 200,
                    'signature_valid' => true,
                    'created_at' => now(),
                ]);
            }

            $logs = $this->repository->getByClient('test_limit', 5);

            expect($logs)->toHaveCount(5);
        });
    });

    describe('getFailedAttempts', function () {
        it('returns only failed logs for client', function () {
            ApiRequestLog::create([
                'client_id' => 'test_failed',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            ApiRequestLog::create([
                'client_id' => 'test_failed',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now(),
            ]);

            $logs = $this->repository->getFailedAttempts('test_failed');

            expect($logs)->toHaveCount(1);
            expect($logs->first()->signature_valid)->toBeFalse();
        });
    });

    describe('getRecentFailedByIp', function () {
        it('returns failed logs by IP within time window', function () {
            ApiRequestLog::create([
                'client_id' => 'test_ip',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '192.168.1.1',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            ApiRequestLog::create([
                'client_id' => 'test_ip',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '192.168.1.2',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            $logs = $this->repository->getRecentFailedByIp('192.168.1.1');

            expect($logs)->toHaveCount(1);
            expect($logs->first()->ip_address)->toBe('192.168.1.1');
        });
    });

    describe('countFailedAttempts', function () {
        it('counts failed attempts for client in time window', function () {
            ApiRequestLog::create([
                'client_id' => 'test_count',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            ApiRequestLog::create([
                'client_id' => 'test_count',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            $count = $this->repository->countFailedAttempts('test_count', 10);

            expect($count)->toBe(2);
        });
    });

    describe('countFailedByIp', function () {
        it('counts failed attempts by IP in time window', function () {
            ApiRequestLog::create([
                'client_id' => 'test_ip_count',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '10.0.0.1',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            $count = $this->repository->countFailedByIp('10.0.0.1', 10);

            expect($count)->toBe(1);
        });
    });

    describe('getByDateRange', function () {
        it('returns logs within date range', function () {
            // Use insert with toDateTimeString() for proper SQLite date formatting
            ApiRequestLog::insert([
                'client_id' => 'test_date',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now()->subDays(5)->toDateTimeString(),
            ]);

            ApiRequestLog::insert([
                'client_id' => 'test_date_old',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now()->subDays(15)->toDateTimeString(),
            ]);

            $logs = $this->repository->getByDateRange(
                now()->subDays(10),
                now()
            );

            expect($logs)->toHaveCount(1);
        });
    });

    describe('countOlderThan', function () {
        it('counts logs older than specified days', function () {
            ApiRequestLog::insert([
                'client_id' => 'test_old',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now()->subDays(60)->toDateTimeString(),
            ]);

            ApiRequestLog::insert([
                'client_id' => 'test_new',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now()->toDateTimeString(),
            ]);

            $count = $this->repository->countOlderThan(30);

            expect($count)->toBe(1);
        });
    });

    describe('deleteOlderThan', function () {
        it('deletes logs older than specified days', function () {
            ApiRequestLog::insert([
                'client_id' => 'test_delete_old',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now()->subDays(60)->toDateTimeString(),
            ]);

            ApiRequestLog::insert([
                'client_id' => 'test_delete_new',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 200,
                'signature_valid' => true,
                'created_at' => now()->toDateTimeString(),
            ]);

            $deleted = $this->repository->deleteOlderThan(30);

            expect($deleted)->toBe(1);
            expect(ApiRequestLog::count())->toBe(1);
        });
    });

    describe('deleteFailedByIp', function () {
        it('deletes recent failed attempts by IP', function () {
            ApiRequestLog::create([
                'client_id' => 'test_delete_ip',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '192.168.1.100',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            $deleted = $this->repository->deleteFailedByIp('192.168.1.100', 10);

            expect($deleted)->toBe(1);
        });
    });

    describe('getBlockedIps', function () {
        it('returns IPs that exceeded failure threshold', function () {
            for ($i = 0; $i < 15; $i++) {
                ApiRequestLog::create([
                    'client_id' => 'test_blocked',
                    'request_method' => 'GET',
                    'request_path' => '/api/test',
                    'ip_address' => '192.168.1.200',
                    'response_status' => 401,
                    'signature_valid' => false,
                    'created_at' => now(),
                ]);
            }

            $blocked = $this->repository->getBlockedIps(10, 10);

            expect($blocked)->toHaveCount(1);
            expect($blocked->first()->ip_address)->toBe('192.168.1.200');
            expect($blocked->first()->failure_count)->toBe(15);
        });

        it('does not return IPs below threshold', function () {
            for ($i = 0; $i < 5; $i++) {
                ApiRequestLog::create([
                    'client_id' => 'test_not_blocked',
                    'request_method' => 'GET',
                    'request_path' => '/api/test',
                    'ip_address' => '192.168.1.201',
                    'response_status' => 401,
                    'signature_valid' => false,
                    'created_at' => now(),
                ]);
            }

            $blocked = $this->repository->getBlockedIps(10, 10);

            expect($blocked)->toHaveCount(0);
        });
    });

    describe('clearAllFailedRecent', function () {
        it('clears all recent failed attempts', function () {
            ApiRequestLog::create([
                'client_id' => 'test_clear1',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.1',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            ApiRequestLog::create([
                'client_id' => 'test_clear2',
                'request_method' => 'GET',
                'request_path' => '/api/test',
                'ip_address' => '127.0.0.2',
                'response_status' => 401,
                'signature_valid' => false,
                'created_at' => now(),
            ]);

            $deleted = $this->repository->clearAllFailedRecent(10);

            expect($deleted)->toBe(2);
        });
    });

    describe('tenant methods', function () {
        it('throws exception when tenancy disabled for getByTenant', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);

            $this->repository->getByTenant(1);
        })->throws(RuntimeException::class, 'Tenancy is not enabled');

        it('throws exception when tenancy disabled for getStatsForTenant', function () {
            $this->tenancyConfig->shouldReceive('isEnabled')->andReturn(false);

            $this->repository->getStatsForTenant(1);
        })->throws(RuntimeException::class, 'Tenancy is not enabled');
    });
});
