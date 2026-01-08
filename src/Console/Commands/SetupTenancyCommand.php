<?php

declare(strict_types=1);

namespace HmacAuth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Command to add multi-tenancy support to an existing HMAC Auth installation.
 */
class SetupTenancyCommand extends Command
{
    protected $signature = 'hmac:setup-tenancy
                            {--column=tenant_id : New tenant column name}
                            {--from= : Existing column to migrate data from (optional, e.g., company_id)}
                            {--force : Overwrite existing migration files}';

    protected $description = 'Add multi-tenancy support to existing HMAC Auth installation';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $newColumn */
        $newColumn = $this->option('column');
        /** @var string|null $fromColumn */
        $fromColumn = $this->option('from');
        $force = (bool) $this->option('force');

        $this->info('Setting up multi-tenancy support...');
        $this->newLine();

        // Generate tenancy migrations
        $this->generateTenancyMigrations($newColumn, $fromColumn, $force);

        $this->newLine();
        $this->info('Tenancy migrations created successfully!');
        $this->newLine();

        $this->warn('Next steps:');
        $this->line('  1. Review the generated migrations in <comment>database/migrations</comment>');
        $this->line('  2. Run <comment>php artisan migrate</comment> to apply the changes');
        $this->line('  3. Update your <comment>.env</comment> file with:');
        $this->newLine();
        $this->line('     HMAC_TENANCY_ENABLED=true');
        $this->line("     HMAC_TENANT_COLUMN={$newColumn}");
        $this->line('     HMAC_TENANT_MODEL=App\\Models\\YourTenantModel');
        $this->newLine();

        if ($fromColumn !== null) {
            $this->warn("Note: Data migration from '{$fromColumn}' to '{$newColumn}' is included.");
            $this->line('The old column will be kept for safety. Remove it manually after verifying the migration.');
        }

        return self::SUCCESS;
    }

    private function generateTenancyMigrations(string $newColumn, ?string $fromColumn, bool $force): void
    {
        $stubPath = __DIR__.'/../../../database/migrations/stubs';
        $migrationPath = database_path('migrations');

        // Ensure migration directory exists
        if (! $this->files->isDirectory($migrationPath)) {
            $this->files->makeDirectory($migrationPath, 0755, true);
        }

        $timestamp = date('Y_m_d_His');

        if ($fromColumn !== null && $fromColumn !== $newColumn) {
            // Generate migration with data migration from old column
            $this->generateDataMigration(
                $migrationPath."/{$timestamp}_add_tenancy_to_api_credentials.php",
                'api_credentials',
                $newColumn,
                $fromColumn,
                $force
            );

            $timestamp = date('Y_m_d_His', strtotime('+1 second'));

            $this->generateDataMigration(
                $migrationPath."/{$timestamp}_add_tenancy_to_api_request_logs.php",
                'api_request_logs',
                $newColumn,
                $fromColumn,
                $force
            );
        } else {
            // Generate standard tenancy migrations from stubs
            $this->publishStub(
                $stubPath.'/add_tenancy_to_api_credentials.php.stub',
                $migrationPath."/{$timestamp}_add_tenancy_to_api_credentials.php",
                ['{{TENANT_COLUMN}}' => $newColumn],
                $force
            );

            $timestamp = date('Y_m_d_His', strtotime('+1 second'));

            $this->publishStub(
                $stubPath.'/add_tenancy_to_api_request_logs.php.stub',
                $migrationPath."/{$timestamp}_add_tenancy_to_api_request_logs.php",
                ['{{TENANT_COLUMN}}' => $newColumn],
                $force
            );
        }

        $this->info('Generated tenancy migrations.');
    }

    private function generateDataMigration(
        string $targetPath,
        string $tableName,
        string $newColumn,
        string $fromColumn,
        bool $force
    ): void {
        if (! $force && $this->files->exists($targetPath)) {
            $this->warn("Migration already exists: {$targetPath}");

            return;
        }

        $indexName = $tableName === 'api_credentials'
            ? "{$tableName}_{$newColumn}_is_active_index"
            : "{$tableName}_{$newColumn}_created_at_index";

        $indexColumns = $tableName === 'api_credentials'
            ? "['{$newColumn}', 'is_active']"
            : "['{$newColumn}', 'created_at']";

        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->unsignedBigInteger('{$newColumn}')->nullable()->after('id');
            \$table->index({$indexColumns});
        });

        // Migrate data from old column to new column
        DB::table('{$tableName}')
            ->whereNotNull('{$fromColumn}')
            ->update(['{$newColumn}' => DB::raw('{$fromColumn}')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->dropIndex({$indexColumns});
            \$table->dropColumn('{$newColumn}');
        });
    }
};

PHP;

        $this->files->put($targetPath, $content);
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
