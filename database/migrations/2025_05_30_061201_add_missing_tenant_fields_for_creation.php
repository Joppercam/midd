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
        Schema::table('tenants', function (Blueprint $table) {
            // Basic company information
            if (!Schema::hasColumn('tenants', 'legal_name')) {
                $table->string('legal_name')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'trade_name')) {
                $table->string('trade_name')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'phone')) {
                $table->string('phone')->nullable();
            }
            
            // Plan information
            if (!Schema::hasColumn('tenants', 'plan')) {
                $table->string('plan')->nullable()->after('subscription_plan');
            }
            
            // Subscription limits
            if (!Schema::hasColumn('tenants', 'max_users')) {
                $table->integer('max_users')->default(1);
            }
            if (!Schema::hasColumn('tenants', 'max_documents_per_month')) {
                $table->integer('max_documents_per_month')->default(100);
            }
            if (!Schema::hasColumn('tenants', 'max_products')) {
                $table->integer('max_products')->default(100);
            }
            if (!Schema::hasColumn('tenants', 'max_customers')) {
                $table->integer('max_customers')->default(100);
            }
            
            // Features
            if (!Schema::hasColumn('tenants', 'api_access')) {
                $table->boolean('api_access')->default(false);
            }
            if (!Schema::hasColumn('tenants', 'multi_branch')) {
                $table->boolean('multi_branch')->default(false);
            }
            if (!Schema::hasColumn('tenants', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            // Regional settings
            if (!Schema::hasColumn('tenants', 'currency')) {
                $table->string('currency')->default('CLP');
            }
            if (!Schema::hasColumn('tenants', 'timezone')) {
                $table->string('timezone')->default('America/Santiago');
            }
            if (!Schema::hasColumn('tenants', 'date_format')) {
                $table->string('date_format')->default('d/m/Y');
            }
            if (!Schema::hasColumn('tenants', 'time_format')) {
                $table->string('time_format')->default('H:i');
            }
            if (!Schema::hasColumn('tenants', 'fiscal_year_start_month')) {
                $table->integer('fiscal_year_start_month')->default(1);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'trade_name', 'email', 'phone', 'plan',
                'max_users', 'max_documents_per_month', 'max_products', 'max_customers',
                'api_access', 'multi_branch', 'is_active',
                'currency', 'timezone', 'date_format', 'time_format', 'fiscal_year_start_month'
            ]);
        });
    }
};
