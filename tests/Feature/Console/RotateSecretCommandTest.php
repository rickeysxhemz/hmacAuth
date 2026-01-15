<?php

declare(strict_types=1);

use HmacAuth\Models\ApiCredential;

describe('RotateSecretCommand', function () {
    it('rotates secret for existing credential', function () {
        $credential = ApiCredential::create([
            'client_id' => 'test_rotate123',
            'client_secret' => generateTestSecret(),
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => true,
            'created_by' => 1,
        ]);

        $oldSecret = $credential->client_secret;

        $this->artisan('hmac:rotate', ['credential' => $credential->client_id])
            ->expectsConfirmation('Are you sure you want to rotate this secret?', 'yes')
            ->expectsOutput('Secret rotated successfully!')
            ->expectsOutputToContain('New Secret')
            ->assertSuccessful();

        $credential->refresh();
        expect($credential->client_secret)->not->toBe($oldSecret);
        expect($credential->old_client_secret)->toBe($oldSecret);
    });

    it('finds credential by ID', function () {
        $credential = ApiCredential::create([
            'client_id' => 'test_byid',
            'client_secret' => generateTestSecret(),
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => true,
            'created_by' => 1,
        ]);

        $this->artisan('hmac:rotate', ['credential' => (string) $credential->id])
            ->expectsConfirmation('Are you sure you want to rotate this secret?', 'yes')
            ->assertSuccessful();
    });

    it('fails when credential not found', function () {
        $this->artisan('hmac:rotate', ['credential' => 'nonexistent'])
            ->expectsOutput('Credential not found: nonexistent')
            ->assertFailed();
    });

    it('fails for inactive credential', function () {
        $credential = ApiCredential::create([
            'client_id' => 'test_inactive',
            'client_secret' => generateTestSecret(),
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => false,
            'created_by' => 1,
        ]);

        $this->artisan('hmac:rotate', ['credential' => $credential->client_id])
            ->expectsOutput('Cannot rotate secret for inactive credential')
            ->assertFailed();
    });

    it('cancels when user declines confirmation', function () {
        $credential = ApiCredential::create([
            'client_id' => 'test_cancel',
            'client_secret' => generateTestSecret(),
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => true,
            'created_by' => 1,
        ]);

        $originalSecret = $credential->client_secret;

        $this->artisan('hmac:rotate', ['credential' => $credential->client_id])
            ->expectsConfirmation('Are you sure you want to rotate this secret?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        $credential->refresh();
        expect($credential->client_secret)->toBe($originalSecret);
    });

    it('uses custom grace days', function () {
        $credential = ApiCredential::create([
            'client_id' => 'test_grace',
            'client_secret' => generateTestSecret(),
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => true,
            'created_by' => 1,
        ]);

        $this->artisan('hmac:rotate', [
            'credential' => $credential->client_id,
            '--grace-days' => '14',
        ])
            ->expectsConfirmation('Are you sure you want to rotate this secret?', 'yes')
            ->expectsOutputToContain('14 days')
            ->assertSuccessful();
    });
});
