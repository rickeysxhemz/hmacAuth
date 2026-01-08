<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use HmacAuth\DTOs\VerificationResult;
use Illuminate\Http\Request;

/**
 * Interface for HMAC authentication verification.
 */
interface HmacVerifierInterface
{
    /**
     * Verify HMAC authentication for an incoming request.
     */
    public function verify(Request $request): VerificationResult;
}
