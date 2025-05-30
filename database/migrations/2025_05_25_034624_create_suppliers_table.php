<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('rut'); // RUT del proveedor
            $table->string('name'); // Nombre/Razón social
            $table->enum('type', ['person', 'company'])->default('company');
            $table->string('business_name')->nullable(); // Giro comercial
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('commune')->nullable();
            $table->string('region')->nullable();
            $table->enum('payment_terms', [
                'immediate',    // Contado
                '15_days',      // 15 días
                '30_days',      // 30 días
                '60_days',      // 60 días
                '90_days'       // 90 días
            ])->default('30_days');
            $table->text('notes')->nullable(); // Notas adicionales
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'rut']);
            $table->unique(['tenant_id', 'rut']); // RUT único por tenant
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};