<?php

declare(strict_types=1);

use HmacAuth\Enums\HmacAlgorithm;

describe('HmacAlgorithm', function () {
    describe('cases', function () {
        it('has SHA256 case', function () {
            expect(HmacAlgorithm::SHA256->value)->toBe('sha256');
        });

        it('has SHA384 case', function () {
            expect(HmacAlgorithm::SHA384->value)->toBe('sha384');
        });

        it('has SHA512 case', function () {
            expect(HmacAlgorithm::SHA512->value)->toBe('sha512');
        });

        it('has exactly 3 cases', function () {
            expect(HmacAlgorithm::cases())->toHaveCount(3);
        });
    });

    describe('getHashLength()', function () {
        it('returns 32 for SHA256', function () {
            expect(HmacAlgorithm::SHA256->getHashLength())->toBe(32);
        });

        it('returns 48 for SHA384', function () {
            expect(HmacAlgorithm::SHA384->getHashLength())->toBe(48);
        });

        it('returns 64 for SHA512', function () {
            expect(HmacAlgorithm::SHA512->getHashLength())->toBe(64);
        });
    });

    describe('tryFromString()', function () {
        it('returns SHA256 for "sha256"', function () {
            expect(HmacAlgorithm::tryFromString('sha256'))->toBe(HmacAlgorithm::SHA256);
        });

        it('returns SHA384 for "sha384"', function () {
            expect(HmacAlgorithm::tryFromString('sha384'))->toBe(HmacAlgorithm::SHA384);
        });

        it('returns SHA512 for "sha512"', function () {
            expect(HmacAlgorithm::tryFromString('sha512'))->toBe(HmacAlgorithm::SHA512);
        });

        it('handles uppercase input', function () {
            expect(HmacAlgorithm::tryFromString('SHA256'))->toBe(HmacAlgorithm::SHA256);
            expect(HmacAlgorithm::tryFromString('SHA384'))->toBe(HmacAlgorithm::SHA384);
            expect(HmacAlgorithm::tryFromString('SHA512'))->toBe(HmacAlgorithm::SHA512);
        });

        it('handles mixed case input', function () {
            expect(HmacAlgorithm::tryFromString('Sha256'))->toBe(HmacAlgorithm::SHA256);
            expect(HmacAlgorithm::tryFromString('ShA384'))->toBe(HmacAlgorithm::SHA384);
        });

        it('returns null for invalid algorithm', function () {
            expect(HmacAlgorithm::tryFromString('md5'))->toBeNull();
            expect(HmacAlgorithm::tryFromString('sha1'))->toBeNull();
            expect(HmacAlgorithm::tryFromString('invalid'))->toBeNull();
            expect(HmacAlgorithm::tryFromString(''))->toBeNull();
        });
    });

    describe('default()', function () {
        it('returns SHA256', function () {
            expect(HmacAlgorithm::default())->toBe(HmacAlgorithm::SHA256);
        });
    });

    describe('supportedNames()', function () {
        it('returns array of algorithm names', function () {
            $names = HmacAlgorithm::supportedNames();

            expect($names)->toBeArray()
                ->and($names)->toContain('sha256')
                ->and($names)->toContain('sha384')
                ->and($names)->toContain('sha512');
        });

        it('returns exactly 3 names', function () {
            expect(HmacAlgorithm::supportedNames())->toHaveCount(3);
        });

        it('returns lowercase names', function () {
            $names = HmacAlgorithm::supportedNames();

            foreach ($names as $name) {
                expect($name)->toBe(strtolower($name));
            }
        });
    });
});
