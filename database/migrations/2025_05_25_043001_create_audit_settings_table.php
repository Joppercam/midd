<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('model_class');
            $table->boolean('is_enabled')->default(true);
            $table->json('events')->nullable(); // Which events to track
            $table->json('excluded_fields')->nullable(); // Fields to exclude from tracking
            $table->json('masked_fields')->nullable(); // Fields to mask (passwords, etc)
            $table->integer('retention_days')->nullable(); // Auto-delete after X days
            $table->timestamps();

            $table->unique(['tenant_id', 'model_class']);
            $table->index(['tenant_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_settings');
    }
};