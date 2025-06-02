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
            $table->integer('api_rate_limit')->default(1000);
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('enable_webhooks')->default(false);
            $table->boolean('api_documentation_public')->default(false);
            
            $table->index('enable_webhooks');
            $table->index('api_documentation_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'api_rate_limit',
                'webhook_url',
                'webhook_secret',
                'enable_webhooks',
                'api_documentation_public'
            ]);
        });
    }
};