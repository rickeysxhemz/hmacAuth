<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test migration to add tenant_id column for multi-tenant testing.
 * This simulates what a package consumer would do when enabling tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_request_logs')) {
            Schema::table('api_request_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('client_id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('api_request_logs')) {
            Schema::table('api_request_logs', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
