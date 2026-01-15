<?php

declare(strict_types=1);

use HmacAuth\Exceptions\NonceValidationException;

describe('NonceValidationException', function () {
    it('creates exception with default message', function () {
        $exception = new NonceValidationException;

        expect($exception)->toBeInstanceOf(NonceValidationException::class)
            ->and($exception->getMessage())->toBe('Nonce validation failed')
            ->and($exception->getCode())->toBe(0);
    });

    it('creates exception with custom message', function () {
        $exception = new NonceValidationException('Custom nonce error message');

        expect($exception->getMessage())->toBe('Custom nonce error message');
    });

    it('creates exception with custom code', function () {
        $exception = new NonceValidationException('Error', 500);

        expect($exception->getCode())->toBe(500);
    });

    it('creates exception with previous exception', function () {
        $previous = new RuntimeException('Previous error');
        $exception = new NonceValidationException('Error', 0, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('extends RuntimeException', function () {
        $exception = new NonceValidationException;

        expect($exception)->toBeInstanceOf(RuntimeException::class);
    });
});
