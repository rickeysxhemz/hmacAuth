<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = app(Filesystem::class);
    $this->migrationPath = database_path('migrations');

    // Clean up any existing test migrations
    cleanupInstallMigrations();
});

afterEach(function () {
    cleanupInstallMigrations();
});

function cleanupInstallMigrations(): void
{
    $filesystem = app(Filesystem::class);
    $migrationPath = database_path('migrations');

    if ($filesystem->isDirectory($migrationPath)) {
        foreach ($filesystem->files($migrationPath) as $file) {
            $filename = $file->getFilename();
            if (str_contains($filename, 'api_credentials') || str_contains($filename, 'api_request_logs')) {
                $filesystem->delete($file->getPathname());
            }
        }
    }
}

describe('InstallCommand', function () {
    it('installs the package with default options', function () {
        $this->artisan('hmac:install')
            ->expectsOutput('Installing HMAC Auth package...')
            ->expectsOutput('Published base migrations.')
            ->expectsOutput('HMAC Auth package installed successfully!')
            ->assertSuccessful();
    });

    it('installs with force option to overwrite existing files', function () {
        // First install
        $this->artisan('hmac:install')->assertSuccessful();

        // Second install with force
        $this->artisan('hmac:install', ['--force' => true])
            ->expectsOutput('Installing HMAC Auth package...')
            ->expectsOutput('Published base migrations.')
            ->assertSuccessful();
    });

    it('installs with tenancy support', function () {
        $this->artisan('hmac:install', ['--with-tenancy' => true])
            ->expectsOutput('Installing HMAC Auth package...')
            ->expectsOutput('Published tenancy migrations.')
            ->expectsOutput('Published base migrations.')
            ->expectsOutput('HMAC Auth package installed successfully!')
            ->expectsOutputToContain('Multi-tenancy support enabled with column: tenant_id')
            ->assertSuccessful();
    });

    it('installs with custom tenant column', function () {
        $this->artisan('hmac:install', ['--with-tenancy' => true, '--tenant-column' => 'organization_id'])
            ->expectsOutputToContain('Multi-tenancy support enabled with column: organization_id')
            ->assertSuccessful();
    });

    it('shows next steps', function () {
        $this->artisan('hmac:install')
            ->expectsOutputToContain('Next steps')
            ->assertSuccessful();
    });

    it('shows tenancy-specific next steps when tenancy is enabled', function () {
        $this->artisan('hmac:install', ['--with-tenancy' => true])
            ->expectsOutputToContain('--tenant=YOUR_TENANT_ID')
            ->assertSuccessful();
    });

    it('warns when migration already exists without force', function () {
        // First install
        $this->artisan('hmac:install')->assertSuccessful();

        // Second install without force - should warn about existing files
        $this->artisan('hmac:install')
            ->expectsOutputToContain('Migration already exists')
            ->assertSuccessful();
    });
});
