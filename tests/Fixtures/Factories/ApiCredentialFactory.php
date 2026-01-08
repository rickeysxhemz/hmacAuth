<?php

declare(strict_types=1);

namespace HmacAuth\Tests\Fixtures\Factories;

use HmacAuth\Models\ApiCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiCredential>
 */
class ApiCredentialFactory extends Factory
{
    protected $model = ApiCredential::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->faker->numberBetween(1, 100),
            'client_id' => 'test_' . bin2hex(random_bytes(16)),
            'client_secret' => $this->generateSecret(),
            'hmac_algorithm' => 'sha256',
            'environment' => 'testing',
            'is_active' => true,
            'last_used_at' => null,
            'expires_at' => null,
            'old_client_secret' => null,
            'old_secret_expires_at' => null,
            'created_by' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * Generate a secure random secret.
     */
    protected function generateSecret(int $length = 48): string
    {
        $bytes = random_bytes($length);
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($bytes));
    }

    /**
     * Indicate that the credential is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the credential is for production.
     */
    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
        ]);
    }

    /**
     * Indicate that the credential is for testing.
     */
    public function testing(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'testing',
        ]);
    }

    /**
     * Indicate that the credential is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the credential will expire soon.
     */
    public function expiringSoon(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the credential has never expired.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the credential is in secret rotation.
     */
    public function withSecretRotation(int $daysUntilOldExpires = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'old_client_secret' => $this->generateSecret(),
            'old_secret_expires_at' => now()->addDays($daysUntilOldExpires),
        ]);
    }

    /**
     * Indicate that the credential has an expired old secret.
     */
    public function withExpiredOldSecret(): static
    {
        return $this->state(fn (array $attributes) => [
            'old_client_secret' => $this->generateSecret(),
            'old_secret_expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set the HMAC algorithm to SHA-384.
     */
    public function sha384(): static
    {
        return $this->state(fn (array $attributes) => [
            'hmac_algorithm' => 'sha384',
        ]);
    }

    /**
     * Set the HMAC algorithm to SHA-512.
     */
    public function sha512(): static
    {
        return $this->state(fn (array $attributes) => [
            'hmac_algorithm' => 'sha512',
        ]);
    }

    /**
     * Set a specific company ID.
     */
    public function forCompany(int $companyId): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $companyId,
        ]);
    }

    /**
     * Set a specific client ID.
     */
    public function withClientId(string $clientId): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $clientId,
        ]);
    }

    /**
     * Set a specific client secret.
     */
    public function withSecret(string $secret): static
    {
        return $this->state(fn (array $attributes) => [
            'client_secret' => $secret,
        ]);
    }

    /**
     * Mark the credential as recently used.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => now()->subMinutes(5),
        ]);
    }
}
