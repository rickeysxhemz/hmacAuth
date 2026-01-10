<?php

declare(strict_types=1);

use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Events\AuthenticationFailed;
use HmacAuth\Events\AuthenticationSucceeded;
use HmacAuth\Http\Middleware\VerifyHmacSignature;
use HmacAuth\Models\ApiCredential;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

function createMiddlewareConfig(array $overrides = []): HmacConfig
{
    return new HmacConfig(
        enabled: $overrides['enabled'] ?? true,
        apiKeyHeader: $overrides['apiKeyHeader'] ?? 'X-Api-Key',
        signatureHeader: 'X-Signature',
        timestampHeader: 'X-Timestamp',
        nonceHeader: 'X-Nonce',
        timestampTolerance: 300,
        rateLimitEnabled: true,
        rateLimitMaxAttempts: 60,
        rateLimitDecayMinutes: 1,
        enforceEnvironment: false,
        appEnvironment: 'testing',
        algorithm: 'sha256',
        clientIdLength: 16,
        secretLength: 48,
        redisPrefix: 'hmac:',
        nonceTtl: 600,
        maxBodySize: 1048576,
        minNonceLength: 32,
        tenancyEnabled: $overrides['tenancyEnabled'] ?? false,
        tenancyColumn: $overrides['tenancyColumn'] ?? 'tenant_id',
        tenancyModel: 'App\\Models\\Tenant',
        databaseRedisPrefix: '',
    );
}

describe('VerifyHmacSignature Middleware', function () {
    beforeEach(function () {
        Event::fake();
        $this->verificationService = Mockery::mock(HmacVerifierInterface::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->dispatcher->shouldReceive('dispatch')->andReturn(null);
    });

    describe('when HMAC is disabled', function () {
        it('passes request through without verification', function () {
            $config = createMiddlewareConfig(['enabled' => false]);
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $request = Request::create('/api/test', 'GET');
            $called = false;

            $response = $middleware->handle($request, function ($req) use (&$called) {
                $called = true;

                return response()->json(['success' => true]);
            });

            expect($called)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        it('does not call verification service', function () {
            $config = createMiddlewareConfig(['enabled' => false]);
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $this->verificationService->shouldNotReceive('verify');

            $request = Request::create('/api/test', 'GET');

            $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });
        });
    });

    describe('when verification succeeds', function () {
        it('allows request to proceed', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $request->headers->set('X-Api-Key', 'test-client');
            $called = false;

            $response = $middleware->handle($request, function ($req) use (&$called) {
                $called = true;

                return response()->json(['success' => true]);
            });

            expect($called)->toBeTrue()
                ->and($response->getStatusCode())->toBe(200);
        });

        it('sets hmac_credential attribute on request', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $capturedCredential = null;

            $middleware->handle($request, function ($req) use (&$capturedCredential) {
                $capturedCredential = $req->attributes->get('hmac_credential');

                return response()->json(['success' => true]);
            });

            expect($capturedCredential)->toBe($credential);
        });

        it('sets tenant_id attribute on request when tenancy enabled', function () {
            $config = createMiddlewareConfig(['tenancyEnabled' => true, 'tenancyColumn' => 'tenant_id']);
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->shouldReceive('getAttribute')->with('tenant_id')->andReturn(456);
            $credential->shouldReceive('offsetExists')->with('tenant_id')->andReturn(true);

            $result = VerificationResult::success($credential);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $capturedTenantId = null;

            $middleware->handle($request, function ($req) use (&$capturedTenantId) {
                $capturedTenantId = $req->attributes->get('tenant_id');

                return response()->json(['success' => true]);
            });

            expect($capturedTenantId)->toBe(456);
        });

        it('does not set tenant_id when tenancy disabled', function () {
            $config = createMiddlewareConfig(['tenancyEnabled' => false]);
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $capturedTenantId = 'not-set';

            $middleware->handle($request, function ($req) use (&$capturedTenantId) {
                $capturedTenantId = $req->attributes->get('tenant_id', 'not-set');

                return response()->json(['success' => true]);
            });

            expect($capturedTenantId)->toBe('not-set');
        });

        it('dispatches AuthenticationSucceeded event', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);
            $dispatcher->shouldReceive('dispatch')
                ->once()
                ->with(Mockery::type(AuthenticationSucceeded::class));

            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $dispatcher);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');

            $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });
        });
    });

    describe('when verification fails', function () {
        it('returns 401 for invalid signature', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $request->headers->set('X-Api-Key', 'test-client');

            $response = $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });

            expect($response->getStatusCode())->toBe(401);
        });

        it('returns 429 for rate limited', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $result = VerificationResult::failure(VerificationFailureReason::RATE_LIMITED);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $request->headers->set('X-Api-Key', 'test-client');

            $response = $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });

            expect($response->getStatusCode())->toBe(429);
        });

        it('returns 413 for body too large', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $result = VerificationResult::failure(VerificationFailureReason::BODY_TOO_LARGE);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'POST');

            $response = $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });

            expect($response->getStatusCode())->toBe(413);
        });

        it('returns JSON error response', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $result = VerificationResult::failure(VerificationFailureReason::MISSING_HEADERS);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');

            $response = $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });

            $body = json_decode($response->getContent(), true);

            expect($body)->toBeArray()
                ->and($body['success'])->toBeFalse()
                ->and($body['message'])->toBe('Missing required headers')
                ->and($body['code'])->toBe('NOT_AUTHORIZED')
                ->and($body['data'])->toBeNull();
        });

        it('dispatches AuthenticationFailed event', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);
            $dispatcher->shouldReceive('dispatch')
                ->once()
                ->with(Mockery::type(AuthenticationFailed::class));

            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $dispatcher);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_CLIENT_ID);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $request->headers->set('X-Api-Key', 'unknown-client');

            $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });
        });

        it('does not call next middleware on failure', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verificationService, $config, $this->dispatcher);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            $this->verificationService->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $request->headers->set('X-Api-Key', 'test-client');
            $called = false;

            $middleware->handle($request, function () use (&$called) {
                $called = true;

                return response()->json(['success' => true]);
            });

            expect($called)->toBeFalse();
        });
    });
});

afterEach(function () {
    Mockery::close();
});
