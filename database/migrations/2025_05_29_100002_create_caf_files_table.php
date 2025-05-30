<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('folio_range_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('document_type');
            $table->string('file_path');
            $table->text('xml_content');
            $table->string('rut_emisor');
            $table->string('rut_envia');
            $table->bigInteger('range_start');
            $table->bigInteger('range_end');
            $table->date('fecha_autorizacion');
            $table->text('public_key');
            $table->text('private_key');
            $table->string('signature');
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->json('validation_errors')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'document_type']);
            $table->index(['tenant_id', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_files');
    }
};