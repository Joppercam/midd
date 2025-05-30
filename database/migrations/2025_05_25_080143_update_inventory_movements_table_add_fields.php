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
        Schema::table('inventory_movements', function (Blueprint $table) {
            // Verificar si las columnas no existen antes de crearlas
            if (!Schema::hasColumn('inventory_movements', 'unit_cost')) {
                $table->decimal('unit_cost', 10, 2)->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('inventory_movements', 'total_cost')) {
                $table->decimal('total_cost', 10, 2)->nullable()->after('unit_cost');
            }
            if (!Schema::hasColumn('inventory_movements', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('notes')->constrained('users');
            }
            
            // Agregar índices
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'product_id']);
            $table->dropIndex(['tenant_id', 'type']);
            $table->dropIndex(['tenant_id', 'created_at']);
            
            if (Schema::hasColumn('inventory_movements', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
            if (Schema::hasColumn('inventory_movements', 'total_cost')) {
                $table->dropColumn('total_cost');
            }
            if (Schema::hasColumn('inventory_movements', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};