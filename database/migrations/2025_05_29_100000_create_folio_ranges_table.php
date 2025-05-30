<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->integer('document_type')->comment('33, 34, 39, 52, 56, 61');
            $table->bigInteger('start_folio');
            $table->bigInteger('end_folio');
            $table->bigInteger('current_folio');
            $table->string('caf_file_path')->nullable();
            $table->text('caf_content')->nullable();
            $table->date('authorization_date');
            $table->date('expiration_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_exhausted')->default(false);
            $table->integer('alert_threshold')->default(100)->comment('Alert when remaining folios below this');
            $table->string('environment')->default('production')->comment('production or certification');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'document_type', 'start_folio']);
            $table->index(['tenant_id', 'document_type', 'is_active']);
            $table->index(['tenant_id', 'document_type', 'current_folio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_ranges');
    }
};