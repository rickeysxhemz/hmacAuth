<?php

declare(strict_types=1);

namespace HmacAuth\Facades;

use Carbon\CarbonInterface;
use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\HmacManager;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static VerificationResult verify(Request $request)
 * @method static string generateSignature(SignaturePayload $payload, string $secret, string $algorithm = 'sha256')
 * @method static bool verifySignature(string $expected, string $actual)
 * @method static array{credential: ApiCredential, plain_secret: string} generateCredentials(int $createdBy, string $environment = 'testing', ?CarbonInterface $expiresAt = null, int|string|null $tenantId = null)
 * @method static array{credential: ApiCredential, new_secret: string, old_secret_expires_at: string} rotateSecret(ApiCredential $credential, int $graceDays = 7)
 * @method static string generateClientId(string $environment)
 * @method static string generateClientSecret()
 * @method static string generateNonce()
 *
 * @see HmacManager
 */
final class Hmac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hmac';
    }
}
