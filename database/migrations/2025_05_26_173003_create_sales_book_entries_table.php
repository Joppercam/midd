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
        Schema::create('sales_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_document_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date');
            $table->string('document_type', 50);
            $table->string('document_number', 50);
            $table->string('customer_rut', 20)->nullable();
            $table->string('customer_name');
            $table->decimal('exempt_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->boolean('is_electronic')->default(false);
            $table->boolean('is_export')->default(false);
            $table->string('sii_track_id')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->json('additional_data')->nullable();
            $table->timestamps();
            
            $table->index(['document_date', 'document_type']);
            $table->index('customer_rut');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_book_entries');
    }
};