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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('quote_number', 50)->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->enum('status', ['draft', 'sent', 'approved', 'rejected', 'converted', 'expired']);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('payment_conditions')->nullable();
            $table->integer('validity_days')->default(30);
            $table->foreignId('converted_to_invoice_id')->nullable()->constrained('tax_documents')->onDelete('set null');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'issue_date']);
            $table->index(['tenant_id', 'quote_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
