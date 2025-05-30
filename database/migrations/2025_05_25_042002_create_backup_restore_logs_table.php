<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_restore_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('restored_by')->constrained('users');
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('restore_type'); // full, tables_only, files_only
            $table->json('restored_tables')->nullable();
            $table->json('restored_files')->nullable();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['backup_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restore_logs');
    }
};