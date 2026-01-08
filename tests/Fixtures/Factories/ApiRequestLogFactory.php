<?php

declare(strict_types=1);

namespace HmacAuth\Tests\Fixtures\Factories;

use HmacAuth\Models\ApiRequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    protected $model = ApiRequestLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_credential_id' => null,
            'company_id' => $this->faker->numberBetween(1, 100),
            'client_id' => 'test_'.bin2hex(random_bytes(16)),
            'request_method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'request_path' => '/api/'.$this->faker->slug(2),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'signature_valid' => true,
            'response_status' => 200,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the authentication was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'signature_valid' => true,
            'response_status' => 200,
        ]);
    }

    /**
     * Indicate that the authentication failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'signature_valid' => false,
            'response_status' => 401,
        ]);
    }

    /**
     * Set a specific IP address.
     */
    public function fromIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip,
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
    public function forClient(string $clientId): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $clientId,
        ]);
    }

    /**
     * Set a specific credential.
     */
    public function forCredential(int $credentialId, int $companyId, string $clientId): static
    {
        return $this->state(fn (array $attributes) => [
            'api_credential_id' => $credentialId,
            'company_id' => $companyId,
            'client_id' => $clientId,
        ]);
    }

    /**
     * Set a specific HTTP method.
     */
    public function withMethod(string $method): static
    {
        return $this->state(fn (array $attributes) => [
            'request_method' => strtoupper($method),
        ]);
    }

    /**
     * Set a specific request path.
     */
    public function withPath(string $path): static
    {
        return $this->state(fn (array $attributes) => [
            'request_path' => $path,
        ]);
    }

    /**
     * Set a GET request.
     */
    public function get(): static
    {
        return $this->withMethod('GET');
    }

    /**
     * Set a POST request.
     */
    public function post(): static
    {
        return $this->withMethod('POST');
    }

    /**
     * Set a recent timestamp.
     */
    public function recent(int $minutesAgo = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    /**
     * Set an old timestamp.
     */
    public function old(int $daysAgo = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    /**
     * Set a specific timestamp.
     */
    public function at(\DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $dateTime,
        ]);
    }
}
