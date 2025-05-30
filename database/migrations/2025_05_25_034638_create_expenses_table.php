<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // Número interno del gasto
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('document_type', [
                'invoice',          // Factura de compra
                'receipt',          // Boleta
                'expense_note',     // Nota de gasto
                'petty_cash',       // Caja chica
                'bank_charge',      // Cargo bancario
                'other'             // Otro
            ])->default('invoice');
            $table->string('supplier_document_number')->nullable(); // Número del documento del proveedor
            $table->date('issue_date'); // Fecha de emisión
            $table->date('due_date')->nullable(); // Fecha de vencimiento
            $table->decimal('net_amount', 15, 2); // Monto neto
            $table->decimal('tax_amount', 15, 2)->default(0); // IVA
            $table->decimal('other_taxes', 15, 2)->default(0); // Otros impuestos
            $table->decimal('total_amount', 15, 2); // Total
            $table->decimal('balance', 15, 2); // Saldo pendiente
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'check',
                'credit_card',
                'debit_card',
                'electronic',
                'credit_account',
                'other'
            ])->nullable();
            $table->enum('status', [
                'draft',        // Borrador
                'pending',      // Pendiente de pago
                'paid',         // Pagado
                'cancelled'     // Cancelado
            ])->default('pending');
            $table->string('category')->nullable(); // Categoría del gasto
            $table->text('description')->nullable(); // Descripción
            $table->string('reference')->nullable(); // Referencia de pago
            $table->json('metadata')->nullable(); // Datos adicionales
            $table->timestamps();

            $table->index(['tenant_id', 'issue_date']);
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'document_type']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};