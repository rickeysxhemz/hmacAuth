<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * Aggregate trait for cached repository operations.
 *
 * Combines: GeneratesCacheKeys, SanitizesForLogging
 */
trait CacheRepositoryConcerns
{
    use GeneratesCacheKeys;
    use SanitizesForLogging;
}
