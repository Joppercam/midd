<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('frequency'); // daily, weekly, monthly
            $table->time('time')->default('03:00:00');
            $table->string('day_of_week')->nullable(); // for weekly: 0-6 (Sunday-Saturday)
            $table->integer('day_of_month')->nullable(); // for monthly: 1-31
            $table->string('timezone')->default('America/Santiago');
            $table->json('included_tables')->nullable();
            $table->json('excluded_tables')->nullable();
            $table->boolean('include_files')->default(true);
            $table->string('disk')->default('local');
            $table->integer('retention_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('run_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->json('notification_settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};