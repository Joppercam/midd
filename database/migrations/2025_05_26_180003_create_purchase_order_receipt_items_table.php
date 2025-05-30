<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_receipt_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_item_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity_received', 10, 2);
            $table->string('condition')->default('good'); // good, damaged, rejected
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('purchase_order_receipt_id', 'po_receipt_id_index');
            $table->index('purchase_order_item_id', 'po_item_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_items');
    }
};