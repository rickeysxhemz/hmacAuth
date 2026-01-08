<?php

declare(strict_types=1);

use HmacAuth\DTOs\SignaturePayload;
use Illuminate\Http\Request;

describe('SignaturePayload', function () {
    describe('constructor', function () {
        it('creates valid payload with all fields', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/api/users',
                body: '{"name":"test"}',
                timestamp: '1704067200',
                nonce: 'abc123def456abc123def456abc123de',
            );

            expect($payload->method)->toBe('POST')
                ->and($payload->path)->toBe('/api/users')
                ->and($payload->body)->toBe('{"name":"test"}')
                ->and($payload->timestamp)->toBe('1704067200')
                ->and($payload->nonce)->toBe('abc123def456abc123def456abc123de');
        });

        it('throws exception for empty method', function () {
            expect(fn () => new SignaturePayload(
                method: '',
                path: '/api/test',
                body: '',
                timestamp: '1704067200',
                nonce: 'nonce123',
            ))->toThrow(InvalidArgumentException::class, 'Payload fields cannot be empty');
        });

        it('throws exception for empty path', function () {
            expect(fn () => new SignaturePayload(
                method: 'GET',
                path: '',
                body: '',
                timestamp: '1704067200',
                nonce: 'nonce123',
            ))->toThrow(InvalidArgumentException::class, 'Payload fields cannot be empty');
        });

        it('throws exception for empty timestamp', function () {
            expect(fn () => new SignaturePayload(
                method: 'GET',
                path: '/test',
                body: '',
                timestamp: '',
                nonce: 'nonce123',
            ))->toThrow(InvalidArgumentException::class, 'Payload fields cannot be empty');
        });

        it('throws exception for empty nonce', function () {
            expect(fn () => new SignaturePayload(
                method: 'GET',
                path: '/test',
                body: '',
                timestamp: '1704067200',
                nonce: '',
            ))->toThrow(InvalidArgumentException::class, 'Payload fields cannot be empty');
        });

        it('allows empty body', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/test',
                body: '',
                timestamp: '1704067200',
                nonce: 'nonce123nonce123nonce123nonce123',
            );

            expect($payload->body)->toBe('');
        });
    });

    describe('fromRequest()', function () {
        it('creates payload from Laravel request', function () {
            $request = Request::create('/api/users', 'POST', [], [], [], [], '{"data":"test"}');

            $payload = SignaturePayload::fromRequest($request, '1704067200', 'nonce123');

            expect($payload->method)->toBe('POST')
                ->and($payload->path)->toBe('/api/users')
                ->and($payload->body)->toBe('{"data":"test"}')
                ->and($payload->timestamp)->toBe('1704067200')
                ->and($payload->nonce)->toBe('nonce123');
        });

        it('normalizes duplicate slashes in path', function () {
            $request = Request::create('/api//users///test', 'GET');

            $payload = SignaturePayload::fromRequest($request, '1704067200', 'nonce123');

            expect($payload->path)->toBe('/api/users/test');
        });

        it('normalizes trailing slashes', function () {
            $request = Request::create('/api/users/', 'GET');

            $payload = SignaturePayload::fromRequest($request, '1704067200', 'nonce123');

            expect($payload->path)->toBe('/api/users');
        });

        it('normalizes root path', function () {
            $request = Request::create('/', 'GET');

            $payload = SignaturePayload::fromRequest($request, '1704067200', 'nonce123');

            expect($payload->path)->toBe('/');
        });

        it('converts method to uppercase', function () {
            $request = Request::create('/test', 'post');

            $payload = SignaturePayload::fromRequest($request, '1704067200', 'nonce123');

            expect($payload->method)->toBe('POST');
        });

        it('handles GET request with empty body', function () {
            $request = Request::create('/api/resource', 'GET');

            $payload = SignaturePayload::fromRequest($request, '1704067200', 'nonce123');

            expect($payload->body)->toBe('');
        });
    });

    describe('toCanonicalString()', function () {
        it('creates canonical string in correct format', function () {
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/api/users',
                body: '{"name":"test"}',
                timestamp: '1704067200',
                nonce: 'abc123',
            );

            $canonical = $payload->toCanonicalString();

            $expected = "POST\n/api/users\n{\"name\":\"test\"}\n1704067200\nabc123";
            expect($canonical)->toBe($expected);
        });

        it('handles empty body in canonical string', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/api/resource',
                body: '',
                timestamp: '1704067200',
                nonce: 'nonce123',
            );

            $canonical = $payload->toCanonicalString();

            $expected = "GET\n/api/resource\n\n1704067200\nnonce123";
            expect($canonical)->toBe($expected);
        });

        it('preserves body with special characters', function () {
            $bodyWithSpecialChars = '{"message":"Hello\nWorld\t!"}';
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/api/test',
                body: $bodyWithSpecialChars,
                timestamp: '1704067200',
                nonce: 'nonce123',
            );

            $canonical = $payload->toCanonicalString();

            expect($canonical)->toContain($bodyWithSpecialChars);
        });
    });

    describe('getBodySizeInBytes()', function () {
        it('returns correct size for empty body', function () {
            $payload = new SignaturePayload(
                method: 'GET',
                path: '/test',
                body: '',
                timestamp: '1704067200',
                nonce: 'nonce123',
            );

            expect($payload->getBodySizeInBytes())->toBe(0);
        });

        it('returns correct size for simple body', function () {
            $body = 'hello';
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/test',
                body: $body,
                timestamp: '1704067200',
                nonce: 'nonce123',
            );

            expect($payload->getBodySizeInBytes())->toBe(5);
        });

        it('returns correct size for unicode body', function () {
            $body = 'Hello, World!';
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/test',
                body: $body,
                timestamp: '1704067200',
                nonce: 'nonce123',
            );

            expect($payload->getBodySizeInBytes())->toBe(strlen($body));
        });

        it('returns correct size for large body', function () {
            $body = str_repeat('x', 10000);
            $payload = new SignaturePayload(
                method: 'POST',
                path: '/test',
                body: $body,
                timestamp: '1704067200',
                nonce: 'nonce123',
            );

            expect($payload->getBodySizeInBytes())->toBe(10000);
        });
    });
});
