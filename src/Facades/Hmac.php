<?php

declare(strict_types=1);

namespace HmacAuth\Facades;

use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static VerificationResult verify(Request $request)
 * @method static string generateSignature(\HmacAuth\DTOs\SignaturePayload $payload, string $secret, string $algorithm = 'sha256')
 * @method static bool verifySignature(string $expected, string $actual)
 * @method static array{client_id: string, client_secret: string, credential: ApiCredential} generateCredentials(int $companyId, int $createdBy, string $environment = 'testing', ?\DateTimeInterface $expiresAt = null)
 * @method static array{new_secret: string, old_secret_expires_at: string} rotateSecret(ApiCredential $credential, int $graceDays = 7)
 * @method static string generateClientId(string $environment)
 * @method static string generateClientSecret()
 * @method static string generateNonce()
 *
 * @see \HmacAuth\HmacManager
 */
class Hmac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hmac';
    }
}
