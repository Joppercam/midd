<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('receipt_number')->unique();
            $table->timestamp('received_at');
            $table->string('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_document')->nullable();
            $table->timestamps();
            
            $table->index('purchase_order_id');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipts');
    }
};