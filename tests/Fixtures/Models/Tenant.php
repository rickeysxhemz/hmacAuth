<?php

declare(strict_types=1);

namespace HmacAuth\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stub Tenant model for testing multi-tenant functionality.
 */
class Tenant extends Model
{
    protected $fillable = ['name'];
}
