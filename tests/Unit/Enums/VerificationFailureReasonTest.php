<?php

declare(strict_types=1);

use HmacAuth\Enums\VerificationFailureReason;

describe('VerificationFailureReason', function () {
    describe('cases', function () {
        it('has all expected cases', function () {
            $expectedCases = [
                'MISSING_HEADERS',
                'INVALID_TIMESTAMP',
                'BODY_TOO_LARGE',
                'IP_BLOCKED',
                'RATE_LIMITED',
                'INVALID_NONCE',
                'DUPLICATE_NONCE',
                'INVALID_CLIENT_ID',
                'CREDENTIAL_EXPIRED',
                'ENVIRONMENT_MISMATCH',
                'INVALID_SECRET',
                'INVALID_SIGNATURE',
            ];

            $actualCases = array_map(fn ($case) => $case->name, VerificationFailureReason::cases());

            expect($actualCases)->toBe($expectedCases);
        });

        it('has correct values for all cases', function () {
            expect(VerificationFailureReason::MISSING_HEADERS->value)->toBe('missing_headers');
            expect(VerificationFailureReason::INVALID_TIMESTAMP->value)->toBe('invalid_timestamp');
            expect(VerificationFailureReason::BODY_TOO_LARGE->value)->toBe('body_too_large');
            expect(VerificationFailureReason::IP_BLOCKED->value)->toBe('ip_blocked');
            expect(VerificationFailureReason::RATE_LIMITED->value)->toBe('rate_limited');
            expect(VerificationFailureReason::INVALID_NONCE->value)->toBe('invalid_nonce');
            expect(VerificationFailureReason::DUPLICATE_NONCE->value)->toBe('duplicate_nonce');
            expect(VerificationFailureReason::INVALID_CLIENT_ID->value)->toBe('invalid_client_id');
            expect(VerificationFailureReason::CREDENTIAL_EXPIRED->value)->toBe('credential_expired');
            expect(VerificationFailureReason::ENVIRONMENT_MISMATCH->value)->toBe('environment_mismatch');
            expect(VerificationFailureReason::INVALID_SECRET->value)->toBe('invalid_secret');
            expect(VerificationFailureReason::INVALID_SIGNATURE->value)->toBe('invalid_signature');
        });
    });

    describe('getMessage()', function () {
        it('returns correct message for MISSING_HEADERS', function () {
            expect(VerificationFailureReason::MISSING_HEADERS->getMessage())
                ->toBe('Missing required headers');
        });

        it('returns correct message for INVALID_TIMESTAMP', function () {
            expect(VerificationFailureReason::INVALID_TIMESTAMP->getMessage())
                ->toBe('Invalid or expired timestamp');
        });

        it('returns correct message for BODY_TOO_LARGE', function () {
            expect(VerificationFailureReason::BODY_TOO_LARGE->getMessage())
                ->toBe('Request body exceeds maximum size');
        });

        it('returns correct message for IP_BLOCKED', function () {
            expect(VerificationFailureReason::IP_BLOCKED->getMessage())
                ->toBe('Too many failed attempts from this IP');
        });

        it('returns correct message for RATE_LIMITED', function () {
            expect(VerificationFailureReason::RATE_LIMITED->getMessage())
                ->toBe('Rate limit exceeded');
        });

        it('returns correct message for INVALID_NONCE', function () {
            expect(VerificationFailureReason::INVALID_NONCE->getMessage())
                ->toBe('Nonce too short');
        });

        it('returns correct message for DUPLICATE_NONCE', function () {
            expect(VerificationFailureReason::DUPLICATE_NONCE->getMessage())
                ->toBe('Duplicate nonce detected');
        });

        it('returns correct message for INVALID_CLIENT_ID', function () {
            expect(VerificationFailureReason::INVALID_CLIENT_ID->getMessage())
                ->toBe('Invalid client ID');
        });

        it('returns correct message for CREDENTIAL_EXPIRED', function () {
            expect(VerificationFailureReason::CREDENTIAL_EXPIRED->getMessage())
                ->toBe('API credential has expired');
        });

        it('returns correct message for ENVIRONMENT_MISMATCH', function () {
            expect(VerificationFailureReason::ENVIRONMENT_MISMATCH->getMessage())
                ->toBe('Credential environment mismatch');
        });

        it('returns correct message for INVALID_SECRET', function () {
            expect(VerificationFailureReason::INVALID_SECRET->getMessage())
                ->toBe('Invalid client secret');
        });

        it('returns correct message for INVALID_SIGNATURE', function () {
            expect(VerificationFailureReason::INVALID_SIGNATURE->getMessage())
                ->toBe('Invalid signature');
        });

        it('returns non-empty message for all cases', function () {
            foreach (VerificationFailureReason::cases() as $reason) {
                expect($reason->getMessage())->toBeString()->not->toBeEmpty();
            }
        });
    });

    describe('getHttpStatus()', function () {
        it('returns 429 for RATE_LIMITED', function () {
            expect(VerificationFailureReason::RATE_LIMITED->getHttpStatus())->toBe(429);
        });

        it('returns 429 for IP_BLOCKED', function () {
            expect(VerificationFailureReason::IP_BLOCKED->getHttpStatus())->toBe(429);
        });

        it('returns 413 for BODY_TOO_LARGE', function () {
            expect(VerificationFailureReason::BODY_TOO_LARGE->getHttpStatus())->toBe(413);
        });

        it('returns 401 for authentication failures', function () {
            expect(VerificationFailureReason::MISSING_HEADERS->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::INVALID_TIMESTAMP->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::INVALID_NONCE->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::DUPLICATE_NONCE->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::INVALID_CLIENT_ID->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::CREDENTIAL_EXPIRED->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::ENVIRONMENT_MISMATCH->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::INVALID_SECRET->getHttpStatus())->toBe(401);
            expect(VerificationFailureReason::INVALID_SIGNATURE->getHttpStatus())->toBe(401);
        });
    });

    describe('shouldIncrementRateLimit()', function () {
        it('returns true for INVALID_CLIENT_ID', function () {
            expect(VerificationFailureReason::INVALID_CLIENT_ID->shouldIncrementRateLimit())->toBeTrue();
        });

        it('returns true for ENVIRONMENT_MISMATCH', function () {
            expect(VerificationFailureReason::ENVIRONMENT_MISMATCH->shouldIncrementRateLimit())->toBeTrue();
        });

        it('returns true for INVALID_SIGNATURE', function () {
            expect(VerificationFailureReason::INVALID_SIGNATURE->shouldIncrementRateLimit())->toBeTrue();
        });

        it('returns false for MISSING_HEADERS', function () {
            expect(VerificationFailureReason::MISSING_HEADERS->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for INVALID_TIMESTAMP', function () {
            expect(VerificationFailureReason::INVALID_TIMESTAMP->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for BODY_TOO_LARGE', function () {
            expect(VerificationFailureReason::BODY_TOO_LARGE->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for IP_BLOCKED', function () {
            expect(VerificationFailureReason::IP_BLOCKED->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for RATE_LIMITED', function () {
            expect(VerificationFailureReason::RATE_LIMITED->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for INVALID_NONCE', function () {
            expect(VerificationFailureReason::INVALID_NONCE->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for DUPLICATE_NONCE', function () {
            expect(VerificationFailureReason::DUPLICATE_NONCE->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for CREDENTIAL_EXPIRED', function () {
            expect(VerificationFailureReason::CREDENTIAL_EXPIRED->shouldIncrementRateLimit())->toBeFalse();
        });

        it('returns false for INVALID_SECRET', function () {
            expect(VerificationFailureReason::INVALID_SECRET->shouldIncrementRateLimit())->toBeFalse();
        });
    });
});
