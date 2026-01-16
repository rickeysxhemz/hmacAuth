<?php

declare(strict_types=1);

use HmacAuth\Contracts\ApiCredentialRepositoryInterface;
use HmacAuth\Contracts\NonceStoreInterface;
use HmacAuth\Contracts\RateLimiterInterface;
use HmacAuth\Contracts\RequestLoggerInterface;
use HmacAuth\Contracts\SignatureServiceInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\HmacVerificationService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

function createVerificationConfig(array $overrides = []): HmacConfig
{
    return new HmacConfig(
        enabled: $overrides['enabled'] ?? true,
        apiKeyHeader: $overrides['apiKeyHeader'] ?? 'X-Api-Key',
        signatureHeader: $overrides['signatureHeader'] ?? 'X-Signature',
        timestampHeader: $overrides['timestampHeader'] ?? 'X-Timestamp',
        nonceHeader: $overrides['nonceHeader'] ?? 'X-Nonce',
        timestampTolerance: $overrides['timestampTolerance'] ?? 300,
        rateLimitEnabled: $overrides['rateLimitEnabled'] ?? true,
        rateLimitMaxAttempts: $overrides['rateLimitMaxAttempts'] ?? 60,
        rateLimitDecayMinutes: $overrides['rateLimitDecayMinutes'] ?? 1,
        enforceEnvironment: $overrides['enforceEnvironment'] ?? false,
        appEnvironment: $overrides['appEnvironment'] ?? 'testing',
        algorithm: $overrides['algorithm'] ?? 'sha256',
        clientIdLength: 16,
        secretLength: 48,
        cacheStore: null,
        cachePrefix: 'hmac:nonce:',
        nonceTtl: 600,
        maxBodySize: $overrides['maxBodySize'] ?? 1048576,
        minNonceLength: $overrides['minNonceLength'] ?? 32,
        negativeCacheTtl: 60,
        ipBlockingEnabled: $overrides['ipBlockingEnabled'] ?? true,
        ipBlockingThreshold: $overrides['ipBlockingThreshold'] ?? 10,
        ipBlockingWindowMinutes: $overrides['ipBlockingWindowMinutes'] ?? 10,
    );
}

function createMockRequest(array $headers = [], string $method = 'GET', string $path = '/api/test', string $body = ''): Request
{
    $request = Request::create($path, $method, [], [], [], [], $body);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

describe('HmacVerificationService', function () {
    beforeEach(function () {
        $this->credentialRepo = Mockery::mock(ApiCredentialRepositoryInterface::class);
        $this->nonceStore = Mockery::mock(NonceStoreInterface::class);
        $this->requestLogger = Mockery::mock(RequestLoggerInterface::class);
        $this->rateLimiter = Mockery::mock(RateLimiterInterface::class);
        $this->signatureService = Mockery::mock(SignatureServiceInterface::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->dispatcher->shouldReceive('dispatch')->byDefault();
        $this->config = createVerificationConfig();
    });

    function createService($test): HmacVerificationService
    {
        return new HmacVerificationService(
            $test->credentialRepo,
            $test->nonceStore,
            $test->requestLogger,
            $test->rateLimiter,
            $test->signatureService,
            $test->config,
            $test->dispatcher,
        );
    }

    describe('verify()', function () {
        it('fails for missing X-Api-Key header', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::MISSING_HEADERS);
        });

        it('fails for missing X-Signature header', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::MISSING_HEADERS);
        });

        it('fails for missing X-Timestamp header', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::MISSING_HEADERS);
        });

        it('fails for missing X-Nonce header', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::MISSING_HEADERS);
        });

        it('fails for expired timestamp', function () {
            $service = createService($this);

            // Timestamp from 10 minutes ago (beyond 5 minute tolerance)
            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) (time() - 600),
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::INVALID_TIMESTAMP);
        });

        it('fails for future timestamp beyond tolerance', function () {
            $service = createService($this);

            // Timestamp 10 minutes in the future
            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) (time() + 600),
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::INVALID_TIMESTAMP);
        });

        it('fails for body size exceeding limit', function () {
            $this->config = createVerificationConfig(['maxBodySize' => 10]);
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('a', 32),
            ], 'POST', '/api/test', str_repeat('x', 100));

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::BODY_TOO_LARGE);
        });

        it('fails for blocked IP', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')
                ->with(Mockery::any())
                ->once()
                ->andReturn(true);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::IP_BLOCKED);
        });

        it('fails for rate limited client', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('a', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')
                ->with('test-client')
                ->once()
                ->andReturn(true);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::RATE_LIMITED);
        });

        it('fails for short nonce', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => 'short', // Less than 32 characters
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::INVALID_NONCE);
        });

        it('fails for duplicate nonce', function () {
            $service = createService($this);

            $nonce = str_repeat('b', 32);
            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => $nonce,
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->nonceStore->shouldReceive('exists')->with($nonce)->once()->andReturn(true);
            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::DUPLICATE_NONCE);
        });

        it('fails for unknown client ID', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'unknown-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('c', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->nonceStore->shouldReceive('exists')->once()->andReturn(false);
            $this->credentialRepo->shouldReceive('findActiveByClientId')
                ->with('unknown-client')
                ->once()
                ->andReturn(null);
            $this->rateLimiter->shouldReceive('recordFailure')->with('unknown-client')->once();
            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::INVALID_CLIENT_ID);
        });

        it('fails for expired credential', function () {
            $service = createService($this);

            $credential = Mockery::mock(ApiCredential::class);
            $credential->shouldReceive('isExpired')->once()->andReturn(true);

            $request = createMockRequest([
                'X-Api-Key' => 'expired-client',
                'X-Signature' => 'sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('d', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->nonceStore->shouldReceive('exists')->once()->andReturn(false);
            $this->credentialRepo->shouldReceive('findActiveByClientId')
                ->with('expired-client')
                ->once()
                ->andReturn($credential);
            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::CREDENTIAL_EXPIRED);
        });

        it('fails for invalid signature', function () {
            $service = createService($this);

            $credential = Mockery::mock(ApiCredential::class);
            $credential->shouldReceive('isExpired')->once()->andReturn(false);
            $credential->shouldReceive('getAttribute')->with('client_secret')->andReturn('secret123');
            $credential->shouldReceive('getAttribute')->with('hmac_algorithm')->andReturn('sha256');
            $credential->shouldReceive('getAttribute')->with('old_client_secret')->andReturn(null);
            $credential->shouldReceive('getAttribute')->with('old_secret_expires_at')->andReturn(null);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
                'X-Signature' => 'invalid-signature',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('e', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->nonceStore->shouldReceive('exists')->once()->andReturn(false);
            $this->credentialRepo->shouldReceive('findActiveByClientId')
                ->with('test-client')
                ->once()
                ->andReturn($credential);
            $this->signatureService->shouldReceive('generate')
                ->once()
                ->andReturn('expected-signature');
            $this->signatureService->shouldReceive('verify')
                ->with('expected-signature', 'invalid-signature')
                ->once()
                ->andReturn(false);
            $this->rateLimiter->shouldReceive('recordFailure')->with('test-client')->once();
            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeFalse()
                ->and($result->failureReason)->toBe(VerificationFailureReason::INVALID_SIGNATURE);
        });

        it('succeeds for valid request', function () {
            $service = createService($this);

            $credential = Mockery::mock(ApiCredential::class);
            $credential->shouldReceive('isExpired')->once()->andReturn(false);
            $credential->shouldReceive('getAttribute')->with('client_secret')->andReturn('secret123');
            $credential->shouldReceive('getAttribute')->with('hmac_algorithm')->andReturn('sha256');

            $request = createMockRequest([
                'X-Api-Key' => 'valid-client',
                'X-Signature' => 'valid-signature',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('f', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->nonceStore->shouldReceive('exists')->once()->andReturn(false);
            $this->credentialRepo->shouldReceive('findActiveByClientId')
                ->with('valid-client')
                ->once()
                ->andReturn($credential);
            $this->signatureService->shouldReceive('generate')
                ->once()
                ->andReturn('valid-signature');
            $this->signatureService->shouldReceive('verify')
                ->with('valid-signature', 'valid-signature')
                ->once()
                ->andReturn(true);
            $this->nonceStore->shouldReceive('store')->once();
            $this->credentialRepo->shouldReceive('markAsUsed')->with($credential)->once();
            $this->requestLogger->shouldReceive('logSuccessfulAttempt')->once();
            $this->rateLimiter->shouldReceive('reset')->with('valid-client')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeTrue()
                ->and($result->credential)->toBe($credential);
        });

        it('succeeds with old secret during rotation period', function () {
            $service = createService($this);

            $futureDate = now()->addDays(3);
            $credential = Mockery::mock(ApiCredential::class);
            $credential->shouldReceive('isExpired')->once()->andReturn(false);
            $credential->shouldReceive('getAttribute')->with('client_secret')->andReturn('new-secret');
            $credential->shouldReceive('getAttribute')->with('hmac_algorithm')->andReturn('sha256');
            $credential->shouldReceive('getAttribute')->with('old_client_secret')->andReturn('old-secret');
            $credential->shouldReceive('getAttribute')->with('old_secret_expires_at')->andReturn($futureDate);

            $request = createMockRequest([
                'X-Api-Key' => 'rotating-client',
                'X-Signature' => 'old-sig',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => str_repeat('g', 32),
            ]);

            $this->requestLogger->shouldReceive('hasExcessiveFailures')->once()->andReturn(false);
            $this->rateLimiter->shouldReceive('isLimited')->once()->andReturn(false);
            $this->nonceStore->shouldReceive('exists')->once()->andReturn(false);
            $this->credentialRepo->shouldReceive('findActiveByClientId')
                ->with('rotating-client')
                ->once()
                ->andReturn($credential);

            // First try with new secret - fails
            $this->signatureService->shouldReceive('generate')
                ->andReturn('new-sig', 'old-sig');
            $this->signatureService->shouldReceive('verify')
                ->with('new-sig', 'old-sig')
                ->once()
                ->andReturn(false);
            // Second try with old secret - succeeds
            $this->signatureService->shouldReceive('verify')
                ->with('old-sig', 'old-sig')
                ->once()
                ->andReturn(true);

            $this->nonceStore->shouldReceive('store')->once();
            $this->credentialRepo->shouldReceive('markAsUsed')->with($credential)->once();
            $this->requestLogger->shouldReceive('logSuccessfulAttempt')->once();
            $this->rateLimiter->shouldReceive('reset')->with('rotating-client')->once();

            $result = $service->verify($request);

            expect($result->isValid())->toBeTrue();
        });
    });

    describe('__invoke()', function () {
        it('works as callable', function () {
            $service = createService($this);

            $request = createMockRequest([
                'X-Api-Key' => 'test-client',
            ]);

            $this->requestLogger->shouldReceive('logFailedAttempt')->once();

            $result = $service($request);

            expect($result->isValid())->toBeFalse();
        });
    });
});

afterEach(function () {
    Mockery::close();
});
