<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // Número de pago interno
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->date('payment_date'); // Fecha del pago
            $table->decimal('amount', 15, 2); // Monto del pago
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'check',
                'credit_card',
                'debit_card',
                'electronic',
                'other'
            ])->default('cash');
            $table->string('reference')->nullable(); // Número de transferencia, cheque, etc.
            $table->string('bank')->nullable(); // Banco (para transferencias y cheques)
            $table->text('description')->nullable(); // Descripción del pago
            $table->enum('status', [
                'pending',    // Pendiente (ej: cheque a fecha)
                'confirmed',  // Confirmado/Cobrado
                'rejected',   // Rechazado (ej: cheque sin fondos)
                'cancelled'   // Cancelado
            ])->default('confirmed');
            $table->decimal('remaining_amount', 15, 2)->default(0); // Monto no asignado
            $table->json('metadata')->nullable(); // Datos adicionales
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'payment_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};