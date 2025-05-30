<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance', 15, 2)->nullable();
            $table->string('transaction_type'); // deposit, withdrawal, fee, interest
            $table->string('category')->nullable();
            $table->string('external_id')->nullable(); // ID from bank
            $table->string('status')->default('pending'); // pending, matched, reconciled, ignored
            $table->json('metadata')->nullable(); // Additional bank data
            $table->timestamps();

            $table->index(['bank_account_id', 'transaction_date']);
            $table->index(['tenant_id', 'status']);
            $table->index('reference');
            $table->unique(['bank_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};