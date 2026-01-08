<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Concerns\ValidatesHmacHeaders;
use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\Contracts\NonceStoreInterface;
use HmacAuth\Contracts\RateLimiterInterface;
use HmacAuth\Contracts\RequestLoggerInterface;
use HmacAuth\Contracts\SignatureServiceInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Enums\HmacAlgorithm;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;

/**
 * HMAC signature verification service.
 */
final readonly class HmacVerificationService implements HmacVerifierInterface
{
    use ValidatesHmacHeaders;

    public function __construct(
        private ApiCredentialRepositoryInterface $credentialRepository,
        private NonceStoreInterface $nonceStore,
        private RequestLoggerInterface $requestLogger,
        private RateLimiterInterface $rateLimiter,
        private SignatureServiceInterface $signatureService,
        private HmacConfig $config,
    ) {}

    public function __invoke(Request $request): VerificationResult
    {
        return $this->verify($request);
    }

    public function verify(Request $request): VerificationResult
    {
        $clientId = $request->header($this->config->apiKeyHeader);
        $signature = $request->header($this->config->signatureHeader);
        $timestamp = $request->header($this->config->timestampHeader);
        $nonce = $request->header($this->config->nonceHeader);

        if (! $this->hasValidHeaders($clientId, $signature, $timestamp, $nonce)) {
            return $this->fail($request, $clientId ?? 'unknown', VerificationFailureReason::MISSING_HEADERS);
        }

        /** @var string $clientId */
        /** @var string $signature */
        /** @var string $timestamp */
        /** @var string $nonce */
        if (! $this->isTimestampValid((int) $timestamp, $this->config->timestampTolerance)) {
            return $this->fail($request, $clientId, VerificationFailureReason::INVALID_TIMESTAMP);
        }

        if (! $this->isBodySizeValid($request->getContent(), $this->config->maxBodySize)) {
            return $this->fail($request, $clientId, VerificationFailureReason::BODY_TOO_LARGE);
        }

        $ipAddress = $request->ip();
        if ($ipAddress !== null && $this->requestLogger->hasExcessiveFailures($ipAddress)) {
            return $this->fail($request, $clientId, VerificationFailureReason::IP_BLOCKED);
        }

        if ($this->rateLimiter->isLimited($clientId)) {
            return $this->fail($request, $clientId, VerificationFailureReason::RATE_LIMITED);
        }

        if (! $this->isNonceLengthValid($nonce, $this->config->minNonceLength)) {
            return $this->fail($request, $clientId, VerificationFailureReason::INVALID_NONCE);
        }

        if ($this->nonceStore->exists($nonce)) {
            return $this->fail($request, $clientId, VerificationFailureReason::DUPLICATE_NONCE);
        }

        $credential = $this->credentialRepository->findActiveByClientId($clientId);
        if (! $credential instanceof ApiCredential) {
            $this->rateLimiter->recordFailure($clientId);

            return $this->fail($request, $clientId, VerificationFailureReason::INVALID_CLIENT_ID);
        }

        if ($credential->isExpired()) {
            return $this->fail($request, $clientId, VerificationFailureReason::CREDENTIAL_EXPIRED, $credential);
        }

        if ($this->config->enforceEnvironment && ! $credential->matchesEnvironment($this->config->appEnvironment)) {
            $this->rateLimiter->recordFailure($clientId);

            return $this->fail($request, $clientId, VerificationFailureReason::ENVIRONMENT_MISMATCH, $credential);
        }

        $clientSecret = $credential->client_secret;
        if (! is_string($clientSecret) || $clientSecret === '') {
            return $this->fail($request, $clientId, VerificationFailureReason::INVALID_SECRET, $credential);
        }

        $rawAlgorithm = is_string($credential->hmac_algorithm) ? $credential->hmac_algorithm : 'sha256';
        $algorithm = HmacAlgorithm::tryFromString($rawAlgorithm)?->value ?? HmacAlgorithm::default()->value;

        if (! $this->verifySignatureWithRotation($request, $credential, $signature, $timestamp, $nonce, $algorithm)) {
            $this->rateLimiter->recordFailure($clientId);

            return $this->fail($request, $clientId, VerificationFailureReason::INVALID_SIGNATURE, $credential);
        }

        $this->nonceStore->store($nonce);
        $this->credentialRepository->markAsUsed($credential);
        $this->requestLogger->logSuccessfulAttempt($request, $credential);
        $this->rateLimiter->reset($clientId);

        return VerificationResult::success($credential);
    }

    private function verifySignatureWithRotation(
        Request $request,
        ApiCredential $credential,
        string $signature,
        string $timestamp,
        string $nonce,
        string $algorithm
    ): bool {
        $payload = SignaturePayload::fromRequest($request, $timestamp, $nonce);

        $currentSecret = $credential->client_secret;
        if (is_string($currentSecret) && $currentSecret !== '') {
            $expected = $this->signatureService->generate($payload, $currentSecret, $algorithm);
            if ($this->signatureService->verify($expected, $signature)) {
                return true;
            }
        }

        $oldSecret = $credential->old_client_secret;
        $oldSecretExpiry = $credential->old_secret_expires_at;

        if (is_string($oldSecret) && $oldSecret !== '' && $oldSecretExpiry !== null && $oldSecretExpiry->isFuture()) {
            $expected = $this->signatureService->generate($payload, $oldSecret, $algorithm);
            if ($this->signatureService->verify($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function fail(
        Request $request,
        string $clientId,
        VerificationFailureReason $reason,
        ?ApiCredential $credential = null
    ): VerificationResult {
        $this->requestLogger->logFailedAttempt($request, $clientId, $reason->value, $credential);

        return VerificationResult::failure($reason);
    }
}
