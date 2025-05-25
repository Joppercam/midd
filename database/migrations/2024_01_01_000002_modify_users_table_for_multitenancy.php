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
        Schema::table('users', function (Blueprint $table) {
            // For SQLite compatibility, we keep the id as is and add tenant fields
            $table->uuid('tenant_id')->after('id')->nullable();
            $table->string('role')->default('user')->after('email');
            $table->json('permissions')->nullable()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('updated_at');
            
            // Add indexes
            $table->index('tenant_id');
            $table->index('role');
            $table->index(['tenant_id', 'email']);
        });

        // Check if sessions table exists before modifying
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                // Add tenant_id to sessions if needed
                if (!Schema::hasColumn('sessions', 'tenant_id')) {
                    $table->uuid('tenant_id')->nullable()->after('user_id');
                    $table->index('tenant_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'tenant_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'email']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['tenant_id', 'role', 'permissions', 'last_login_at']);
        });
    }
};