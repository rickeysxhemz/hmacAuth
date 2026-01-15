<?php

declare(strict_types=1);

use Carbon\Carbon;
use HmacAuth\Contracts\ApiCredentialServiceInterface;
use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\Contracts\KeyGeneratorInterface;
use HmacAuth\Contracts\SignatureServiceInterface;
use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\HmacManager;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;

describe('HmacManager', function () {
    beforeEach(function () {
        $this->verificationService = Mockery::mock(HmacVerifierInterface::class);
        $this->signatureService = Mockery::mock(SignatureServiceInterface::class);
        $this->credentialService = Mockery::mock(ApiCredentialServiceInterface::class);
        $this->keyGenerator = Mockery::mock(KeyGeneratorInterface::class);

        $this->manager = new HmacManager(
            $this->verificationService,
            $this->signatureService,
            $this->credentialService,
            $this->keyGenerator,
        );
    });

    describe('verify', function () {
        it('delegates to verification service', function () {
            $request = Request::create('/test', 'GET');
            $result = VerificationResult::success(
                Mockery::mock(ApiCredential::class)
            );

            $this->verificationService->shouldReceive('verify')
                ->with($request)
                ->once()
                ->andReturn($result);

            $actual = $this->manager->verify($request);

            expect($actual)->toBe($result);
            expect($actual->isValid())->toBeTrue();
        });

        it('returns failure result when verification fails', function () {
            $request = Request::create('/test', 'GET');
            $result = VerificationResult::failure(
                VerificationFailureReason::INVALID_SIGNATURE
            );

            $this->verificationService->shouldReceive('verify')
                ->with($request)
                ->andReturn($result);

            $actual = $this->manager->verify($request);

            expect($actual->isValid())->toBeFalse();
            expect($actual->failureReason)->toBe(VerificationFailureReason::INVALID_SIGNATURE);
        });
    });

    describe('generateSignature', function () {
        it('generates signature for payload', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/api/test',
                body: '{"data":"test"}',
                timestamp: '1234567890',
                nonce: 'abc123def456'
            );

            $this->signatureService->shouldReceive('generate')
                ->with($payload, 'secret', 'sha256')
                ->andReturn('generated-signature');

            $signature = $this->manager->generateSignature($payload, 'secret');

            expect($signature)->toBe('generated-signature');
        });

        it('uses specified algorithm', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/api/data',
                body: '',
                timestamp: '1234567890',
                nonce: 'nonce123'
            );

            $this->signatureService->shouldReceive('generate')
                ->with($payload, 'secret', 'sha512')
                ->andReturn('sha512-signature');

            $signature = $this->manager->generateSignature($payload, 'secret', 'sha512');

            expect($signature)->toBe('sha512-signature');
        });
    });

    describe('verifySignature', function () {
        it('returns true for matching signatures', function () {
            $this->signatureService->shouldReceive('verify')
                ->with('signature', 'signature')
                ->andReturn(true);

            $result = $this->manager->verifySignature('signature', 'signature');

            expect($result)->toBeTrue();
        });

        it('returns false for non-matching signatures', function () {
            $this->signatureService->shouldReceive('verify')
                ->with('expected', 'actual')
                ->andReturn(false);

            $result = $this->manager->verifySignature('expected', 'actual');

            expect($result)->toBeFalse();
        });
    });

    describe('generateCredentials', function () {
        it('creates credentials through service', function () {
            $credential = Mockery::mock(ApiCredential::class);

            $this->credentialService->shouldReceive('generate')
                ->with(1, 'testing', null, null)
                ->andReturn([
                    'credential' => $credential,
                    'plain_secret' => 'secret123',
                ]);

            $result = $this->manager->generateCredentials(createdBy: 1);

            expect($result['credential'])->toBe($credential);
            expect($result['plain_secret'])->toBe('secret123');
        });

        it('passes all parameters to service', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $expiresAt = Carbon::now()->addDays(30);

            $this->credentialService->shouldReceive('generate')
                ->with(5, 'production', $expiresAt, 42)
                ->andReturn([
                    'credential' => $credential,
                    'plain_secret' => 'prod-secret',
                ]);

            $result = $this->manager->generateCredentials(
                createdBy: 5,
                environment: 'production',
                expiresAt: $expiresAt,
                tenantId: 42
            );

            expect($result['plain_secret'])->toBe('prod-secret');
        });
    });

    describe('rotateSecret', function () {
        it('rotates secret through service', function () {
            $credential = Mockery::mock(ApiCredential::class);

            $this->credentialService->shouldReceive('rotateSecret')
                ->with($credential, 7)
                ->andReturn([
                    'credential' => $credential,
                    'new_secret' => 'new-secret',
                    'old_secret_expires_at' => '2024-12-31 23:59:59',
                ]);

            $result = $this->manager->rotateSecret($credential);

            expect($result['new_secret'])->toBe('new-secret');
        });

        it('uses custom grace days', function () {
            $credential = Mockery::mock(ApiCredential::class);

            $this->credentialService->shouldReceive('rotateSecret')
                ->with($credential, 14)
                ->andReturn([
                    'credential' => $credential,
                    'new_secret' => 'rotated',
                    'old_secret_expires_at' => '2025-01-15',
                ]);

            $result = $this->manager->rotateSecret($credential, 14);

            expect($result['new_secret'])->toBe('rotated');
        });
    });

    describe('generateClientId', function () {
        it('generates client ID with environment prefix', function () {
            $this->keyGenerator->shouldReceive('generateClientId')
                ->with('testing')
                ->andReturn('test_abc123def456');

            $clientId = $this->manager->generateClientId('testing');

            expect($clientId)->toBe('test_abc123def456');
        });

        it('supports production environment', function () {
            $this->keyGenerator->shouldReceive('generateClientId')
                ->with('production')
                ->andReturn('prod_xyz789abc');

            $clientId = $this->manager->generateClientId('production');

            expect($clientId)->toBe('prod_xyz789abc');
        });
    });

    describe('generateClientSecret', function () {
        it('generates secure secret', function () {
            $this->keyGenerator->shouldReceive('generateClientSecret')
                ->andReturn('secure-random-secret-value');

            $secret = $this->manager->generateClientSecret();

            expect($secret)->toBe('secure-random-secret-value');
        });
    });

    describe('generateNonce', function () {
        it('generates unique nonce', function () {
            $this->keyGenerator->shouldReceive('generateNonce')
                ->andReturn('unique-nonce-value-12345');

            $nonce = $this->manager->generateNonce();

            expect($nonce)->toBe('unique-nonce-value-12345');
        });
    });
});
