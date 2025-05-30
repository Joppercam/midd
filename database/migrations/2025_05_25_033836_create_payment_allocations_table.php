<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('tax_document_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2); // Monto asignado a este documento
            $table->text('notes')->nullable(); // Notas sobre la asignación
            $table->timestamps();

            $table->unique(['payment_id', 'tax_document_id']);
            $table->index('payment_id');
            $table->index('tax_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};