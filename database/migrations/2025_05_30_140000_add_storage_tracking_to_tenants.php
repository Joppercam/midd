<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add storage fields to tenants table
        Schema::table('tenants', function (Blueprint $table) {
            $table->bigInteger('storage_used')->default(0)->comment('Storage used in bytes');
            $table->bigInteger('max_storage')->default(5368709120)->comment('Maximum storage allowed in bytes (5GB default)');
            $table->timestamp('storage_last_calculated_at')->nullable();
        });

        // Create storage usage tracking table
        Schema::create('tenant_storage_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('file_type')->nullable(); // 'image', 'document', 'attachment', etc.
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size'); // Size in bytes
            $table->string('uploaded_by_type')->nullable(); // Model type (User, Customer, etc.)
            $table->uuid('uploaded_by_id')->nullable(); // Model ID
            $table->string('related_model_type')->nullable(); // What model this file belongs to
            $table->uuid('related_model_id')->nullable(); // Related model ID
            $table->json('metadata')->nullable(); // Additional file metadata
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_deleted']);
            $table->index(['file_type']);
            $table->index(['related_model_type', 'related_model_id']);
            $table->index(['uploaded_by_type', 'uploaded_by_id']);
        });

        // Create storage quotas by plan table
        Schema::create('storage_quotas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_plan_id');
            $table->bigInteger('storage_limit'); // Storage limit in bytes
            $table->bigInteger('file_upload_limit')->nullable(); // Max file size in bytes
            $table->json('allowed_file_types')->nullable(); // Array of allowed mime types
            $table->integer('max_files_per_month')->nullable(); // Monthly upload limit
            $table->timestamps();

            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
        });

        // Create storage cleanup jobs table
        Schema::create('storage_cleanup_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->enum('type', ['cleanup_temp', 'compress_images', 'archive_old', 'delete_unused']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->json('parameters')->nullable(); // Job-specific parameters
            $table->bigInteger('bytes_processed')->default(0);
            $table->bigInteger('bytes_freed')->default(0);
            $table->integer('files_processed')->default(0);
            $table->integer('files_deleted')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_cleanup_jobs');
        Schema::dropIfExists('storage_quotas');
        Schema::dropIfExists('tenant_storage_usage');
        
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['storage_used', 'max_storage', 'storage_last_calculated_at']);
        });
    }
};