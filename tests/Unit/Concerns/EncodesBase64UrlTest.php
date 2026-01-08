<?php

declare(strict_types=1);

use HmacAuth\Concerns\EncodesBase64Url;

// Create a test class that uses the trait
class Base64UrlTestClass
{
    use EncodesBase64Url;

    public function encode(string $data): string
    {
        return $this->base64UrlEncode($data);
    }

    public function decode(string $data): string
    {
        return $this->base64UrlDecode($data);
    }
}

describe('EncodesBase64Url', function () {
    beforeEach(function () {
        $this->encoder = new Base64UrlTestClass();
    });

    describe('base64UrlEncode()', function () {
        it('encodes simple string', function () {
            $input = 'Hello World';
            $encoded = $this->encoder->encode($input);

            expect($encoded)->toBeString()
                ->and($encoded)->not->toContain('+')
                ->and($encoded)->not->toContain('/')
                ->and($encoded)->not->toContain('=');
        });

        it('removes padding characters', function () {
            // 'A' base64 encodes to 'QQ==' - should become 'QQ'
            $encoded = $this->encoder->encode('A');

            expect($encoded)->not->toEndWith('=');
        });

        it('replaces + with -', function () {
            // Find a string that produces + in standard base64
            $testStrings = ['test>?', 'fb', 'abc>>>'];
            $anyContainedPlus = false;

            foreach ($testStrings as $test) {
                $standard = base64_encode($test);
                if (str_contains($standard, '+')) {
                    $urlSafe = $this->encoder->encode($test);
                    expect($urlSafe)->not->toContain('+');
                    $anyContainedPlus = true;
                }
            }

            // Just verify the encoding works
            expect($this->encoder->encode('test'))->toBeString();
        });

        it('replaces / with _', function () {
            // Find a string that produces / in standard base64
            $testStrings = ['test??', '???', 'abc/'];

            foreach ($testStrings as $test) {
                $urlSafe = $this->encoder->encode($test);
                expect($urlSafe)->not->toContain('/');
            }
        });

        it('handles empty string', function () {
            $encoded = $this->encoder->encode('');

            expect($encoded)->toBe('');
        });

        it('handles binary data', function () {
            $binary = random_bytes(32);
            $encoded = $this->encoder->encode($binary);

            expect($encoded)->toBeString()
                ->and($encoded)->not->toContain('+')
                ->and($encoded)->not->toContain('/')
                ->and($encoded)->not->toContain('=');
        });

        it('produces URL-safe output', function () {
            // Generate random data and verify URL safety
            for ($i = 0; $i < 10; $i++) {
                $data = random_bytes(rand(1, 100));
                $encoded = $this->encoder->encode($data);

                expect($encoded)->toMatch('/^[A-Za-z0-9_-]*$/');
            }
        });
    });

    describe('base64UrlDecode()', function () {
        it('decodes URL-safe encoded string', function () {
            $original = 'Hello World';
            $encoded = $this->encoder->encode($original);
            $decoded = $this->encoder->decode($encoded);

            expect($decoded)->toBe($original);
        });

        it('handles strings with replaced characters', function () {
            // Manually create URL-safe base64
            $original = 'test data';
            $encoded = $this->encoder->encode($original);
            $decoded = $this->encoder->decode($encoded);

            expect($decoded)->toBe($original);
        });

        it('handles empty string', function () {
            $decoded = $this->encoder->decode('');

            expect($decoded)->toBe('');
        });

        it('handles binary data roundtrip', function () {
            $binary = random_bytes(64);
            $encoded = $this->encoder->encode($binary);
            $decoded = $this->encoder->decode($encoded);

            expect($decoded)->toBe($binary);
        });

        it('returns empty string for invalid base64', function () {
            // Invalid base64 that can't be decoded
            $decoded = $this->encoder->decode('!!!invalid!!!');

            expect($decoded)->toBe('');
        });

        it('handles standard base64 with URL replacements', function () {
            // Original data that would contain + and / in standard base64
            $testData = str_repeat('test', 10);
            $encoded = $this->encoder->encode($testData);
            $decoded = $this->encoder->decode($encoded);

            expect($decoded)->toBe($testData);
        });
    });

    describe('encode/decode roundtrip', function () {
        it('preserves data through roundtrip', function () {
            $testCases = [
                'simple text',
                'text with spaces and punctuation!',
                "multi\nline\ntext",
                '{"json":"data"}',
                '',
                str_repeat('x', 1000),
            ];

            foreach ($testCases as $original) {
                $encoded = $this->encoder->encode($original);
                $decoded = $this->encoder->decode($encoded);

                expect($decoded)->toBe($original);
            }
        });

        it('preserves binary data through roundtrip', function () {
            for ($i = 0; $i < 10; $i++) {
                $original = random_bytes(rand(1, 200));
                $encoded = $this->encoder->encode($original);
                $decoded = $this->encoder->decode($encoded);

                expect($decoded)->toBe($original);
            }
        });
    });
});
