<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_name')->nullable(); // Store user name in case user is deleted
            $table->string('user_email')->nullable();
            $table->string('event'); // created, updated, deleted, restored, etc.
            $table->morphs('auditable'); // Polymorphic relation
            $table->string('auditable_name')->nullable(); // Human-readable name
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable(); // List of changed fields
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('tags')->nullable(); // Additional tags for filtering
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('created_at');

            // Los índices de performance se agregan en 2025_05_27_100000_add_performance_indexes.php
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};