<?php

declare(strict_types=1);

use HmacAuth\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()->extend()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeValidHmacSignature', function () {
    return $this->toBeString()
        ->and(strlen($this->value))->toBeGreaterThan(0)
        ->and($this->value)->not->toContain('+')
        ->and($this->value)->not->toContain('/')
        ->and($this->value)->not->toContain('=');
});

expect()->extend('toBeValidClientId', function (string $prefix = 'hmac') {
    return $this->toBeString()
        ->and($this->value)->toStartWith($prefix.'_');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createValidHmacHeaders(
    string $clientId,
    string $secret,
    string $method,
    string $path,
    string $body = '',
    string $algorithm = 'sha256'
): array {
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));

    $payload = implode("\n", [
        $method,
        $path,
        $timestamp,
        $nonce,
        $body,
    ]);

    $signature = base64_encode(hash_hmac($algorithm, $payload, $secret, true));
    // Convert to Base64URL
    $signature = str_replace(['+', '/', '='], ['-', '_', ''], $signature);

    return [
        'X-Api-Key' => $clientId,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'X-Nonce' => $nonce,
    ];
}

function generateTestSecret(int $length = 48): string
{
    $bytes = random_bytes($length);

    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($bytes));
}

function generateTestClientId(string $prefix = 'hmac'): string
{
    return $prefix.'_'.bin2hex(random_bytes(16));
}
