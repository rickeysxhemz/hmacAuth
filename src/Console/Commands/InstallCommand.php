<?php

declare(strict_types=1);

namespace HmacAuth\Console\Commands;

use Illuminate\Console\Command;

/**
 * Command to install the HMAC Auth package.
 */
class InstallCommand extends Command
{
    protected $signature = 'hmac:install
                            {--force : Overwrite existing files}';

    protected $description = 'Install the HMAC Auth package (publish config and migrations)';

    public function handle(): int
    {
        $this->info('Installing HMAC Auth package...');

        $this->call('vendor:publish', [
            '--provider' => 'HmacAuth\\HmacAuthServiceProvider',
            '--tag' => 'hmac-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--provider' => 'HmacAuth\\HmacAuthServiceProvider',
            '--tag' => 'hmac-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->info('');
        $this->info('HMAC Auth package installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->line('  1. Run <comment>php artisan migrate</comment> to create the database tables');
        $this->line('  2. Configure <comment>config/hmac.php</comment> as needed');
        $this->line('  3. Add the <comment>hmac.verify</comment> middleware to your API routes');
        $this->line('  4. Generate API credentials with <comment>php artisan hmac:generate</comment>');
        $this->info('');

        return self::SUCCESS;
    }
}
