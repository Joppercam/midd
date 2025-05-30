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
        Schema::table('tax_documents', function (Blueprint $table) {
            // Campo folio requerido por SII
            $table->bigInteger('folio')->nullable()->after('number')->comment('Folio asignado del rango autorizado por SII');
            
            // Tipo de documento SII
            $table->integer('document_type')->nullable()->after('type')->comment('Código de tipo de documento SII (33, 34, 39, 56, 61)');
            
            // Campo adicional para mapeo SII
            $table->integer('sii_document_type')->nullable()->after('document_type')->comment('Tipo de documento SII para procesamiento');
            
            // Índices para búsquedas eficientes
            $table->index(['tenant_id', 'document_type', 'folio'], 'idx_tenant_doctype_folio');
            $table->index(['tenant_id', 'folio'], 'idx_tenant_folio');
            $table->index(['document_type', 'status'], 'idx_doctype_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_documents', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex('idx_tenant_doctype_folio');
            $table->dropIndex('idx_tenant_folio');
            $table->dropIndex('idx_doctype_status');
            
            // Eliminar columnas
            $table->dropColumn(['folio', 'document_type', 'sii_document_type']);
        });
    }
};