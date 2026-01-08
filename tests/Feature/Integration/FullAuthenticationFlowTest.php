<?php

declare(strict_types=1);

use HmacAuth\Models\ApiCredential;
use HmacAuth\Services\SignatureService;
use Illuminate\Support\Facades\Route;

describe('Full Authentication Flow', function () {
    beforeEach(function () {
        // Register a test route with the middleware
        Route::middleware('hmac.verify')->post('/test-api/resource', function () {
            return response()->json([
                'success' => true,
                'company_id' => request()->attributes->get('company_id'),
                'message' => 'Authenticated successfully',
            ]);
        });

        Route::middleware('hmac.verify')->get('/test-api/read', function () {
            return response()->json([
                'success' => true,
                'data' => 'protected data',
            ]);
        });
    });

    describe('successful authentication', function () {
        it('authenticates valid request with correct headers', function () {
            // Create a test credential
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            $credential = ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            // Generate valid headers
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $body = '{"data":"test"}';
            $path = '/test-api/resource';

            // Generate signature
            $signatureService = new SignatureService();
            $payload = new \HmacAuth\DTOs\SignaturePayload(
                method: 'POST',
                path: $path,
                body: $body,
                timestamp: $timestamp,
                nonce: $nonce,
            );
            $signature = $signatureService->generate($payload, $clientSecret, 'sha256');

            $response = $this->postJson($path, ['data' => 'test'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'company_id' => 1,
                ]);
        });

        it('updates last_used_at on successful authentication', function () {
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            $credential = ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
                'last_used_at' => null,
            ]);

            // Refresh to get the credential back from database (to verify encryption/decryption)
            $credential->refresh();

            // Verify the secret is retrieved correctly
            expect($credential->client_secret)->toBe($clientSecret);
            expect($credential->last_used_at)->toBeNull();

            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $path = '/test-api/read';

            $signatureService = new SignatureService();
            // Note: Laravel's getJson sends '[]' as the body, so we must sign with that
            $payload = new \HmacAuth\DTOs\SignaturePayload(
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

            $response->assertStatus(200);

            $credential->refresh();
            expect($credential->last_used_at)->not->toBeNull();
        });
    });

    describe('failed authentication', function () {
        it('rejects request with missing headers', function () {
            $response = $this->postJson('/test-api/resource', ['data' => 'test']);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'code' => 'NOT_AUTHORIZED',
                ]);
        });

        it('rejects request with invalid client ID', function () {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $response = $this->postJson('/test-api/resource', ['data' => 'test'], [
                'X-Api-Key' => 'non-existent-client',
                'X-Signature' => 'invalid-signature',
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid client ID',
                ]);
        });

        it('rejects request with invalid signature', function () {
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $response = $this->postJson('/test-api/resource', ['data' => 'test'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => 'wrong-signature',
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid signature',
                ]);
        });

        it('rejects request with expired timestamp', function () {
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            // Timestamp from 10 minutes ago
            $timestamp = (string) (time() - 600);
            $nonce = bin2hex(random_bytes(16));

            $response = $this->postJson('/test-api/resource', ['data' => 'test'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => 'any-signature',
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid or expired timestamp',
                ]);
        });

        it('rejects request with short nonce', function () {
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'created_by' => 1,
            ]);

            $timestamp = (string) time();
            $nonce = 'short'; // Less than 32 characters

            $response = $this->postJson('/test-api/resource', ['data' => 'test'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => 'any-signature',
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Nonce too short',
                ]);
        });

        it('rejects request with inactive credential', function () {
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => false, // Inactive
                'created_by' => 1,
            ]);

            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $response = $this->postJson('/test-api/resource', ['data' => 'test'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => 'any-signature',
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid client ID',
                ]);
        });

        it('rejects request with expired credential', function () {
            $clientId = 'test_' . bin2hex(random_bytes(16));
            $clientSecret = generateTestSecret();

            ApiCredential::create([
                'company_id' => 1,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'hmac_algorithm' => 'sha256',
                'environment' => 'testing',
                'is_active' => true,
                'expires_at' => now()->subDay(), // Expired
                'created_by' => 1,
            ]);

            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            $response = $this->postJson('/test-api/resource', ['data' => 'test'], [
                'X-Api-Key' => $clientId,
                'X-Signature' => 'any-signature',
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ]);

            // Expired credentials are filtered out by findActiveByClientId
            $response->assertStatus(401);
        });
    });
});
