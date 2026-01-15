<?php

declare(strict_types=1);

use HmacAuth\Concerns\SanitizesForLogging;

// Test class that uses the trait
class SanitizesForLoggingTestClass
{
    use SanitizesForLogging;

    public function testSanitizeForLog(string $value, int $maxLength = 20): string
    {
        return $this->sanitizeForLog($value, $maxLength);
    }

    public function testMaskSensitiveValue(string $value, int $visibleChars = 8): string
    {
        return $this->maskSensitiveValue($value, $visibleChars);
    }
}

describe('SanitizesForLogging', function () {
    beforeEach(function () {
        $this->sanitizer = new SanitizesForLoggingTestClass;
    });

    describe('sanitizeForLog', function () {
        it('sanitizes value and adds ellipsis', function () {
            $result = $this->sanitizer->testSanitizeForLog('test-value_123');

            expect($result)->toBe('test-value_123...');
        });

        it('removes special characters', function () {
            $result = $this->sanitizer->testSanitizeForLog('test@value#with$special%chars');

            // The default maxLength is 20, so it truncates first then sanitizes
            // After sanitizing special chars: "testvaluewithspec" (17 chars from first 20)
            expect($result)->toEndWith('...');
            expect($result)->toContain('testvalue');
        });

        it('respects max length', function () {
            $result = $this->sanitizer->testSanitizeForLog('abcdefghijklmnopqrstuvwxyz', 10);

            expect($result)->toBe('abcdefghij...');
        });

        it('handles empty string', function () {
            $result = $this->sanitizer->testSanitizeForLog('');

            expect($result)->toBe('...');
        });

        it('handles string shorter than max length', function () {
            $result = $this->sanitizer->testSanitizeForLog('short', 20);

            expect($result)->toBe('short...');
        });

        it('removes newlines and tabs', function () {
            $result = $this->sanitizer->testSanitizeForLog("value\nwith\ttabs");

            // Newlines and tabs are removed as non-alphanumeric
            expect($result)->toBe('valuewithtabs...');
        });
    });

    describe('maskSensitiveValue', function () {
        it('masks value showing first 8 characters by default', function () {
            $result = $this->sanitizer->testMaskSensitiveValue('secret-key-12345678');

            expect($result)->toBe('secret-k...');
        });

        it('masks entire value when shorter than visible chars', function () {
            $result = $this->sanitizer->testMaskSensitiveValue('short');

            expect($result)->toBe('*****');
        });

        it('masks value exactly at visible chars length', function () {
            $result = $this->sanitizer->testMaskSensitiveValue('12345678');

            expect($result)->toBe('********');
        });

        it('respects custom visible chars', function () {
            $result = $this->sanitizer->testMaskSensitiveValue('this-is-a-secret', 4);

            expect($result)->toBe('this...');
        });

        it('masks entire value when visible chars is zero', function () {
            $result = $this->sanitizer->testMaskSensitiveValue('secret', 0);

            // When 0 visible chars and string is longer than 0, it returns ...
            expect($result)->toBe('...');
        });
    });
});
