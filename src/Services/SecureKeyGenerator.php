<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Concerns\EncodesBase64Url;
use HmacAuth\Contracts\KeyGeneratorInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Models\ApiCredential;
use Random\RandomException;

/**
 * Cryptographically secure key generation.
 */
final readonly class SecureKeyGenerator implements KeyGeneratorInterface
{
    use EncodesBase64Url;

    public function __construct(
        private HmacConfig $config,
    ) {}

    /**
     * @throws RandomException
     */
    public function generateClientId(string $environment): string
    {
        /** @var string $keyPrefix */
        $keyPrefix = config('hmac.key_prefix', 'hmac');

        $prefix = $environment === ApiCredential::ENVIRONMENT_PRODUCTION ? 'live' : 'test';
        $length = max(1, $this->config->clientIdLength);

        return sprintf('%s_%s_%s', $keyPrefix, $prefix, bin2hex(random_bytes($length)));
    }

    /**
     * @throws RandomException
     */
    public function generateClientSecret(): string
    {
        $length = max(1, $this->config->secretLength);

        return $this->base64UrlEncode(random_bytes($length));
    }

    /**
     * @throws RandomException
     */
    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
