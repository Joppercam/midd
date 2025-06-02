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
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('rut')->nullable();
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone');
            $table->string('business_type')->nullable();
            $table->string('employees')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'contacted', 'demo_scheduled', 'demo_completed', 'converted', 'declined'])
                  ->default('pending');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('demo_scheduled_at')->nullable();
            $table->json('notes')->nullable(); // Para notas internas del equipo de ventas
            $table->string('assigned_to')->nullable(); // Usuario asignado para seguimiento
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
