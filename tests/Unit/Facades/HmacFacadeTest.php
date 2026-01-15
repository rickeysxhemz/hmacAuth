<?php

declare(strict_types=1);

use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Facades\Hmac;
use HmacAuth\HmacManager;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;

describe('Hmac Facade', function () {
    beforeEach(function () {
        config(['hmac.tenancy.enabled' => false]);
    });

    it('resolves to HmacManager instance', function () {
        $resolved = Hmac::getFacadeRoot();

        expect($resolved)->toBeInstanceOf(HmacManager::class);
    });

    it('generates client id', function () {
        $clientId = Hmac::generateClientId('testing');

        expect($clientId)->toBeString()
            ->and($clientId)->toStartWith('test_');
    });

    it('generates client secret', function () {
        $secret = Hmac::generateClientSecret();

        expect($secret)->toBeString()
            ->and(strlen($secret))->toBeGreaterThan(32);
    });

    it('generates nonce', function () {
        $nonce = Hmac::generateNonce();

        expect($nonce)->toBeString()
            ->and(strlen($nonce))->toBeGreaterThanOrEqual(32);
    });

    it('generates signature', function () {
        $payload = new SignaturePayload(
            method: 'GET',
            path: '/api/test',
            timestamp: (string) time(),
            nonce: bin2hex(random_bytes(16)),
            body: ''
        );

        $signature = Hmac::generateSignature($payload, 'test-secret');

        expect($signature)->toBeString()
            ->and(strlen($signature))->toBeGreaterThan(0);
    });

    it('generates credentials', function () {
        $result = Hmac::generateCredentials(
            createdBy: 1,
            environment: 'testing'
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['credential', 'plain_secret'])
            ->and($result['credential'])->toBeInstanceOf(ApiCredential::class)
            ->and($result['plain_secret'])->toBeString();
    });

    it('verifies request and returns result', function () {
        // Create a credential
        $credentials = Hmac::generateCredentials(createdBy: 1, environment: 'testing');
        $credential = $credentials['credential'];
        $secret = $credentials['plain_secret'];

        // Create a valid request
        $timestamp = (string) time();
        $nonce = Hmac::generateNonce();

        $payload = new SignaturePayload(
            method: 'GET',
            path: '/api/test',
            timestamp: $timestamp,
            nonce: $nonce,
            body: ''
        );

        $signature = Hmac::generateSignature($payload, $secret);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Api-Key', $credential->client_id);
        $request->headers->set('X-Signature', $signature);
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Nonce', $nonce);

        $result = Hmac::verify($request);

        expect($result)->toBeInstanceOf(VerificationResult::class)
            ->and($result->isValid())->toBeTrue();
    });

    it('verifies signature', function () {
        $signature = 'test_signature_value';

        $result = Hmac::verifySignature($signature, $signature);

        expect($result)->toBeTrue();
    });

    it('returns false for mismatched signatures', function () {
        $result = Hmac::verifySignature('signature1', 'signature2');

        expect($result)->toBeFalse();
    });

    it('rotates secret for credential', function () {
        $credentials = Hmac::generateCredentials(createdBy: 1, environment: 'testing');
        $credential = $credentials['credential'];

        $result = Hmac::rotateSecret($credential, graceDays: 7);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['credential', 'new_secret', 'old_secret_expires_at'])
            ->and($result['credential'])->toBeInstanceOf(ApiCredential::class)
            ->and($result['new_secret'])->toBeString();
    });
});
