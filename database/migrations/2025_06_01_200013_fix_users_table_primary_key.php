<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table to add primary key constraint
        // First, disable foreign key checks
        DB::statement('PRAGMA foreign_keys=OFF');
        
        // Create a temporary table with the correct structure
        Schema::create('users_temp', function (Blueprint $table) {
            $table->id(); // This creates bigint unsigned auto increment primary key
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->string('tenant_id')->nullable();
            $table->string('role')->default('employee');
            $table->json('custom_permissions')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_enabled_at')->nullable();
            $table->boolean('force_two_factor')->default(false);
            $table->boolean('is_demo_user')->default(false);
            $table->string('demo_session_id')->nullable();
            $table->timestamp('demo_expires_at')->nullable();
            $table->integer('demo_extensions')->default(0);
            
            $table->index('tenant_id');
            $table->index('email');
        });
        
        // Copy data from old table to new table
        DB::statement('INSERT INTO users_temp (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, tenant_id, role, custom_permissions, last_login_at, two_factor_enabled, two_factor_secret, two_factor_recovery_codes, two_factor_enabled_at, force_two_factor, is_demo_user, demo_session_id, demo_expires_at, demo_extensions) 
                      SELECT id, name, email, email_verified_at, password, remember_token, created_at, updated_at, tenant_id, role, custom_permissions, last_login_at, two_factor_enabled, two_factor_secret, two_factor_recovery_codes, two_factor_enabled_at, force_two_factor, is_demo_user, demo_session_id, demo_expires_at, demo_extensions 
                      FROM users');
        
        // Drop the old table
        Schema::drop('users');
        
        // Rename the temporary table
        DB::statement('ALTER TABLE users_temp RENAME TO users');
        
        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys=ON');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed due to the nature of SQLite table recreation
        // The original table structure would need to be recreated manually if needed
    }
};
