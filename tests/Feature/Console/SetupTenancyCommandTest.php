<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = app(Filesystem::class);
    $this->migrationPath = database_path('migrations');

    // Clean up any existing test migrations
    cleanupTenancyMigrations();
});

afterEach(function () {
    cleanupTenancyMigrations();
});

function cleanupTenancyMigrations(): void
{
    $filesystem = app(Filesystem::class);
    $migrationPath = database_path('migrations');

    if ($filesystem->isDirectory($migrationPath)) {
        foreach ($filesystem->files($migrationPath) as $file) {
            $filename = $file->getFilename();
            if (str_contains($filename, 'tenancy') || str_contains($filename, 'tenant')) {
                $filesystem->delete($file->getPathname());
            }
        }
    }
}

describe('SetupTenancyCommand', function () {
    it('sets up tenancy with default column name', function () {
        $this->artisan('hmac:setup-tenancy')
            ->expectsOutput('Setting up multi-tenancy support...')
            ->expectsOutput('Generated tenancy migrations.')
            ->expectsOutput('Tenancy migrations created successfully!')
            ->assertSuccessful();
    });

    it('sets up tenancy with custom column name', function () {
        $this->artisan('hmac:setup-tenancy', ['--column' => 'organization_id'])
            ->expectsOutputToContain('HMAC_TENANT_COLUMN=organization_id')
            ->assertSuccessful();
    });

    it('generates data migration when from column is specified', function () {
        $this->artisan('hmac:setup-tenancy', [
            '--column' => 'tenant_id',
            '--from' => 'company_id',
        ])
            ->expectsOutputToContain("Data migration from 'company_id' to 'tenant_id' is included")
            ->assertSuccessful();
    });

    it('shows next steps for configuration', function () {
        $this->artisan('hmac:setup-tenancy')
            ->expectsOutputToContain('Review the generated migrations')
            ->expectsOutputToContain('HMAC_TENANCY_ENABLED=true')
            ->assertSuccessful();
    });

    it('uses force option to overwrite existing migrations', function () {
        // First setup
        $this->artisan('hmac:setup-tenancy')->assertSuccessful();

        // Second setup with force
        $this->artisan('hmac:setup-tenancy', ['--force' => true])
            ->expectsOutput('Generated tenancy migrations.')
            ->assertSuccessful();
    });

    it('warns when migration already exists without force', function () {
        // First setup
        $this->artisan('hmac:setup-tenancy')->assertSuccessful();

        // Second setup without force - should warn about existing files
        $this->artisan('hmac:setup-tenancy')
            ->expectsOutputToContain('Migration already exists')
            ->assertSuccessful();
    });

    it('skips data migration when from column equals new column', function () {
        $this->artisan('hmac:setup-tenancy', [
            '--column' => 'tenant_id',
            '--from' => 'tenant_id',
        ])
            ->expectsOutput('Generated tenancy migrations.')
            ->assertSuccessful();
    });
});
