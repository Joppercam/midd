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
            $table->boolean('is_demo_user')->default(false);
            $table->string('demo_session_id')->nullable();
            $table->timestamp('demo_expires_at')->nullable();
            $table->integer('demo_extensions')->default(0);
            
            $table->index(['is_demo_user', 'demo_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_demo_user',
                'demo_session_id', 
                'demo_expires_at',
                'demo_extensions'
            ]);
        });
    }
};
