<?php

declare(strict_types=1);

namespace HmacAuth\Exceptions;

use HmacAuth\DTOs\VerificationResult;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when HMAC authentication fails.
 */
final class HmacAuthenticationException extends HttpException
{
    public function __construct(
        private readonly VerificationResult $result,
    ) {
        parent::__construct(
            $result->getHttpStatus(),
            $result->getErrorMessage() ?? 'Authentication failed'
        );
    }

    public function getResult(): VerificationResult
    {
        return $this->result;
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'NOT_AUTHORIZED',
            'data' => null,
        ], $this->getStatusCode());
    }
}