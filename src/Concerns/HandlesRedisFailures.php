<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

use Closure;
use Illuminate\Support\Facades\Log;
use RedisException;
use Throwable;

/**
 * Handles Redis failures with configurable fail behavior.
 */
trait HandlesRedisFailures
{
    /**
     * Execute a Redis operation with error handling.
     *
     * @template T
     *
     * @param  Closure(): T  $operation
     * @param  T  $default
     * @param  array<string, mixed>  $logData
     * @param  class-string<Throwable>|null  $exceptionClass
     * @return T
     *
     * @throws Throwable
     */
    protected function executeRedisOperation(
        Closure $operation,
        mixed $default,
        string $context,
        array $logData = [],
        ?string $exceptionClass = null,
        ?string $exceptionMessage = null
    ): mixed {
        try {
            return $operation();
        } catch (RedisException $e) {
            Log::error("Redis failure in {$context}", [
                ...$logData,
                'error' => $e->getMessage(),
            ]);

            if ($this->shouldFailOnRedisError() && $exceptionClass !== null) {
                throw new $exceptionClass(
                    $exceptionMessage ?? 'Redis operation failed',
                    previous: $e
                );
            }

            return $default;
        }
    }

    /**
     * Determine if the service should throw on Redis errors.
     */
    abstract protected function shouldFailOnRedisError(): bool;
}
