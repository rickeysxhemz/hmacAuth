<?php

declare(strict_types=1);

use HmacAuth\Models\ApiRequestLog;

describe('CleanupLogsCommand', function () {
    it('deletes old logs', function () {
        // Create old logs - use insert to bypass model mutators and set exact date
        ApiRequestLog::insert([
            'client_id' => 'test_old',
            'request_method' => 'GET',
            'request_path' => '/api/test',
            'ip_address' => '127.0.0.1',
            'response_status' => 200,
            'signature_valid' => true,
            'created_at' => now()->subDays(60)->toDateTimeString(),
        ]);

        // Create recent logs
        ApiRequestLog::insert([
            'client_id' => 'test_recent',
            'request_method' => 'GET',
            'request_path' => '/api/test',
            'ip_address' => '127.0.0.1',
            'response_status' => 200,
            'signature_valid' => true,
            'created_at' => now()->subDays(10)->toDateTimeString(),
        ]);

        expect(ApiRequestLog::count())->toBe(2);

        $this->artisan('hmac:cleanup', ['--days' => '30'])
            ->expectsOutputToContain('Deleted 1 log entries')
            ->assertSuccessful();

        expect(ApiRequestLog::count())->toBe(1);
    });

    it('supports dry run mode', function () {
        // Create an old log entry using query builder for precise date control
        $oldDate = now()->subDays(60);
        \Illuminate\Support\Facades\DB::table('api_request_logs')->insert([
            'client_id' => 'test_dry',
            'request_method' => 'GET',
            'request_path' => '/api/test',
            'ip_address' => '127.0.0.1',
            'response_status' => 200,
            'signature_valid' => 1,
            'created_at' => $oldDate->format('Y-m-d H:i:s'),
        ]);

        // Verify record exists and is old enough
        expect(ApiRequestLog::count())->toBe(1);
        $log = ApiRequestLog::first();
        expect($log->created_at->lt(now()->subDays(30)))->toBeTrue();

        // Run command and capture output
        $exitCode = \Illuminate\Support\Facades\Artisan::call('hmac:cleanup', [
            '--days' => '30',
            '--dry-run' => true,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        expect($exitCode)->toBe(0);
        expect($output)->toContain('[DRY RUN]');
        expect($output)->toContain('Would delete');
        expect($output)->toContain('log entries');

        // Dry run should not delete
        expect(ApiRequestLog::count())->toBe(1);
    });

    it('fails with invalid days parameter', function () {
        $this->artisan('hmac:cleanup', ['--days' => '0'])
            ->expectsOutput('Days must be at least 1')
            ->assertFailed();
    });

    it('handles empty logs gracefully', function () {
        $this->artisan('hmac:cleanup', ['--days' => '30'])
            ->expectsOutputToContain('Deleted 0 log entries')
            ->assertSuccessful();
    });

    it('uses default 30 days when not specified', function () {
        ApiRequestLog::insert([
            'client_id' => 'test_default',
            'request_method' => 'GET',
            'request_path' => '/api/test',
            'ip_address' => '127.0.0.1',
            'response_status' => 200,
            'signature_valid' => true,
            'created_at' => now()->subDays(31)->toDateTimeString(),
        ]);

        $this->artisan('hmac:cleanup')
            ->expectsOutputToContain('Deleted 1 log entries')
            ->assertSuccessful();
    });
});
