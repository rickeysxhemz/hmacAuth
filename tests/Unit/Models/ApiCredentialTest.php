<?php

declare(strict_types=1);

use HmacAuth\Models\ApiCredential;

describe('ApiCredential', function () {
    describe('constants', function () {
        it('has production environment constant', function () {
            expect(ApiCredential::ENVIRONMENT_PRODUCTION)->toBe('production');
        });

        it('has testing environment constant', function () {
            expect(ApiCredential::ENVIRONMENT_TESTING)->toBe('testing');
        });

        it('has valid environments array', function () {
            expect(ApiCredential::VALID_ENVIRONMENTS)->toBe(['production', 'testing']);
        });
    });

    describe('isValidEnvironment()', function () {
        it('returns true for production', function () {
            expect(ApiCredential::isValidEnvironment('production'))->toBeTrue();
        });

        it('returns true for testing', function () {
            expect(ApiCredential::isValidEnvironment('testing'))->toBeTrue();
        });

        it('returns false for invalid environment', function () {
            expect(ApiCredential::isValidEnvironment('development'))->toBeFalse();
            expect(ApiCredential::isValidEnvironment('staging'))->toBeFalse();
            expect(ApiCredential::isValidEnvironment(''))->toBeFalse();
        });
    });

    describe('encryption', function () {
        it('encrypts client_secret on save', function () {
            $credential = new ApiCredential;
            $plainSecret = 'my-plain-secret-12345';

            $credential->client_secret = $plainSecret;

            // The raw attribute should be encrypted (different from plain)
            $rawValue = $credential->getAttributes()['client_secret'];
            expect($rawValue)->not->toBe($plainSecret);
        });

        it('decrypts client_secret on get', function () {
            $credential = new ApiCredential;
            $plainSecret = 'my-plain-secret-12345';

            $credential->client_secret = $plainSecret;

            // Getting the attribute should return decrypted value
            expect($credential->client_secret)->toBe($plainSecret);
        });

        it('encrypts old_client_secret on save', function () {
            $credential = new ApiCredential;
            $plainSecret = 'old-secret-12345';

            $credential->old_client_secret = $plainSecret;

            $rawValue = $credential->getAttributes()['old_client_secret'];
            expect($rawValue)->not->toBe($plainSecret);
        });

        it('handles null old_client_secret', function () {
            $credential = new ApiCredential;
            $credential->old_client_secret = null;

            expect($credential->old_client_secret)->toBeNull();
        });
    });

    describe('matchesEnvironment()', function () {
        it('matches production credential with production app env', function () {
            $credential = new ApiCredential;
            $credential->environment = 'production';

            expect($credential->matchesEnvironment('production'))->toBeTrue();
        });

        it('matches testing credential with local app env', function () {
            $credential = new ApiCredential;
            $credential->environment = 'testing';

            expect($credential->matchesEnvironment('local'))->toBeTrue();
        });

        it('matches testing credential with staging app env', function () {
            $credential = new ApiCredential;
            $credential->environment = 'testing';

            expect($credential->matchesEnvironment('staging'))->toBeTrue();
        });

        it('does not match production credential with local app env', function () {
            $credential = new ApiCredential;
            $credential->environment = 'production';

            expect($credential->matchesEnvironment('local'))->toBeFalse();
        });

        it('does not match testing credential with production app env', function () {
            $credential = new ApiCredential;
            $credential->environment = 'testing';

            expect($credential->matchesEnvironment('production'))->toBeFalse();
        });
    });

    describe('isProduction()', function () {
        it('returns true for production environment', function () {
            $credential = new ApiCredential;
            $credential->environment = 'production';

            expect($credential->isProduction())->toBeTrue();
        });

        it('returns false for testing environment', function () {
            $credential = new ApiCredential;
            $credential->environment = 'testing';

            expect($credential->isProduction())->toBeFalse();
        });
    });

    describe('isTesting()', function () {
        it('returns true for testing environment', function () {
            $credential = new ApiCredential;
            $credential->environment = 'testing';

            expect($credential->isTesting())->toBeTrue();
        });

        it('returns false for production environment', function () {
            $credential = new ApiCredential;
            $credential->environment = 'production';

            expect($credential->isTesting())->toBeFalse();
        });
    });

    describe('isExpired()', function () {
        it('returns false when expires_at is null', function () {
            $credential = new ApiCredential;
            $credential->expires_at = null;

            expect($credential->isExpired())->toBeFalse();
        });

        it('returns true when expires_at is in the past', function () {
            $credential = new ApiCredential;
            $credential->expires_at = now()->subDay();

            expect($credential->isExpired())->toBeTrue();
        });

        it('returns false when expires_at is in the future', function () {
            $credential = new ApiCredential;
            $credential->expires_at = now()->addDay();

            expect($credential->isExpired())->toBeFalse();
        });
    });

    describe('isValid()', function () {
        it('returns true when active and not expired', function () {
            $credential = new ApiCredential;
            $credential->is_active = true;
            $credential->expires_at = null;

            expect($credential->isValid())->toBeTrue();
        });

        it('returns false when inactive', function () {
            $credential = new ApiCredential;
            $credential->is_active = false;
            $credential->expires_at = null;

            expect($credential->isValid())->toBeFalse();
        });

        it('returns false when expired', function () {
            $credential = new ApiCredential;
            $credential->is_active = true;
            $credential->expires_at = now()->subDay();

            expect($credential->isValid())->toBeFalse();
        });

        it('returns true when active and not yet expired', function () {
            $credential = new ApiCredential;
            $credential->is_active = true;
            $credential->expires_at = now()->addDay();

            expect($credential->isValid())->toBeTrue();
        });
    });

    describe('verifySecret()', function () {
        it('returns true for matching current secret', function () {
            $credential = new ApiCredential;
            $secret = 'test-secret-12345';
            $credential->client_secret = $secret;

            expect($credential->verifySecret($secret))->toBeTrue();
        });

        it('returns false for non-matching secret', function () {
            $credential = new ApiCredential;
            $credential->client_secret = 'correct-secret';

            expect($credential->verifySecret('wrong-secret'))->toBeFalse();
        });

        it('returns true for valid old secret during rotation', function () {
            $credential = new ApiCredential;
            $credential->client_secret = 'new-secret';
            $credential->old_client_secret = 'old-secret';
            $credential->old_secret_expires_at = now()->addDays(7);

            expect($credential->verifySecret('old-secret'))->toBeTrue();
        });

        it('returns false for old secret after rotation period', function () {
            $credential = new ApiCredential;
            $credential->client_secret = 'new-secret';
            $credential->old_client_secret = 'old-secret';
            $credential->old_secret_expires_at = now()->subDay();

            expect($credential->verifySecret('old-secret'))->toBeFalse();
        });

        it('returns false when client_secret is null', function () {
            $credential = new ApiCredential;
            // Don't set client_secret

            expect($credential->verifySecret('any-secret'))->toBeFalse();
        });
    });

    describe('casts', function () {
        it('casts is_active to boolean', function () {
            $credential = new ApiCredential;
            $credential->is_active = 1;

            expect($credential->is_active)->toBeTrue();

            $credential->is_active = 0;
            expect($credential->is_active)->toBeFalse();
        });

        it('casts expires_at to datetime', function () {
            $credential = new ApiCredential;
            $credential->expires_at = '2024-12-31 23:59:59';

            expect($credential->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('hidden attributes', function () {
        it('hides client_secret in array', function () {
            $credential = new ApiCredential;
            $credential->client_id = 'test-client';
            $credential->client_secret = 'secret';

            $array = $credential->toArray();

            expect($array)->not->toHaveKey('client_secret');
        });

        it('hides old_client_secret in array', function () {
            $credential = new ApiCredential;
            $credential->client_id = 'test-client';
            $credential->old_client_secret = 'old-secret';

            $array = $credential->toArray();

            expect($array)->not->toHaveKey('old_client_secret');
        });

    });

    describe('hidden attributes (tenancy mode)', function () {
        beforeEach(function () {
            config(['hmac.tenancy.enabled' => true]);
            config(['hmac.tenancy.column' => 'tenant_id']);
        });

        afterEach(function () {
            config(['hmac.tenancy.enabled' => false]);
        });

        it('hides tenant_id in array when tenancy enabled', function () {
            $credential = new ApiCredential;
            $credential->tenant_id = 123;
            $credential->client_id = 'test-client';

            $array = $credential->toArray();

            expect($array)->not->toHaveKey('tenant_id');
        });
    });
});
