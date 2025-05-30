<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->string('bank_name');
            $table->string('account_type')->default('checking'); // checking, savings, credit_card
            $table->string('currency', 3)->default('CLP');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('reconciled_balance', 15, 2)->default(0);
            $table->date('last_reconciled_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};