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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_credential_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('client_id')->nullable();
            $table->string('request_method', 10);
            $table->string('request_path', 500);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->integer('response_status')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['signature_valid', 'created_at']);
            $table->index(['ip_address', 'signature_valid', 'created_at']);

            $table->foreign('api_credential_id')
                ->references('id')
                ->on('api_credentials')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
