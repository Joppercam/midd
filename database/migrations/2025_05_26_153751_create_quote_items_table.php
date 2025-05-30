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
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('restrict');
            $table->string('description');
            $table->string('product_code', 50)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('unidad');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->integer('position')->default(0);
            $table->timestamps();
            
            // Índices
            $table->index(['quote_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
