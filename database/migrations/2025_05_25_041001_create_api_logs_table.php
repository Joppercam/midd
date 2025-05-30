<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('method', 10);
            $table->string('endpoint');
            $table->integer('status_code');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('response_time'); // in milliseconds
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['api_token_id', 'created_at']);
            $table->index('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};