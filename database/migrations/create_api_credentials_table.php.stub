<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('client_id')->unique();
            $table->text('client_secret');
            $table->string('hmac_algorithm')->default('sha256');
            $table->string('environment')->default('testing');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('old_client_secret')->nullable();
            $table->timestamp('old_secret_expires_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index('client_id');
            $table->index('environment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
