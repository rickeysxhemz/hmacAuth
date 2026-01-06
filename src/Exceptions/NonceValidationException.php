<?php

declare(strict_types=1);

namespace HmacAuth\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when nonce validation fails.
 */
final class NonceValidationException extends RuntimeException
{
    public function __construct(
        string $message = 'Nonce validation failed',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
