<?php

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
        Schema::create('tenant_usage_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('metric_type'); // users_count, invoices_count, storage_mb, api_calls, etc.
            $table->decimal('value', 20, 2);
            $table->json('additional_data')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'date', 'metric_type']);
            $table->index(['tenant_id', 'date']);
            $table->index('metric_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_statistics');
    }
};