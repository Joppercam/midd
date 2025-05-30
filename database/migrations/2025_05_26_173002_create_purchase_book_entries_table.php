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
        Schema::create('purchase_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date');
            $table->string('document_type', 50);
            $table->string('document_number', 50);
            $table->string('supplier_rut', 20);
            $table->string('supplier_name');
            $table->string('description')->nullable();
            $table->decimal('exempt_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->decimal('other_taxes', 15, 2)->default(0);
            $table->boolean('is_electronic')->default(false);
            $table->string('sii_track_id')->nullable();
            $table->json('additional_data')->nullable();
            $table->timestamps();
            
            $table->index(['document_date', 'document_type']);
            $table->index('supplier_rut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_book_entries');
    }
};