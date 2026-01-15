<?php

declare(strict_types=1);

use HmacAuth\Concerns\TruncatesStrings;

// Test class that uses the trait
class TruncatesStringsTestClass
{
    use TruncatesStrings;

    public function testTruncate(?string $value, int $maxLength, string $suffix = '...'): ?string
    {
        return $this->truncate($value, $maxLength, $suffix);
    }

    public function testTruncateUserAgent(?string $userAgent, int $maxLength = 500): ?string
    {
        return $this->truncateUserAgent($userAgent, $maxLength);
    }

    public function testTruncatePath(string $path, int $maxLength = 500): string
    {
        return $this->truncatePath($path, $maxLength);
    }
}

describe('TruncatesStrings', function () {
    beforeEach(function () {
        $this->truncator = new TruncatesStringsTestClass;
    });

    describe('truncate', function () {
        it('returns null for null input', function () {
            $result = $this->truncator->testTruncate(null, 100);

            expect($result)->toBeNull();
        });

        it('returns original value when maxLength is zero', function () {
            $result = $this->truncator->testTruncate('test', 0);

            expect($result)->toBe('test');
        });

        it('returns original value when maxLength is negative', function () {
            $result = $this->truncator->testTruncate('test', -5);

            expect($result)->toBe('test');
        });

        it('returns original value when shorter than max length', function () {
            $result = $this->truncator->testTruncate('short', 100);

            expect($result)->toBe('short');
        });

        it('returns original value when exactly at max length', function () {
            $result = $this->truncator->testTruncate('test', 4);

            expect($result)->toBe('test');
        });

        it('truncates value and adds suffix', function () {
            $result = $this->truncator->testTruncate('this is a long string', 10);

            expect($result)->toBe('this is...');
        });

        it('uses custom suffix', function () {
            $result = $this->truncator->testTruncate('this is a long string', 10, '…');

            expect($result)->toBe('this is a…');
        });

        it('handles UTF-8 characters correctly', function () {
            $result = $this->truncator->testTruncate('日本語テスト文字列', 5);

            expect($result)->toBe('日本...');
        });

        it('handles empty suffix', function () {
            $result = $this->truncator->testTruncate('this is a long string', 7, '');

            expect($result)->toBe('this is');
        });

        it('handles suffix longer than max length', function () {
            $result = $this->truncator->testTruncate('test', 2, '...');

            expect($result)->toBe('...');
        });
    });

    describe('truncateUserAgent', function () {
        it('truncates long user agent', function () {
            $longUserAgent = str_repeat('Mozilla/5.0 ', 100);
            $result = $this->truncator->testTruncateUserAgent($longUserAgent);

            expect(strlen($result))->toBeLessThanOrEqual(500);
        });

        it('returns null for null user agent', function () {
            $result = $this->truncator->testTruncateUserAgent(null);

            expect($result)->toBeNull();
        });

        it('returns original user agent when short', function () {
            $result = $this->truncator->testTruncateUserAgent('Mozilla/5.0');

            expect($result)->toBe('Mozilla/5.0');
        });

        it('respects custom max length', function () {
            $result = $this->truncator->testTruncateUserAgent('Mozilla/5.0 (Windows)', 10);

            expect($result)->toBe('Mozilla...');
        });
    });

    describe('truncatePath', function () {
        it('truncates long path', function () {
            $longPath = '/api/'.str_repeat('segment/', 100);
            $result = $this->truncator->testTruncatePath($longPath);

            expect(strlen($result))->toBeLessThanOrEqual(500);
        });

        it('returns original path when short', function () {
            $result = $this->truncator->testTruncatePath('/api/users');

            expect($result)->toBe('/api/users');
        });

        it('respects custom max length', function () {
            $result = $this->truncator->testTruncatePath('/api/very/long/path', 10);

            expect($result)->toBe('/api/ve...');
        });

        it('returns empty string for empty path', function () {
            $result = $this->truncator->testTruncatePath('');

            expect($result)->toBe('');
        });
    });
});
