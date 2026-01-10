<?php

declare(strict_types=1);

namespace HmacAuth\Http\Middleware;

use Closure;
use HmacAuth\Contracts\HmacVerifierInterface;
use HmacAuth\DTOs\HmacConfig;
use HmacAuth\Exceptions\HmacAuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify HMAC signatures on incoming requests.
 */
final readonly class VerifyHmacSignature
{
    public function __construct(
        private HmacVerifierInterface $verifier,
        private HmacConfig $config,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->config->enabled) {
            return $next($request);
        }

        $result = $this->verifier->verify($request);

        if ($result->isFailure()) {
            throw new HmacAuthenticationException($result);
        }

        $request->attributes->set('hmac_credential', $result->credential);
        $this->setTenantAttributes($request, $result->credential);

        return $next($request);
    }

    private function setTenantAttributes(Request $request, mixed $credential): void
    {
        if (! $this->config->tenancyEnabled || $credential === null) {
            return;
        }

        $tenantId = $credential->{$this->config->tenancyColumn};
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set($this->config->tenancyColumn, $tenantId);
    }
}