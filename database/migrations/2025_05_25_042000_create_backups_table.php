<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('manual'); // manual, scheduled, automatic
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->bigInteger('size')->default(0); // in bytes
            $table->json('included_tables')->nullable();
            $table->json('excluded_tables')->nullable();
            $table->boolean('include_files')->default(true);
            $table->json('statistics')->nullable(); // tables count, files count, etc
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('retention_days')->default(30);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['expires_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};