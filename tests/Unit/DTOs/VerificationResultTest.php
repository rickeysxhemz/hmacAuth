<?php

declare(strict_types=1);

use HmacAuth\DTOs\VerificationResult;
use HmacAuth\Enums\VerificationFailureReason;
use HmacAuth\Models\ApiCredential;

describe('VerificationResult', function () {
    describe('success()', function () {
        it('creates successful result with credential', function () {
            $credential = Mockery::mock(ApiCredential::class);

            $result = VerificationResult::success($credential);

            expect($result->valid)->toBeTrue()
                ->and($result->credential)->toBe($credential)
                ->and($result->failureReason)->toBeNull();
        });
    });

    describe('failure()', function () {
        it('creates failure result with reason', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            expect($result->valid)->toBeFalse()
                ->and($result->credential)->toBeNull()
                ->and($result->failureReason)->toBe(VerificationFailureReason::INVALID_SIGNATURE);
        });

        it('creates failure for each reason type', function () {
            $reasons = VerificationFailureReason::cases();

            foreach ($reasons as $reason) {
                $result = VerificationResult::failure($reason);

                expect($result->valid)->toBeFalse()
                    ->and($result->failureReason)->toBe($reason);
            }
        });
    });

    describe('isValid()', function () {
        it('returns true for success result', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            expect($result->isValid())->toBeTrue();
        });

        it('returns false for failure result', function () {
            $result = VerificationResult::failure(VerificationFailureReason::MISSING_HEADERS);

            expect($result->isValid())->toBeFalse();
        });
    });

    describe('isFailure()', function () {
        it('returns false for success result', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            expect($result->isFailure())->toBeFalse();
        });

        it('returns true for failure result', function () {
            $result = VerificationResult::failure(VerificationFailureReason::RATE_LIMITED);

            expect($result->isFailure())->toBeTrue();
        });
    });

    describe('getCredential()', function () {
        it('returns credential for success result', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            expect($result->getCredential())->toBe($credential);
        });

        it('throws exception for failure result', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_CLIENT_ID);

            expect(fn () => $result->getCredential())
                ->toThrow(LogicException::class, 'Cannot get credential from failed verification result');
        });
    });

    describe('getErrorMessage()', function () {
        it('returns null for success result', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            expect($result->getErrorMessage())->toBeNull();
        });

        it('returns error message for failure result', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            expect($result->getErrorMessage())->toBe('Invalid signature');
        });

        it('returns correct message for each failure reason', function () {
            $result = VerificationResult::failure(VerificationFailureReason::MISSING_HEADERS);
            expect($result->getErrorMessage())->toBe('Missing required headers');

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_TIMESTAMP);
            expect($result->getErrorMessage())->toBe('Invalid or expired timestamp');

            $result = VerificationResult::failure(VerificationFailureReason::RATE_LIMITED);
            expect($result->getErrorMessage())->toBe('Rate limit exceeded');
        });
    });

    describe('getHttpStatus()', function () {
        it('returns 200 for success result', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            expect($result->getHttpStatus())->toBe(200);
        });

        it('returns 429 for rate limited', function () {
            $result = VerificationResult::failure(VerificationFailureReason::RATE_LIMITED);

            expect($result->getHttpStatus())->toBe(429);
        });

        it('returns 429 for IP blocked', function () {
            $result = VerificationResult::failure(VerificationFailureReason::IP_BLOCKED);

            expect($result->getHttpStatus())->toBe(429);
        });

        it('returns 413 for body too large', function () {
            $result = VerificationResult::failure(VerificationFailureReason::BODY_TOO_LARGE);

            expect($result->getHttpStatus())->toBe(413);
        });

        it('returns 401 for other failures', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);
            expect($result->getHttpStatus())->toBe(401);

            $result = VerificationResult::failure(VerificationFailureReason::INVALID_CLIENT_ID);
            expect($result->getHttpStatus())->toBe(401);

            $result = VerificationResult::failure(VerificationFailureReason::MISSING_HEADERS);
            expect($result->getHttpStatus())->toBe(401);
        });
    });

    describe('shouldIncrementRateLimit()', function () {
        it('returns false for success result', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            expect($result->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns true for invalid client ID', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_CLIENT_ID);

            expect($result->shouldIncrementRateLimit())->toBeTrue();
        });

        it('returns true for environment mismatch', function () {
            $result = VerificationResult::failure(VerificationFailureReason::ENVIRONMENT_MISMATCH);

            expect($result->shouldIncrementRateLimit())->toBeTrue();
        });

        it('returns true for invalid signature', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            expect($result->shouldIncrementRateLimit())->toBeTrue();
        });

        it('returns false for missing headers', function () {
            $result = VerificationResult::failure(VerificationFailureReason::MISSING_HEADERS);

            expect($result->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for rate limited', function () {
            $result = VerificationResult::failure(VerificationFailureReason::RATE_LIMITED);

            expect($result->shouldIncrementRateLimit())->toBeFalse();
        });
    });

    describe('toArray()', function () {
        it('returns array with valid true for success', function () {
            $credential = Mockery::mock(ApiCredential::class);
            $result = VerificationResult::success($credential);

            $array = $result->toArray();

            expect($array)->toBeArray()
                ->and($array['valid'])->toBeTrue()
                ->and($array['credential'])->toBe($credential)
                ->and($array['error'])->toBeNull();
        });

        it('returns array with valid false for failure', function () {
            $result = VerificationResult::failure(VerificationFailureReason::INVALID_SIGNATURE);

            $array = $result->toArray();

            expect($array)->toBeArray()
                ->and($array['valid'])->toBeFalse()
                ->and($array['credential'])->toBeNull()
                ->and($array['error'])->toBe('Invalid signature');
        });
    });
});

afterEach(function () {
    Mockery::close();
});
