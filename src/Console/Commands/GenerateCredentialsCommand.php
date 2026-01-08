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
                            {--company= : Company ID for the credential}
                            {--environment=testing : Environment (production or testing)}
                            {--expires= : Days until expiration (optional)}
                            {--user= : User ID who creates this credential}';

    protected $description = 'Generate new HMAC API credentials';

    public function __construct(
        private readonly ApiCredentialService $credentialService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyId = $this->option('company') ?: $this->ask('Company ID');
        $environment = $this->option('environment');
        $expiresInDays = $this->option('expires');
        $userId = $this->option('user') ?: 1;

        if (! is_numeric($companyId)) {
            $this->error('Company ID must be a number');

            return self::FAILURE;
        }

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
            companyId: (int) $companyId,
            createdBy: (int) $userId,
            environment: $environment,
            expiresAt: $expiresAt,
        );

        $this->newLine();
        $this->info('API credentials generated successfully!');
        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Client ID', $result['credential']->client_id],
                ['Client Secret', $result['plain_secret']],
                ['Environment', $environment],
                ['Expires At', $expiresAt?->toDateTimeString() ?? 'Never'],
            ]
        );

        $this->newLine();
        $this->warn('IMPORTANT: Store the Client Secret securely. It cannot be retrieved later.');
        $this->newLine();

        return self::SUCCESS;
    }
}
