<?php

declare(strict_types=1);

namespace HmacAuth\Http\Middleware;

use Closure;
use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Events\AuthenticationFailed;
use HmacAuth\Events\AuthenticationSucceeded;
use HmacAuth\Models\ApiCredential;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify HMAC signatures on incoming requests.
 */
final readonly class VerifyHmacSignature
{
    public function __construct(
        private HmacVerifierInterface $verificationService,
        private HmacConfig $config,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->config->enabled) {
            return $next($request);
        }

        $result = $this->verificationService->verify($request);
        $resultArray = $result->toArray();

        if (! $resultArray['valid'] || $resultArray['credential'] === null) {
            $this->dispatchFailedEvent($request, $result);

            return response()->json([
                'success' => false,
                'message' => $resultArray['error'],
                'code' => 'NOT_AUTHORIZED',
                'data' => null,
            ], $result->getHttpStatus());
        }

        $credential = $resultArray['credential'];
        $request->attributes->set('hmac_credential', $credential);

        // Set tenant attribute when tenancy is enabled
        if ((bool) config('hmac.tenancy.enabled', false)) {
            $tenantColumn = (string) config('hmac.tenancy.column', 'tenant_id');
            $tenantId = $credential->{$tenantColumn};
            $request->attributes->set('tenant_id', $tenantId);
            $request->attributes->set($tenantColumn, $tenantId);
        }

        $this->dispatchSuccessEvent($request, $result);

        return $next($request);
    }

    /**
     * Dispatch authentication succeeded event.
     */
    private function dispatchSuccessEvent(Request $request, VerificationResult $result): void
    {
        $credential = $result->credential;
        if (! $credential instanceof ApiCredential) {
            return;
        }

        event(new AuthenticationSucceeded($request, $credential, $request->ip() ?? '0.0.0.0'));
    }

    /**
     * Dispatch authentication failed event.
     */
    private function dispatchFailedEvent(Request $request, VerificationResult $result): void
    {
        $failureReason = $result->failureReason;
        if (! $failureReason instanceof VerificationFailureReason) {
            return;
        }

        $clientId = $request->header($this->config->apiKeyHeader);

        event(new AuthenticationFailed($request, is_string($clientId) ? $clientId : 'unknown', $failureReason, $request->ip() ?? '0.0.0.0', $result->credential));
    }
}
