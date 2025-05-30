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
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('certificate_password')->nullable()->after('sii_environment');
            $table->timestamp('certificate_uploaded_at')->nullable()->after('certificate_password');
            $table->string('authorized_sender_rut')->nullable()->after('certificate_uploaded_at');
            $table->date('sii_resolution_date')->nullable()->after('authorized_sender_rut');
            $table->string('sii_resolution_number')->nullable()->after('sii_resolution_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_password',
                'certificate_uploaded_at',
                'authorized_sender_rut',
                'sii_resolution_date',
                'sii_resolution_number'
            ]);
        });
    }
};