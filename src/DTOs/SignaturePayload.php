<?php

declare(strict_types=1);

namespace HmacAuth\DTOs;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * HMAC signature payload.
 */
final readonly class SignaturePayload
{
    public function __construct(
        public string $method,
        public string $path,
        public string $body,
        public string $timestamp,
        public string $nonce,
    ) {
        if ($method === '' || $path === '' || $timestamp === '' || $nonce === '') {
            throw new InvalidArgumentException('Payload fields cannot be empty');
        }
    }

    public static function fromRequest(Request $request, string $timestamp, string $nonce): self
    {
        return new self(
            method: strtoupper($request->method()),
            path: self::normalizePath($request->getPathInfo()),
            body: $request->getContent(),
            timestamp: $timestamp,
            nonce: $nonce,
        );
    }

    /**
     * Normalize a URL path to prevent signature bypass via path manipulation.
     *
     * Handles: duplicate slashes, trailing slashes, and ensures leading slash.
     */
    private static function normalizePath(string $path): string
    {
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        $trimmed = trim($path, '/');

        return $trimmed === '' ? '/' : '/'.$trimmed;
    }

    public function toCanonicalString(): string
    {
        return implode("\n", [
            $this->method,
            $this->path,
            $this->body,
            $this->timestamp,
            $this->nonce,
        ]);
    }

    public function getBodySizeInBytes(): int
    {
        return strlen($this->body);
    }
}
