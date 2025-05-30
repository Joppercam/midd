<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Solo agregar campos que no existen
            if (!Schema::hasColumn('tenants', 'business_activity')) {
                $table->string('business_activity')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'commune')) {
                $table->string('commune')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'branch_code')) {
                $table->string('branch_code')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'sii_environment')) {
                $table->string('sii_environment')->default('certification');
            }
            if (!Schema::hasColumn('tenants', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'features')) {
                $table->json('features')->nullable();
            }
            
            // Índices
            if (!Schema::hasIndex('tenants', 'tenants_tax_id_index')) {
                $table->index('tax_id');
            }
            if (!Schema::hasIndex('tenants', 'tenants_is_active_index')) {
                $table->index('is_active');
            }
            if (!Schema::hasIndex('tenants', 'tenants_plan_index')) {
                $table->index('plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'business_activity', 'commune', 'branch_code', 
                'sii_environment', 'last_activity_at', 'features'
            ]);
        });
    }
};