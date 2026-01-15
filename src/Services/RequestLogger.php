<?php

declare(strict_types=1);

namespace HmacAuth\Services;

use HmacAuth\Concerns\TruncatesStrings;
use HmacAuth\Contracts\ApiRequestLogRepositoryInterface;
use HmacAuth\Contracts\RequestLoggerInterface;
use HmacAuth\Contracts\TenancyConfigInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Service for logging HMAC authentication requests.
 */
final readonly class RequestLogger implements RequestLoggerInterface
{
    use TruncatesStrings;

    public function __construct(
        private ApiRequestLogRepositoryInterface $logRepository,
        private HmacConfig $config,
        private TenancyConfigInterface $tenancyConfig,
    ) {}

    /**
     * Log successful authentication attempt.
     */
    public function logSuccessfulAttempt(Request $request, ApiCredential $credential): void
    {
        $data = [
            'api_credential_id' => $credential->id,
            'client_id' => $credential->client_id,
            'request_method' => $request->method(),
            'request_path' => $this->truncatePath($request->getPathInfo()),
            'ip_address' => $request->ip() ?? '0.0.0.0',
            'user_agent' => $this->truncateUserAgent($request->userAgent()),
            'signature_valid' => true,
            'response_status' => 200,
        ];

        if ($this->tenancyConfig->isEnabled()) {
            $tenantColumn = $this->tenancyConfig->getColumn();
            $data[$tenantColumn] = $credential->{$tenantColumn};
        }

        $this->logRepository->create($data);
    }

    /**
     * Log failed authentication attempt.
     */
    public function logFailedAttempt(
        Request $request,
        string $clientId,
        string $reason,
        ?ApiCredential $credential = null
    ): void {
        $data = [
            'api_credential_id' => $credential?->id,
            'client_id' => $clientId,
            'request_method' => $request->method(),
            'request_path' => $this->truncatePath($request->getPathInfo()),
            'ip_address' => $request->ip() ?? '0.0.0.0',
            'user_agent' => $this->truncateUserAgent($request->userAgent()),
            'signature_valid' => false,
            'response_status' => 401,
        ];

        if ($this->tenancyConfig->isEnabled() && $credential !== null) {
            $tenantColumn = $this->tenancyConfig->getColumn();
            $data[$tenantColumn] = $credential->{$tenantColumn};
        }

        $this->logRepository->create($data);

        Log::warning('HMAC authentication failed', [
            'client_id' => $clientId,
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $this->truncatePath($request->getPathInfo()),
        ]);
    }

    /**
     * Check if IP has too many failed attempts.
     */
    public function hasExcessiveFailures(
        string $ipAddress,
        ?int $threshold = null,
        ?int $minutes = null
    ): bool {
        if (! $this->config->ipBlockingEnabled) {
            return false;
        }

        $threshold ??= $this->config->ipBlockingThreshold;
        $minutes ??= $this->config->ipBlockingWindowMinutes;

        return $this->logRepository->countFailedByIp($ipAddress, $minutes) >= $threshold;
    }

    /**
     * Check if client has too many failed attempts.
     */
    public function hasExcessiveClientFailures(
        string $clientId,
        ?int $threshold = null,
        ?int $minutes = null
    ): bool {
        $threshold ??= $this->config->ipBlockingThreshold;
        $minutes ??= $this->config->ipBlockingWindowMinutes;

        return $this->logRepository->countFailedAttempts($clientId, $minutes) >= $threshold;
    }

    /**
     * Clear failed attempts for a specific IP address.
     */
    public function clearFailuresForIp(string $ipAddress, ?int $minutes = null): int
    {
        $minutes ??= $this->config->ipBlockingWindowMinutes;

        return $this->logRepository->deleteFailedByIp($ipAddress, $minutes);
    }

    /**
     * Check if IP blocking is enabled.
     */
    public function isIpBlockingEnabled(): bool
    {
        return $this->config->ipBlockingEnabled;
    }

    /**
     * Get IP blocking configuration values.
     *
     * @return array{threshold: int, window_minutes: int}
     */
    public function getIpBlockingConfig(): array
    {
        return [
            'threshold' => $this->config->ipBlockingThreshold,
            'window_minutes' => $this->config->ipBlockingWindowMinutes,
        ];
    }
}
