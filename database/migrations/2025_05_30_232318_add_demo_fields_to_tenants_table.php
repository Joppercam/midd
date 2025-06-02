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
            $table->boolean('is_demo')->default(false);
            $table->timestamp('demo_expires_at')->nullable();
            $table->integer('demo_sessions_count')->default(0);
            $table->timestamp('last_demo_reset')->nullable();
            
            $table->index(['is_demo', 'demo_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'is_demo',
                'demo_expires_at',
                'demo_sessions_count',
                'last_demo_reset'
            ]);
        });
    }
};
