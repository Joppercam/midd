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
        Schema::table('products', function (Blueprint $table) {
            // Agregar campos faltantes
            $table->string('code', 50)->after('id')->nullable();
            $table->string('unit', 20)->after('price')->default('Unidad');
            $table->decimal('minimum_stock', 10, 2)->after('stock')->default(0);
            
            // Agregar campos para funcionalidades avanzadas
            $table->decimal('weight', 8, 3)->nullable()->after('unit');
            $table->string('barcode', 100)->nullable()->after('code');
            $table->text('notes')->nullable()->after('description');
            $table->boolean('track_inventory')->default(true)->after('type');
            $table->boolean('allow_negative_stock')->default(false)->after('track_inventory');
            // cost ya existe en la tabla
            $table->decimal('margin_percentage', 5, 2)->nullable()->after('cost');
            $table->string('location', 100)->nullable()->after('notes');
            $table->date('last_inventory_date')->nullable();
            $table->integer('reorder_point')->default(0)->after('minimum_stock');
            $table->integer('reorder_quantity')->default(0)->after('reorder_point');
            
            // Índices para optimizar búsquedas
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropIndex(['tenant_id', 'barcode']);
            $table->dropIndex(['tenant_id', 'category_id']);
            $table->dropIndex(['tenant_id', 'stock']);
            
            $table->dropColumn([
                'code',
                'unit',
                'minimum_stock',
                'weight',
                'barcode',
                'notes',
                'track_inventory',
                'allow_negative_stock',
                // 'cost', ya existe
                'margin_percentage',
                'location',
                'last_inventory_date',
                'reorder_point',
                'reorder_quantity'
            ]);
        });
    }
};