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
        Schema::create('sii_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('tax_document_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // upload, status_check, acceptance, rejection, error
            $table->string('track_id')->nullable();
            $table->string('status')->nullable();
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->integer('response_time')->nullable(); // in milliseconds
            $table->timestamps();
            
            $table->index(['tenant_id', 'event_type']);
            $table->index('track_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_event_logs');
    }
};