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
        Schema::create('tax_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id')->nullable();
            $table->enum('type', ['invoice', 'receipt', 'credit_note', 'debit_note']);
            $table->string('number');
            $table->string('sii_track_id')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'cancelled'])->default('draft');
            $table->enum('sii_status', ['pending', 'sent', 'processing', 'accepted', 'rejected', 'error'])->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('total', 15, 2);
            $table->text('xml_content')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('sii_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('tenant_id');
            $table->index('type');
            $table->index('number');
            $table->index('customer_id');
            $table->index('status');
            $table->index('sii_status');
            $table->index('issue_date');
            $table->index(['tenant_id', 'type', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_documents');
    }
};