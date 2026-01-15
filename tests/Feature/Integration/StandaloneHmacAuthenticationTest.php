<?php

declare(strict_types=1);

use HmacAuth\DTOs\SignaturePayload;
use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\SignatureService;
use Illuminate\Support\Facades\Route;

/**
 * Comprehensive standalone HMAC authentication integration test.
 *
 * Tests the complete authentication flow without multi-tenancy.
 */
describe('Standalone HMAC Authentication', function () {
    beforeEach(function () {
        // Disable tenancy for standalone tests
        config(['hmac.tenancy.enabled' => false]);
        config(['hmac.enforce_environment' => false]);

        // Register test routes with HMAC middleware
        Route::middleware('hmac.verify')->group(function () {
            Route::get('/api/standalone/users', fn () => response()->json([
                'users' => [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
            ]));

            Route::post('/api/standalone/users', fn () => response()->json([
                'message' => 'User created',
                'user' => request()->all(),
            ], 201));

            Route::put('/api/standalone/users/{id}', fn ($id) => response()->json([
                'message' => 'User updated',
                'id' => $id,
            ]));

            Route::delete('/api/standalone/users/{id}', fn ($id) => response()->json([
                'message' => 'User deleted',
                'id' => $id,
            ]));
        });
    });

    describe('complete authentication flow', function () {
        it('authenticates GET request with valid credentials', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $response = $this->getJson($path, [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('users.0.name', 'John')
                ->assertJsonPath('users.1.name', 'Jane');
        });

        it('authenticates POST request with JSON body', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $body = json_encode(['name' => 'Alice', 'email' => 'alice@example.com']);
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'POST',
                path: $path,
                body: $body,
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $response = $this->postJson($path, ['name' => 'Alice', 'email' => 'alice@example.com'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('message', 'User created')
                ->assertJsonPath('user.name', 'Alice');
        });

        it('authenticates PUT request with path parameters', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users/123';
            $body = json_encode(['name' => 'Updated']);
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'PUT',
                path: $path,
                body: $body,
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $response = $this->putJson($path, ['name' => 'Updated'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('message', 'User updated')
                ->assertJsonPath('id', '123');
        });

        it('authenticates DELETE request', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users/456';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'DELETE',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $response = $this->deleteJson($path, [], [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('message', 'User deleted')
                ->assertJsonPath('id', '456');
        });
    });

    describe('credential lifecycle', function () {
        it('tracks last_used_at on successful authentication', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            $credential = ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
                'last_used_at' => null,
            ]);

            expect($credential->last_used_at)->toBeNull();

            $path = '/api/standalone/users';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $this->getJson($path, [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ])->assertStatus(200);

            $credential->refresh();
            expect($credential->last_used_at)->not->toBeNull();
        });

        it('supports different hash algorithms', function () {
            foreach (['sha256', 'sha384', 'sha512'] as $algorithm) {
                $clientId = 'test_'.bin2hex(random_bytes(16));
                $clientSecret = generateTestSecret();

                ApiCredential::create([
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'hmac_algorithm' => $algorithm,
                    'environment' => 'testing',
                    'is_active' => true,
                    'created_by' => 1,
                ]);

                $path = '/api/standalone/users';
                $timestamp = (string) time();
                $nonce = bin2hex(random_bytes(16));

                $signatureService = new SignatureService;
                $payload = new SignaturePayload(
                    method: 'GET',
                    path: $path,
                    body: '[]',
                    timestamp: $timestamp,
                    nonce: $nonce,
                );
                $signature = $signatureService->generate($payload, $clientSecret, $algorithm);

                $response = $this->getJson($path, [
                    'X-Api-Key' => $clientId,
                    'X-Signature' => $signature,
                    'X-Timestamp' => $timestamp,
                    'X-Nonce' => $nonce,
                ]);

                $response->assertStatus(200);
            }
        });
    });

    describe('security validations', function () {
        it('rejects missing API key', function () {
            $response = $this->getJson('/api/standalone/users', [
                'X-Signature' => 'any',
                'X-Timestamp' => (string) time(),
                'X-Nonce' => bin2hex(random_bytes(16)),
            ]);

            $response->assertStatus(401)
                ->assertJsonPath('success', false);
        });

        it('rejects missing signature', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => generateTestSecret(),
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $response = $this->getJson('/api/standalone/users', [
                'X-Api-Key' => $clientId,
                'X-Timestamp' => (string) time(),
                'X-Nonce' => bin2hex(random_bytes(16)),
            ]);

            $response->assertStatus(401);
        });

        it('rejects tampered body', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $originalBody = json_encode(['name' => 'Original']);
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            // Sign with original body
            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'POST',
                path: $path,
                body: $originalBody,
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            // Send with tampered body
            $response = $this->postJson($path, ['name' => 'Tampered'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJsonPath('message', 'Invalid signature');
        });

        it('rejects replay attacks with reused nonce', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16)); // Same nonce used twice

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $headers = [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ];

            // First request should succeed
            $this->getJson($path, $headers)->assertStatus(200);

            // Second request with same nonce should fail (replay attack)
            $this->getJson($path, $headers)->assertStatus(401)
                ->assertJsonPath('message', 'Duplicate nonce detected');
        });

        it('rejects expired credentials', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'expires_at' => now()->subDay(),
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $response = $this->getJson($path, [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401);
        });
    });

    describe('secret rotation', function () {
        it('accepts both old and new secret during grace period', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $oldSecret = generateTestSecret();
            $newSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $newSecret,
                'old_client_secret' => $oldSecret,
                'old_secret_expires_at' => now()->addDays(7),
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $signatureService = new SignatureService;

            // Test with old secret
            $timestamp1 = (string) time();
            $nonce1 = bin2hex(random_bytes(16));
            $payload1 = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp1,
                nonce: $nonce1,
            );
            $signature1 = $signatureService->generate($payload1, $oldSecret, 'sha256');

            $this->getJson($path, [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature1,
                'X-Timestamp' => $timestamp1,
                'X-Nonce' => $nonce1,
            ])->assertStatus(200);

            // Test with new secret
            $timestamp2 = (string) time();
            $nonce2 = bin2hex(random_bytes(16));
            $payload2 = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp2,
                nonce: $nonce2,
            );
            $signature2 = $signatureService->generate($payload2, $newSecret, 'sha256');

            $this->getJson($path, [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature2,
                'X-Timestamp' => $timestamp2,
                'X-Nonce' => $nonce2,
            ])->assertStatus(200);
        });

        it('rejects old secret after grace period expires', function () {
            $clientId = 'test_'.bin2hex(random_bytes(16));
            $oldSecret = generateTestSecret();
            $newSecret = generateTestSecret();

            ApiCredential::create([
                'client_id' => $clientId,
                'client_secret' => $newSecret,
                'old_client_secret' => $oldSecret,
                'old_secret_expires_at' => now()->subDay(), // Expired
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $path = '/api/standalone/users';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $signatureService = new SignatureService;
            $payload = new SignaturePayload(
                method: 'GET',
                path: $path,
                body: '[]',
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $oldSecret, 'sha256');

            $response = $this->getJson($path, [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJsonPath('message', 'Invalid signature');
        });
    });
});
