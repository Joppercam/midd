<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de módulos disponibles en el sistema
        Schema::create('system_modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 'invoicing', 'hrm', 'crm', etc.
            $table->string('name');
            $table->text('description');
            $table->string('version');
            $table->string('category'); // 'finance', 'sales', 'operations', etc.
            $table->json('dependencies')->nullable(); // ['core', 'invoicing']
            $table->json('settings_schema')->nullable(); // Configuración específica del módulo
            $table->boolean('is_core')->default(false); // Módulos que no se pueden desactivar
            $table->boolean('is_active')->default(true); // Disponible para activación
            $table->decimal('base_price', 10, 2)->default(0); // Precio mensual base
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable(); // Lista de características del módulo
            $table->json('permissions')->nullable(); // Permisos que otorga el módulo
            $table->timestamps();
        });

        // Tabla de planes/paquetes
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // 'starter', 'professional', 'enterprise'
            $table->text('description');
            $table->decimal('monthly_price', 10, 2);
            $table->decimal('annual_price', 10, 2);
            $table->json('included_modules'); // ['core', 'invoicing', 'inventory']
            $table->json('limits'); // {'users': 5, 'documents': 1000, 'storage': '10GB'}
            $table->json('features'); // ['support_email', 'api_access', 'white_label']
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('trial_days')->default(14);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Módulos activos por tenant
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained('system_modules')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Para módulos con período de prueba
            $table->json('settings')->nullable(); // Configuración específica del tenant
            $table->json('usage_stats')->nullable(); // Estadísticas de uso
            $table->decimal('custom_price', 10, 2)->nullable(); // Precio personalizado
            $table->string('billing_cycle')->default('monthly'); // 'monthly', 'annual', 'one_time'
            $table->timestamps();
            
            $table->unique(['tenant_id', 'module_id']);
            $table->index(['tenant_id', 'is_enabled']);
        });

        // Suscripciones de tenants
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans')->onDelete('restrict');
            $table->string('status'); // 'active', 'trial', 'suspended', 'cancelled'
            $table->timestamp('started_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->json('custom_modules')->nullable(); // Módulos adicionales al plan
            $table->json('custom_limits')->nullable(); // Límites personalizados
            $table->decimal('monthly_amount', 10, 2);
            $table->string('billing_cycle')->default('monthly');
            $table->string('payment_method')->nullable();
            $table->json('billing_info')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
        });

        // Registro de uso de módulos
        Schema::create('module_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained('system_modules')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // 'access', 'create', 'update', 'delete', 'export'
            $table->string('entity')->nullable(); // 'invoice', 'employee', 'product'
            $table->integer('count')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamp('logged_at');
            $table->index(['tenant_id', 'module_id', 'logged_at']);
        });

        // Solicitudes de módulos
        Schema::create('module_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained('system_modules')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->text('reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Configuración de módulos por categoría de negocio
        Schema::create('business_type_modules', function (Blueprint $table) {
            $table->id();
            $table->string('business_type'); // 'retail', 'services', 'manufacturing', 'wholesale'
            $table->string('business_size'); // 'micro', 'small', 'medium', 'large'
            $table->json('recommended_modules'); // Módulos recomendados
            $table->json('essential_modules'); // Módulos esenciales
            $table->json('optional_modules'); // Módulos opcionales
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_modules');
        Schema::dropIfExists('module_requests');
        Schema::dropIfExists('module_usage_logs');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('system_modules');
    }
};