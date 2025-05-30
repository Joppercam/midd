<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('restrict');
            $table->string('description');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_received', 10, 2)->default(0);
            $table->string('unit')->default('unidad');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(19);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->integer('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('purchase_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};