<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * URL-safe Base64 encoding/decoding without padding.
 */
trait EncodesBase64Url
{
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded !== false ? $decoded : '';
    }
}
