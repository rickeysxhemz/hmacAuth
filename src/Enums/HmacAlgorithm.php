<?php

declare(strict_types=1);

namespace HmacAuth\Enums;

/**
 * Enum representing supported HMAC algorithms.
 */
enum HmacAlgorithm: string
{
    case SHA256 = 'sha256';
    case SHA384 = 'sha384';
    case SHA512 = 'sha512';

    /**
     * Get the hash output length in bytes.
     */
    public function getHashLength(): int
    {
        return match ($this) {
            self::SHA256 => 32,
            self::SHA384 => 48,
            self::SHA512 => 64,
        };
    }

    /**
     * Try to create from string, returns null if invalid.
     */
    public static function tryFromString(string $algorithm): ?self
    {
        return self::tryFrom(strtolower($algorithm));
    }

    /**
     * Get default algorithm.
     */
    public static function default(): self
    {
        return self::SHA256;
    }

    /**
     * Get all supported algorithm names.
     *
     * @return list<string>
     */
    public static function supportedNames(): array
    {
        return array_map(fn (self $algo) => $algo->value, self::cases());
    }
}
