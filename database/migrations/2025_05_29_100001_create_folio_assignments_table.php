<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('folio_range_id')->constrained()->onDelete('cascade');
            $table->foreignId('tax_document_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('document_type');
            $table->bigInteger('folio_number');
            $table->enum('status', ['assigned', 'used', 'cancelled', 'recycled']);
            $table->timestamp('assigned_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('recycled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['tenant_id', 'document_type', 'folio_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['folio_range_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_assignments');
    }
};