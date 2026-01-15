<?php

declare(strict_types=1);

use HmacAuth\Concerns\HandlesRedisFailures;
use HmacAuth\Exceptions\NonceValidationException;

// Test class that uses the trait
class HandlesRedisFailuresTestClass
{
    use HandlesRedisFailures;

    private bool $shouldFail = false;

    public function setShouldFailOnError(bool $shouldFail): void
    {
        $this->shouldFail = $shouldFail;
    }

    protected function shouldFailOnRedisError(): bool
    {
        return $this->shouldFail;
    }

    public function testExecuteRedisOperation(
        Closure $operation,
        mixed $default,
        string $context,
        array $logData = [],
        ?string $exceptionClass = null,
        ?string $exceptionMessage = null
    ): mixed {
        return $this->executeRedisOperation(
            $operation,
            $default,
            $context,
            $logData,
            $exceptionClass,
            $exceptionMessage
        );
    }
}

describe('HandlesRedisFailures', function () {
    beforeEach(function () {
        $this->handler = new HandlesRedisFailuresTestClass;
    });

    describe('executeRedisOperation', function () {
        it('returns result from successful operation', function () {
            $result = $this->handler->testExecuteRedisOperation(
                fn () => 'success',
                'default',
                'test operation'
            );

            expect($result)->toBe('success');
        });

        it('returns default value on Redis failure when not configured to fail', function () {
            $this->handler->setShouldFailOnError(false);

            $result = $this->handler->testExecuteRedisOperation(
                fn () => throw new \RedisException('Connection failed'),
                'default_value',
                'test operation'
            );

            expect($result)->toBe('default_value');
        });

        it('throws exception on Redis failure when configured to fail', function () {
            $this->handler->setShouldFailOnError(true);

            expect(fn () => $this->handler->testExecuteRedisOperation(
                fn () => throw new \RedisException('Connection failed'),
                'default',
                'test operation',
                [],
                NonceValidationException::class,
                'Nonce storage failed'
            ))->toThrow(NonceValidationException::class, 'Nonce storage failed');
        });

        it('throws exception with default message when no custom message provided', function () {
            $this->handler->setShouldFailOnError(true);

            expect(fn () => $this->handler->testExecuteRedisOperation(
                fn () => throw new \RedisException('Connection failed'),
                'default',
                'test operation',
                [],
                NonceValidationException::class
            ))->toThrow(NonceValidationException::class, 'Redis operation failed');
        });

        it('returns default when configured to fail but no exception class provided', function () {
            $this->handler->setShouldFailOnError(true);

            $result = $this->handler->testExecuteRedisOperation(
                fn () => throw new \RedisException('Connection failed'),
                'default_value',
                'test operation'
            );

            expect($result)->toBe('default_value');
        });

        it('logs error with context data', function () {
            $this->handler->setShouldFailOnError(false);

            // The logging happens internally, we just verify no exception is thrown
            $result = $this->handler->testExecuteRedisOperation(
                fn () => throw new \RedisException('Connection failed'),
                'default',
                'test operation',
                ['client_id' => 'test-client']
            );

            expect($result)->toBe('default');
        });

        it('preserves previous exception when throwing', function () {
            $this->handler->setShouldFailOnError(true);

            try {
                $this->handler->testExecuteRedisOperation(
                    fn () => throw new \RedisException('Original error'),
                    'default',
                    'test operation',
                    [],
                    NonceValidationException::class
                );
                $this->fail('Expected exception was not thrown');
            } catch (NonceValidationException $e) {
                expect($e->getPrevious())->toBeInstanceOf(\RedisException::class)
                    ->and($e->getPrevious()->getMessage())->toBe('Original error');
            }
        });
    });
});
