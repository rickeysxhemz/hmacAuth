<?php

declare(strict_types=1);

namespace HmacAuth\Console\Commands;

use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\ApiCredentialService;
use Illuminate\Console\Command;

/**
 * Command to generate new API credentials.
 */
class GenerateCredentialsCommand extends Command
{
    protected $signature = 'hmac:generate
                            {--tenant= : Tenant ID (required when tenancy is enabled)}
                            {--environment=testing : Environment (production or testing)}
                            {--expires= : Days until expiration (optional)}
                            {--user=1 : User ID who creates this credential}';

    protected $description = 'Generate new HMAC API credentials';

    public function __construct(
        private readonly ApiCredentialService $credentialService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenancyEnabled = (bool) config('hmac.tenancy.enabled', false);
        $tenantId = null;

        if ($tenancyEnabled) {
            $tenantId = $this->option('tenant') ?: $this->ask('Tenant ID');

            if (! is_numeric($tenantId)) {
                $this->error('Tenant ID must be a number');

                return self::FAILURE;
            }

            $tenantId = (int) $tenantId;
        }

        /** @var string $environment */
        $environment = $this->option('environment');
        $expiresInDays = $this->option('expires');
        $userId = $this->option('user');

        if (! in_array($environment, ApiCredential::VALID_ENVIRONMENTS, true)) {
            $this->error('Environment must be "production" or "testing"');

            return self::FAILURE;
        }

        $expiresAt = null;
        if ($expiresInDays !== null && is_numeric($expiresInDays)) {
            $expiresAt = now()->addDays((int) $expiresInDays);
        }

        $this->info('Generating API credentials...');

        $result = $this->credentialService->generate(
            createdBy: (int) $userId,
            environment: $environment,
            expiresAt: $expiresAt,
            tenantId: $tenantId,
        );

        $this->newLine();
        $this->info('API credentials generated successfully!');
        $this->newLine();

        $tableData = [
            ['Client ID', $result['credential']->client_id],
            ['Client Secret', $result['plain_secret']],
            ['Environment', $environment],
            ['Expires At', $expiresAt?->toDateTimeString() ?? 'Never'],
        ];

        if ($tenancyEnabled) {
            $tenantColumn = (string) config('hmac.tenancy.column', 'tenant_id');
            array_splice($tableData, 1, 0, [[$tenantColumn, (string) $tenantId]]);
        }

        $this->table(['Field', 'Value'], $tableData);

        $this->newLine();
        $this->warn('IMPORTANT: Store the Client Secret securely. It cannot be retrieved later.');
        $this->newLine();

        return self::SUCCESS;
    }
}
