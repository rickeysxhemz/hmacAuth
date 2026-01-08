<?php

declare(strict_types=1);

namespace HmacAuth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Command to install the HMAC Auth package.
 */
class InstallCommand extends Command
{
    protected $signature = 'hmac:install
                            {--with-tenancy : Enable multi-tenancy support}
                            {--tenant-column=tenant_id : Column name for tenant foreign key}
                            {--force : Overwrite existing files}';

    protected $description = 'Install the HMAC Auth package (publish config and migrations)';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Installing HMAC Auth package...');

        $withTenancy = (bool) $this->option('with-tenancy');
        /** @var string $tenantColumn */
        $tenantColumn = $this->option('tenant-column');
        $force = (bool) $this->option('force');

        // Publish config
        $this->call('vendor:publish', [
            '--provider' => 'HmacAuth\\HmacAuthServiceProvider',
            '--tag' => 'hmac-config',
            '--force' => $force,
        ]);

        // Generate migrations from stubs
        $this->generateMigrations($withTenancy, $tenantColumn, $force);

        $this->info('');
        $this->info('HMAC Auth package installed successfully!');
        $this->info('');

        if ($withTenancy) {
            $this->info('Multi-tenancy support enabled with column: '.$tenantColumn);
            $this->info('');
            $this->warn('Add the following to your .env file:');
            $this->line('  HMAC_TENANCY_ENABLED=true');
            $this->line("  HMAC_TENANT_COLUMN={$tenantColumn}");
            $this->line('  HMAC_TENANT_MODEL=App\\Models\\YourTenantModel');
            $this->info('');
        }

        $this->info('Next steps:');
        $this->line('  1. Run <comment>php artisan migrate</comment> to create the database tables');
        $this->line('  2. Configure <comment>config/hmac.php</comment> as needed');
        $this->line('  3. Add the <comment>hmac.verify</comment> middleware to your API routes');

        if ($withTenancy) {
            $this->line('  4. Generate API credentials with <comment>php artisan hmac:generate --tenant=YOUR_TENANT_ID</comment>');
        } else {
            $this->line('  4. Generate API credentials with <comment>php artisan hmac:generate</comment>');
        }

        $this->info('');

        return self::SUCCESS;
    }

    private function generateMigrations(bool $withTenancy, string $tenantColumn, bool $force): void
    {
        $stubPath = __DIR__.'/../../../database/migrations/stubs';
        $migrationPath = database_path('migrations');

        // Ensure migration directory exists
        if (! $this->files->isDirectory($migrationPath)) {
            $this->files->makeDirectory($migrationPath, 0755, true);
        }

        $timestamp = date('Y_m_d_His');

        // Base migrations
        $this->publishStub(
            $stubPath.'/create_api_credentials_table.php.stub',
            $migrationPath."/{$timestamp}_create_api_credentials_table.php",
            [],
            $force
        );

        // Increment timestamp for the second migration
        $timestamp = date('Y_m_d_His', strtotime('+1 second'));

        $this->publishStub(
            $stubPath.'/create_api_request_logs_table.php.stub',
            $migrationPath."/{$timestamp}_create_api_request_logs_table.php",
            [],
            $force
        );

        // Tenancy migrations (only if enabled)
        if ($withTenancy) {
            $timestamp = date('Y_m_d_His', strtotime('+2 seconds'));

            $this->publishStub(
                $stubPath.'/add_tenancy_to_api_credentials.php.stub',
                $migrationPath."/{$timestamp}_add_tenancy_to_api_credentials.php",
                ['{{TENANT_COLUMN}}' => $tenantColumn],
                $force
            );

            $timestamp = date('Y_m_d_His', strtotime('+3 seconds'));

            $this->publishStub(
                $stubPath.'/add_tenancy_to_api_request_logs.php.stub',
                $migrationPath."/{$timestamp}_add_tenancy_to_api_request_logs.php",
                ['{{TENANT_COLUMN}}' => $tenantColumn],
                $force
            );

            $this->info('Published tenancy migrations.');
        }

        $this->info('Published base migrations.');
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function publishStub(string $stubPath, string $targetPath, array $replacements, bool $force): void
    {
        if (! $force && $this->files->exists($targetPath)) {
            $this->warn("Migration already exists: {$targetPath}");

            return;
        }

        if (! $this->files->exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");

            return;
        }

        $content = $this->files->get($stubPath);

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        $this->files->put($targetPath, $content);
    }
}
