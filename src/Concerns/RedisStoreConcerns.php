<?php

declare(strict_types=1);

namespace HmacAuth\Concerns;

/**
 * Aggregate trait for Redis-based storage services.
 *
 * Combines: HandlesRedisFailures, SanitizesForLogging
 */
trait RedisStoreConcerns
{
    use HandlesRedisFailures;
    use SanitizesForLogging;
}
