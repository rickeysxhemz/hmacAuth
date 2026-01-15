<?php

declare(strict_types=1);

namespace HmacAuth\Contracts;

use HmacAuth\DTOs\HmacConfig;

/**
 * Interface for HmacConfig factory.
 */
interface HmacConfigFactoryInterface
{
    /**
     * Create an HmacConfig instance.
     */
    public function create(): HmacConfig;
}
