<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tax_documents', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('tax_documents', 'sii_status_detail')) {
                $table->text('sii_status_detail')->nullable()->after('sii_status');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_acceptance_status')) {
                $table->string('sii_acceptance_status')->nullable()->after('sii_response');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_accepted_at')) {
                $table->timestamp('sii_accepted_at')->nullable()->after('sii_acceptance_status');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_rejected_at')) {
                $table->timestamp('sii_rejected_at')->nullable()->after('sii_accepted_at');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_last_check')) {
                $table->timestamp('sii_last_check')->nullable()->after('sii_rejected_at');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_dte_id')) {
                $table->string('sii_dte_id')->nullable()->after('sii_last_check');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_send_attempts')) {
                $table->integer('sii_send_attempts')->default(0)->after('sii_dte_id');
            }
            
            if (!Schema::hasColumn('tax_documents', 'sii_send_errors')) {
                $table->text('sii_send_errors')->nullable()->after('sii_send_attempts');
            }
            
            if (!Schema::hasColumn('tax_documents', 'dte_generated_at')) {
                $table->timestamp('dte_generated_at')->nullable()->after('checked_at');
            }
        });
        
        // Add indexes in a separate statement to avoid conflicts
        Schema::table('tax_documents', function (Blueprint $table) {
            // Check if indexes exist before adding
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'tax_documents'");
            $indexNames = array_column($indexes, 'name');
            
            if (!in_array('tax_documents_sii_track_id_index', $indexNames)) {
                $table->index('sii_track_id');
            }
            
            if (!in_array('tax_documents_sii_status_index', $indexNames)) {
                $table->index('sii_status');
            }
            
            if (!in_array('tax_documents_type_number_index', $indexNames)) {
                $table->index(['type', 'number']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_documents', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['sii_track_id']);
            $table->dropIndex(['sii_status']);
            $table->dropIndex(['type', 'number']);
            
            // Drop columns
            $table->dropColumn([
                'sii_response',
                'sii_acceptance_status',
                'sii_accepted_at',
                'sii_rejected_at',
                'sii_last_check',
                'sii_dte_id',
                'sii_send_attempts',
                'sii_send_errors'
            ]);
        });
    }
};