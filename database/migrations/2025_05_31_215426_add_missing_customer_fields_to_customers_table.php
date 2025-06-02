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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type')->default('person')->after('name'); // person or company
            $table->string('commune')->nullable()->after('address');
            $table->string('city')->nullable()->after('commune');
            $table->string('business_activity')->nullable()->after('city');
            $table->string('contact_name')->nullable()->after('business_activity');
            $table->text('notes')->nullable()->after('contact_name');
            $table->integer('payment_term_days')->nullable()->after('credit_limit');
            
            // Add indexes for better performance
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'type']);
            $table->dropIndex(['tenant_id', 'city']);
            
            $table->dropColumn([
                'type',
                'commune',
                'city', 
                'business_activity',
                'contact_name',
                'notes',
                'payment_term_days'
            ]);
        });
    }
};
