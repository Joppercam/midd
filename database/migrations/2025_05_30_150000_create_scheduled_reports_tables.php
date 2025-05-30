<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Report templates
        Schema::create('report_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // sales, financial, inventory, customers, etc.
            $table->string('format'); // pdf, excel, csv
            $table->json('default_parameters')->nullable();
            $table->json('available_parameters')->nullable();
            $table->string('query_class'); // Class that generates the report data
            $table->string('view_template')->nullable(); // Blade template for PDF reports
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Scheduled reports
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id'); // Who created/owns this schedule
            $table->uuid('report_template_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('frequency'); // daily, weekly, monthly, quarterly, yearly
            $table->json('frequency_details')->nullable(); // day of week, month, etc.
            $table->json('parameters')->nullable(); // Report-specific parameters
            $table->string('format'); // pdf, excel, csv
            $table->json('recipients'); // Email addresses to send to
            $table->boolean('auto_send')->default(true);
            $table->boolean('store_file')->default(true);
            $table->string('storage_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('report_template_id')->references('id')->on('report_templates')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['next_run_at', 'is_active']);
        });

        // Report executions/history
        Schema::create('report_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('scheduled_report_id')->nullable(); // null for manual reports
            $table->uuid('report_template_id');
            $table->uuid('user_id'); // Who triggered the report
            $table->string('name');
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->json('parameters')->nullable();
            $table->string('format');
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('total_records')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('execution_time_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('was_emailed')->default(false);
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('scheduled_report_id')->references('id')->on('scheduled_reports')->onDelete('set null');
            $table->foreign('report_template_id')->references('id')->on('report_templates')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['scheduled_report_id']);
            $table->index(['created_at']);
        });

        // Report shares/permissions
        Schema::create('report_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('scheduled_report_id');
            $table->uuid('user_id')->nullable(); // Specific user
            $table->string('role')->nullable(); // Role-based access
            $table->string('email')->nullable(); // External email
            $table->enum('permission', ['view', 'edit', 'admin']);
            $table->boolean('receive_emails')->default(false);
            $table->timestamps();

            $table->foreign('scheduled_report_id')->references('id')->on('scheduled_reports')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Report filters/saved views
        Schema::create('report_filters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->uuid('report_template_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('report_template_id')->references('id')->on('report_templates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_filters');
        Schema::dropIfExists('report_shares');
        Schema::dropIfExists('report_executions');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_templates');
    }
};