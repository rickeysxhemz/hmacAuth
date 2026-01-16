<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HMAC Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for HMAC authentication error
    | messages and notifications.
    |
    */

    'errors' => [
        'missing_headers' => 'Missing required headers',
        'invalid_timestamp' => 'Invalid or expired timestamp',
        'body_too_large' => 'Request body exceeds maximum size',
        'ip_blocked' => 'Too many failed attempts from this IP',
        'rate_limited' => 'Rate limit exceeded',
        'invalid_nonce' => 'Nonce too short',
        'duplicate_nonce' => 'Duplicate nonce detected',
        'invalid_client_id' => 'Invalid client ID',
        'credential_expired' => 'API credential has expired',
        'environment_mismatch' => 'Credential environment mismatch',
        'invalid_secret' => 'Invalid client secret',
        'invalid_signature' => 'Invalid signature',
    ],

    'response' => [
        'unauthorized' => 'Unauthorized',
        'forbidden' => 'Forbidden',
        'not_authorized_code' => 'NOT_AUTHORIZED',
    ],

    'commands' => [
        'install' => [
            'installing' => 'Installing HMAC Auth package...',
            'success' => 'HMAC Auth package installed successfully!',
            'next_steps' => 'Next steps:',
            'step_migrate' => 'Run php artisan migrate to create the database tables',
            'step_config' => 'Configure config/hmac.php as needed',
            'step_middleware' => 'Add the hmac.verify middleware to your API routes',
            'step_generate' => 'Generate API credentials with php artisan hmac:generate',
        ],
        'generate' => [
            'generating' => 'Generating API credentials...',
            'success' => 'API credentials generated successfully!',
            'secret_warning' => 'IMPORTANT: Store the Client Secret securely. It cannot be retrieved later.',
            'invalid_tenant' => 'Tenant ID must be a number',
            'invalid_environment' => 'Environment must be "production" or "testing"',
        ],
        'rotate' => [
            'rotating' => 'Rotating secret for credential: :client_id',
            'confirm' => 'Are you sure you want to rotate this secret?',
            'success' => 'Secret rotated successfully!',
            'cancelled' => 'Operation cancelled.',
            'not_found' => 'Credential not found: :identifier',
            'inactive' => 'Cannot rotate secret for inactive credential',
            'update_warning' => 'IMPORTANT: Update your application with the new secret.',
            'expiry_warning' => 'The old secret will remain valid until :expiry',
        ],
        'cleanup' => [
            'cleaning' => 'Cleaning up logs older than :date...',
            'dry_run' => '[DRY RUN] Would delete :count log entries',
            'deleted' => 'Deleted :count log entries',
            'invalid_days' => 'Days must be at least 1',
        ],
    ],

    'events' => [
        'authentication_succeeded' => 'HMAC authentication succeeded for client :client_id',
        'authentication_failed' => 'HMAC authentication failed for client :client_id: :reason',
    ],

    'validation' => [
        'client_id_required' => 'Client ID is required',
        'signature_required' => 'Signature is required',
        'timestamp_required' => 'Timestamp is required',
        'nonce_required' => 'Nonce is required',
    ],
];
