<?php

declare(strict_types=1);

namespace HmacAuth\Events;

use HmacAuth\Models\ApiCredential;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when HMAC authentication succeeds.
 */
final readonly class AuthenticationSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Request $request,
        public ApiCredential $credential,
        public string $ipAddress,
    ) {}
}
