<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Concerns\EncodesBase64Url;
use HmacAuth\Contracts\SignatureServiceInterface;
use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\Enums\HmacAlgorithm;

/**
 * HMAC signature generation and verification.
 */
final readonly class SignatureService implements SignatureServiceInterface
{
    use EncodesBase64Url;

    public function __invoke(SignaturePayload $payload, string $secret, string $algorithm = 'sha256'): string
    {
        return $this->generate($payload, $secret, $algorithm);
    }

    public function generate(SignaturePayload $payload, string $secret, string $algorithm = 'sha256'): string
    {
        $hmac = hash_hmac(
            $this->validateAlgorithm($algorithm),
            $payload->toCanonicalString(),
            $secret,
            true
        );

        return $this->base64UrlEncode($hmac);
    }

    public function verify(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }

    public function isAlgorithmSupported(string $algorithm): bool
    {
        return HmacAlgorithm::tryFromString($algorithm) instanceof HmacAlgorithm;
    }

    /**
     * @return list<string>
     */
    public function getSupportedAlgorithms(): array
    {
        return HmacAlgorithm::supportedNames();
    }

    private function validateAlgorithm(string $algorithm): string
    {
        return HmacAlgorithm::tryFromString($algorithm)?->value ?? HmacAlgorithm::default()->value;
    }
}
