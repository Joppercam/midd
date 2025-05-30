<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan de cuentas
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('code')->unique(); // 1.1.01.001
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->enum('subtype', [
                'current_asset', 'non_current_asset',
                'current_liability', 'non_current_liability',
                'capital', 'retained_earnings',
                'operating_revenue', 'non_operating_revenue',
                'operating_expense', 'non_operating_expense'
            ]);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts');
            $table->integer('level')->default(1); // Nivel en la jerarquía
            $table->boolean('is_parent')->default(false); // Si tiene cuentas hijas
            $table->boolean('accepts_entries')->default(true); // Si acepta movimientos
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('normal_balance', ['debit', 'credit']); // Naturaleza de la cuenta
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Configuraciones adicionales
            $table->timestamps();
            
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'parent_id']);
        });

        // Asientos contables
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->string('reference')->nullable(); // Número de documento de referencia
            $table->text('description');
            $table->decimal('total_debit', 15, 2);
            $table->decimal('total_credit', 15, 2);
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->enum('type', ['manual', 'automatic', 'adjustment', 'closing'])->default('manual');
            $table->string('source')->nullable(); // invoice, payment, adjustment, etc.
            $table->foreignId('source_id')->nullable(); // ID del documento origen
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'entry_date']);
            $table->index(['tenant_id', 'entry_number']);
            $table->index(['tenant_id', 'type']);
        });

        // Detalle de asientos contables
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['journal_entry_id', 'account_id']);
        });

        // Libros contables (Mayor y auxiliares)
        Schema::create('general_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->foreignId('journal_entry_id')->constrained();
            $table->foreignId('journal_entry_line_id')->constrained();
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('description');
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->decimal('running_balance', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index(['tenant_id', 'account_id', 'transaction_date']);
            $table->index(['tenant_id', 'transaction_date']);
        });

        // Centros de costo
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Presupuestos
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('year');
            $table->enum('type', ['annual', 'monthly', 'quarterly']);
            $table->enum('status', ['draft', 'approved', 'active', 'closed'])->default('draft');
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'year', 'status']);
        });

        // Líneas de presupuesto
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
            $table->integer('month')->nullable(); // 1-12, null para anual
            $table->decimal('budgeted_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('variance', 15, 2)->default(0);
            $table->decimal('variance_percentage', 5, 2)->default(0);
            $table->timestamps();
            
            $table->index(['budget_id', 'account_id']);
            $table->index(['budget_id', 'month']);
        });

        // Períodos fiscales
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name'); // "Enero 2025", "2025"
            $table->enum('type', ['month', 'quarter', 'year']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed', 'locked'])->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['tenant_id', 'start_date', 'end_date']);
        });

        // Configuración fiscal
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->integer('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['current', 'closed', 'future'])->default('current');
            $table->boolean('is_current')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'year']);
            $table->index(['tenant_id', 'is_current']);
        });

        // Tipos de cambio (para moneda extranjera)
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->date('effective_date');
            $table->decimal('rate', 10, 6);
            $table->enum('source', ['manual', 'api', 'bank'])->default('manual');
            $table->timestamps();
            
            $table->unique(['tenant_id', 'from_currency', 'to_currency', 'effective_date']);
            $table->index(['tenant_id', 'effective_date']);
        });

        // Plantillas de asientos
        Schema::create('journal_entry_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['standard', 'recurring', 'adjustment']);
            $table->json('template_data'); // Estructura del asiento
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'type', 'is_active']);
        });

        // Asientos recurrentes
        Schema::create('recurring_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('template_id')->constrained('journal_entry_templates');
            $table->string('name');
            $table->enum('frequency', ['monthly', 'quarterly', 'annually']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_execution_date');
            $table->integer('day_of_month')->nullable(); // Para frecuencia mensual
            $table->boolean('is_active')->default(true);
            $table->json('template_data');
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active', 'next_execution_date']);
        });

        // Log de ejecución de asientos recurrentes
        Schema::create('recurring_entry_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_entry_id')->constrained('recurring_journal_entries');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->date('execution_date');
            $table->enum('status', ['success', 'failed', 'skipped']);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['recurring_entry_id', 'execution_date']);
        });

        // Configuración contable por tenant
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('base_currency', 3)->default('CLP');
            $table->enum('accounting_method', ['cash', 'accrual'])->default('accrual');
            $table->date('fiscal_year_start');
            $table->date('fiscal_year_end');
            $table->integer('decimal_places')->default(2);
            $table->boolean('use_cost_centers')->default(false);
            $table->boolean('multi_currency')->default(false);
            $table->boolean('auto_post_invoices')->default(true);
            $table->boolean('auto_post_payments')->default(true);
            $table->json('default_accounts')->nullable(); // Cuentas por defecto
            $table->json('settings')->nullable(); // Configuraciones adicionales
            $table->timestamps();
            
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('recurring_entry_executions');
        Schema::dropIfExists('recurring_journal_entries');
        Schema::dropIfExists('journal_entry_templates');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('general_ledger');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};