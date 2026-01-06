<?php

declare(strict_types=1);

namespace HmacAuth\Events;

use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Models\ApiCredential;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when HMAC authentication fails.
 */
final readonly class AuthenticationFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Request $request,
        public string $clientId,
        public VerificationFailureReason $reason,
        public string $ipAddress,
        public ?ApiCredential $credential = null,
    ) {}
}
