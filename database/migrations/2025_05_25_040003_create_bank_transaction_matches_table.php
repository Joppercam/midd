<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transaction_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->morphs('matchable'); // Can match to payments, expenses, tax_documents, etc.
            $table->string('match_type'); // payment_received, payment_made, invoice, expense, etc.
            $table->decimal('matched_amount', 15, 2);
            $table->float('confidence_score')->default(100); // 0-100 match confidence
            $table->string('match_method')->default('manual'); // manual, auto_reference, auto_amount, auto_date
            $table->json('match_details')->nullable(); // Details about why it was matched
            $table->foreignId('matched_by')->nullable()->constrained('users');
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained();
            $table->timestamps();

            $table->index(['bank_transaction_id', 'matchable_type', 'matchable_id']);
            $table->index(['tenant_id', 'match_type']);
            $table->unique(['bank_transaction_id', 'matchable_type', 'matchable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_matches');
    }
};