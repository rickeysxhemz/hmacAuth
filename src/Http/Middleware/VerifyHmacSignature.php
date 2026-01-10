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
use Illuminate\Contracts\Events\Dispatcher;
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
        private Dispatcher $dispatcher,
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
        $this->setTenantAttributes($request, $credential);
        $this->dispatchSuccessEvent($request, $result);

        return $next($request);
    }

    private function setTenantAttributes(Request $request, ApiCredential $credential): void
    {
        if (! $this->config->tenancyEnabled) {
            return;
        }

        $tenantId = $credential->{$this->config->tenancyColumn};
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set($this->config->tenancyColumn, $tenantId);
    }

    private function dispatchSuccessEvent(Request $request, VerificationResult $result): void
    {
        $credential = $result->credential;
        if (! $credential instanceof ApiCredential) {
            return;
        }

        $this->dispatcher->dispatch(new AuthenticationSucceeded($request, $credential, $request->ip() ?? '0.0.0.0'));
    }

    private function dispatchFailedEvent(Request $request, VerificationResult $result): void
    {
        $failureReason = $result->failureReason;
        if (! $failureReason instanceof VerificationFailureReason) {
            return;
        }

        $clientId = $request->header($this->config->apiKeyHeader);

        $this->dispatcher->dispatch(new AuthenticationFailed($request, is_string($clientId) ? $clientId : 'unknown', $failureReason, $request->ip() ?? '0.0.0.0', $result->credential));
    }
}
