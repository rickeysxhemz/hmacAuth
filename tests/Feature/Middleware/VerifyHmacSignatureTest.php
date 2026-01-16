<?php

declare(strict_types=1);

use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Exceptions\HmacAuthenticationException;
use HmacAuth\Http\Middleware\VerifyHmacSignature;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;

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
        cacheStore: null,
        cachePrefix: 'hmac:nonce:',
        nonceTtl: 600,
        maxBodySize: 1048576,
        minNonceLength: 32,
        tenancyEnabled: $overrides['tenancyEnabled'] ?? false,
        tenancyColumn: $overrides['tenancyColumn'] ?? 'tenant_id',
        tenancyModel: 'App\\Models\\Tenant',
    );
}

describe('VerifyHmacSignature Middleware', function () {
    beforeEach(function () {
        $this->verifier = Mockery::mock(HmacVerifierInterface::class);
    });

    describe('when HMAC is disabled', function () {
        it('passes request through without verification', function () {
            $config = createMiddlewareConfig(['enabled' => false]);
            $middleware = new VerifyHmacSignature($this->verifier, $config);

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
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $this->verifier->shouldNotReceive('verify');

            $request = Request::create('/api/test', 'GET');

            $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });
        });
    });

    describe('when verification succeeds', function () {
        it('allows request to proceed', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verifier->shouldReceive('verify')
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
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verifier->shouldReceive('verify')
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
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();
            $credential->shouldReceive('getAttribute')->with('tenant_id')->andReturn(456);
            $credential->shouldReceive('offsetExists')->with('tenant_id')->andReturn(true);

            $result = VerificationResult::success($credential);

            $this->verifier->shouldReceive('verify')
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
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $credential = Mockery::mock(ApiCredential::class)->makePartial();

            $result = VerificationResult::success($credential);

            $this->verifier->shouldReceive('verify')
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
    });

    describe('when verification fails', function () {
        it('throws HmacAuthenticationException for invalid signature', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            $this->verifier->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $request->headers->set('X-Api-Key', 'test-client');

            $middleware->handle($request, function () {
                return response()->json(['success' => true]);
            });
        })->throws(HmacAuthenticationException::class);

        it('exception has correct status code for invalid signature', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            $this->verifier->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');

            try {
                $middleware->handle($request, fn () => response()->json(['success' => true]));
            } catch (HmacAuthenticationException $e) {
                expect($e->getStatusCode())->toBe(401);

                return;
            }

            $this->fail('Expected HmacAuthenticationException was not thrown');
        });

        it('exception has correct status code for rate limited', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $result = VerificationResult::failure(VerificationFailureReason::RATE_LIMITED);

            $this->verifier->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');

            try {
                $middleware->handle($request, fn () => response()->json(['success' => true]));
            } catch (HmacAuthenticationException $e) {
                expect($e->getStatusCode())->toBe(429);

                return;
            }

            $this->fail('Expected HmacAuthenticationException was not thrown');
        });

        it('exception has correct status code for body too large', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $result = VerificationResult::failure(VerificationFailureReason::BODY_TOO_LARGE);

            $this->verifier->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'POST');

            try {
                $middleware->handle($request, fn () => response()->json(['success' => true]));
            } catch (HmacAuthenticationException $e) {
                expect($e->getStatusCode())->toBe(413);

                return;
            }

            $this->fail('Expected HmacAuthenticationException was not thrown');
        });

        it('exception renders correct JSON response', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $result = VerificationResult::failure(VerificationFailureReason::MISSING_HEADERS);

            $this->verifier->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');

            try {
                $middleware->handle($request, fn () => response()->json(['success' => true]));
            } catch (HmacAuthenticationException $e) {
                $response = $e->render();
                $body = json_decode($response->getContent(), true);

                expect($body)->toBeArray()
                    ->and($body['success'])->toBeFalse()
                    ->and($body['message'])->toBe('Missing required headers')
                    ->and($body['code'])->toBe('NOT_AUTHORIZED')
                    ->and($body['data'])->toBeNull();

                return;
            }

            $this->fail('Expected HmacAuthenticationException was not thrown');
        });

        it('does not call next middleware on failure', function () {
            $config = createMiddlewareConfig();
            $middleware = new VerifyHmacSignature($this->verifier, $config);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            $this->verifier->shouldReceive('verify')
                ->once()
                ->andReturn($result);

            $request = Request::create('/api/test', 'GET');
            $called = false;

            try {
                $middleware->handle($request, function () use (&$called) {
                    $called = true;

                    return response()->json(['success' => true]);
                });
            } catch (HmacAuthenticationException) {
                // Expected
            }

            expect($called)->toBeFalse();
        });
    });
});

afterEach(function () {
    Mockery::close();
});
