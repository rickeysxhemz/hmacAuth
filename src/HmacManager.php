<?php

declare(strict_types=1);

namespace HmacAuth;

use DateTimeInterface;
use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\ApiCredentialService;
use HmacAuth\Services\HmacVerificationService;
use HmacAuth\Services\SecureKeyGenerator;
use HmacAuth\Services\SignatureService;
use Illuminate\Http\Request;

/**
 * Manager class for the Hmac facade.
 */
final readonly class HmacManager
{
    public function __construct(
        private HmacVerificationService $verificationService,
        private SignatureService $signatureService,
        private ApiCredentialService $credentialService,
        private SecureKeyGenerator $keyGenerator,
    ) {}

    /**
     * Verify an incoming request.
     */
    public function verify(Request $request): VerificationResult
    {
        return $this->verificationService->verify($request);
    }

    /**
     * Generate an HMAC signature.
     */
    public function generateSignature(SignaturePayload $payload, string $secret, string $algorithm = 'sha256'): string
    {
        return $this->signatureService->generate($payload, $secret, $algorithm);
    }

    /**
     * Verify a signature matches expected value.
     */
    public function verifySignature(string $expected, string $actual): bool
    {
        return $this->signatureService->verify($expected, $actual);
    }

    /**
     * Generate new API credentials.
     *
     * @return array{client_id: string, client_secret: string, credential: ApiCredential}
     */
    public function generateCredentials(
        int $companyId,
        int $createdBy,
        string $environment = 'testing',
        ?DateTimeInterface $expiresAt = null,
    ): array {
        return $this->credentialService->generate($companyId, $createdBy, $environment, $expiresAt);
    }

    /**
     * Rotate the secret for a credential.
     *
     * @return array{new_secret: string, old_secret_expires_at: string}
     */
    public function rotateSecret(ApiCredential $credential, int $graceDays = 7): array
    {
        return $this->credentialService->rotateSecret($credential, $graceDays);
    }

    /**
     * Generate a new client ID.
     */
    public function generateClientId(string $environment): string
    {
        return $this->keyGenerator->generateClientId($environment);
    }

    /**
     * Generate a new client secret.
     */
    public function generateClientSecret(): string
    {
        return $this->keyGenerator->generateClientSecret();
    }

    /**
     * Generate a new nonce.
     */
    public function generateNonce(): string
    {
        return $this->keyGenerator->generateNonce();
    }
}
