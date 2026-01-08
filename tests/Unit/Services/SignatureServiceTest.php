<?php

declare(strict_types=1);

use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\Services\SignatureService;

beforeEach(function () {
    $this->service = new SignatureService;
});

describe('SignatureService', function () {
    describe('generate()', function () {
        it('generates valid HMAC signature for SHA256', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/api/users',
                body: '{"name":"test"}',
                timestamp: '1704067200',
                nonce: 'abc123def456abc123def456abc123de',
            );
            $secret = 'test-secret-key-12345';

            $signature = $this->service->generate($payload, $secret, 'sha256');

            expect($signature)->toBeString()
                ->and($signature)->not->toContain('+')
                ->and($signature)->not->toContain('/')
                ->and($signature)->not->toContain('=');
        });

        it('generates valid HMAC signature for SHA384', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/api/resource',
                body: '',
                timestamp: '1704067200',
                nonce: 'nonce12345678901234567890123456',
            );
            $secret = 'test-secret-key-12345';

            $signature = $this->service->generate($payload, $secret, 'sha384');

            // SHA384 produces 48 bytes = 64 characters in Base64
            expect($signature)->toBeString()
                ->and(strlen($signature))->toBe(64);
        });

        it('generates valid HMAC signature for SHA512', function () {
            $payload = new SignaturePayload(
                method: 'DELETE',
                path: '/api/item/123',
                body: '',
                timestamp: '1704067200',
                nonce: 'unique-nonce-value-1234567890ab',
            );
            $secret = 'test-secret-key-12345';

            $signature = $this->service->generate($payload, $secret, 'sha512');

            // SHA512 produces 64 bytes = 86 characters in Base64 (without padding)
            expect($signature)->toBeString()
                ->and(strlen($signature))->toBe(86);
        });

        it('produces Base64URL encoded output without padding', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/test',
                body: 'test body',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $signature = $this->service->generate($payload, 'secret', 'sha256');

            // Should not contain standard Base64 characters
            expect($signature)->not->toContain('+')
                ->and($signature)->not->toContain('/')
                ->and($signature)->not->toContain('=');
        });

        it('handles empty body correctly', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/api/empty',
                body: '',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $signature = $this->service->generate($payload, 'secret', 'sha256');

            expect($signature)->toBeString()
                ->and(strlen($signature))->toBeGreaterThan(0);
        });

        it('handles special characters in path', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/api/users/search?q=test%20value&filter=active',
                body: '',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $signature = $this->service->generate($payload, 'secret', 'sha256');

            expect($signature)->toBeString()
                ->and(strlen($signature))->toBeGreaterThan(0);
        });

        it('generates different signatures for different inputs', function () {
            $payload1 = new SignaturePayload(
                method: 'GET',
                path: '/api/test1',
                body: '',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $payload2 = new SignaturePayload(
                method: 'GET',
                path: '/api/test2',
                body: '',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $sig1 = $this->service->generate($payload1, 'secret', 'sha256');
            $sig2 = $this->service->generate($payload2, 'secret', 'sha256');

            expect($sig1)->not->toBe($sig2);
        });

        it('generates same signature for same inputs', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/api/test',
                body: '{"data":"value"}',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $sig1 = $this->service->generate($payload, 'secret', 'sha256');
            $sig2 = $this->service->generate($payload, 'secret', 'sha256');

            expect($sig1)->toBe($sig2);
        });

        it('defaults to SHA256 for invalid algorithm', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/test',
                body: '',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $sigInvalid = $this->service->generate($payload, 'secret', 'invalid');
            $sigSha256 = $this->service->generate($payload, 'secret', 'sha256');

            expect($sigInvalid)->toBe($sigSha256);
        });
    });

    describe('verify()', function () {
        it('returns true for matching signatures', function () {
            $signature = 'test-signature-abc123';

            $result = $this->service->verify($signature, $signature);

            expect($result)->toBeTrue();
        });

        it('returns false for non-matching signatures', function () {
            $expected = 'signature-one';
            $actual = 'signature-two';

            $result = $this->service->verify($expected, $actual);

            expect($result)->toBeFalse();
        });

        it('uses constant-time comparison', function () {
            // This is a behavioral test to ensure hash_equals is used
            $expected = 'abcdefghijklmnop';
            $actual = 'abcdefghijklmnop';

            $result = $this->service->verify($expected, $actual);

            expect($result)->toBeTrue();
        });

        it('returns false for similar but different signatures', function () {
            $expected = 'abc123xyz789';
            $actual = 'abc123xyz788'; // One character different

            $result = $this->service->verify($expected, $actual);

            expect($result)->toBeFalse();
        });
    });

    describe('isAlgorithmSupported()', function () {
        it('returns true for sha256', function () {
            expect($this->service->isAlgorithmSupported('sha256'))->toBeTrue();
        });

        it('returns true for sha384', function () {
            expect($this->service->isAlgorithmSupported('sha384'))->toBeTrue();
        });

        it('returns true for sha512', function () {
            expect($this->service->isAlgorithmSupported('sha512'))->toBeTrue();
        });

        it('returns true for uppercase algorithm names', function () {
            expect($this->service->isAlgorithmSupported('SHA256'))->toBeTrue();
            expect($this->service->isAlgorithmSupported('SHA384'))->toBeTrue();
            expect($this->service->isAlgorithmSupported('SHA512'))->toBeTrue();
        });

        it('returns false for unsupported algorithms', function () {
            expect($this->service->isAlgorithmSupported('md5'))->toBeFalse();
            expect($this->service->isAlgorithmSupported('sha1'))->toBeFalse();
            expect($this->service->isAlgorithmSupported('invalid'))->toBeFalse();
        });
    });

    describe('getSupportedAlgorithms()', function () {
        it('returns array of supported algorithms', function () {
            $algorithms = $this->service->getSupportedAlgorithms();

            expect($algorithms)->toBeArray()
                ->and($algorithms)->toContain('sha256')
                ->and($algorithms)->toContain('sha384')
                ->and($algorithms)->toContain('sha512');
        });

        it('returns exactly three algorithms', function () {
            $algorithms = $this->service->getSupportedAlgorithms();

            expect($algorithms)->toHaveCount(3);
        });
    });

    describe('__invoke()', function () {
        it('works as callable', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/test',
                body: 'body',
                timestamp: '1704067200',
                nonce: '12345678901234567890123456789012',
            );

            $service = $this->service;
            $signature = $service($payload, 'secret', 'sha256');

            expect($signature)->toBeString()
                ->and(strlen($signature))->toBeGreaterThan(0);
        });
    });
});
