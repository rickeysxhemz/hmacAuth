<?php

declare(strict_types=1);

namespace HmacAuth\Console\Commands;

use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\ApiCredentialService;
use Illuminate\Console\Command;

/**
 * Command to rotate API credential secrets.
 */
class RotateSecretCommand extends Command
{
    protected $signature = 'hmac:rotate
                            {credential : The credential ID or client_id to rotate}
                            {--grace-days=7 : Number of days the old secret remains valid}';

    protected $description = 'Rotate the secret for an API credential';

    public function __construct(
        private readonly ApiCredentialService $credentialService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $credentialIdentifier */
        $credentialIdentifier = $this->argument('credential');
        $graceDays = (int) $this->option('grace-days');

        // Find credential by ID or client_id
        $credential = ApiCredential::where('id', $credentialIdentifier)
            ->orWhere('client_id', $credentialIdentifier)
            ->first();

        if (! $credential) {
            $this->error("Credential not found: {$credentialIdentifier}");

            return self::FAILURE;
        }

        if (! $credential->is_active) {
            $this->error('Cannot rotate secret for inactive credential');

            return self::FAILURE;
        }

        $this->info("Rotating secret for credential: {$credential->client_id}");

        if (! $this->confirm('Are you sure you want to rotate this secret?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $result = $this->credentialService->rotateSecret($credential, $graceDays);

        $this->newLine();
        $this->info('Secret rotated successfully!');
        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Client ID', $credential->client_id],
                ['New Secret', $result['new_secret']],
                ['Grace Period', "{$graceDays} days"],
                ['Old Secret Expires', $result['old_secret_expires_at']],
            ]
        );

        $this->newLine();
        $this->warn('IMPORTANT: Update your application with the new secret.');
        $this->warn("The old secret will remain valid until {$result['old_secret_expires_at']}");
        $this->newLine();

        return self::SUCCESS;
    }
}
