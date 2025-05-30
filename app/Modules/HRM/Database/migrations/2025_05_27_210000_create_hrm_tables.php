<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Empleados
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('employee_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('rut')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->date('birth_date');
            $table->enum('gender', ['M', 'F', 'O'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('photo')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated', 'suspended'])->default('active');
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_code']);
        });

        // Departamentos
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('employees');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Cargos
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('department_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->decimal('min_salary', 10, 2)->nullable();
            $table->decimal('max_salary', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'department_id']);
        });

        // Contratos
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('position_id')->constrained();
            $table->foreignId('department_id')->constrained();
            $table->string('contract_number')->unique();
            $table->enum('type', ['indefinite', 'fixed_term', 'per_project', 'part_time', 'internship']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('base_salary', 10, 2);
            $table->enum('salary_type', ['monthly', 'hourly', 'daily', 'weekly']);
            $table->integer('working_hours_per_week')->default(45);
            $table->json('benefits')->nullable(); // Array de beneficios
            $table->text('terms')->nullable();
            $table->enum('status', ['draft', 'active', 'terminated', 'expired'])->default('draft');
            $table->date('termination_date')->nullable();
            $table->string('termination_reason')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Asistencia
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->integer('worked_hours')->nullable(); // En minutos
            $table->integer('overtime_hours')->nullable(); // En minutos
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'holiday', 'weekend']);
            $table->text('notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'employee_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });

        // Permisos y Vacaciones
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('leave_type_id')->constrained();
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 5, 2);
            $table->text('reason');
            $table->text('manager_notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Tipos de Permiso
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->integer('days_per_year')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Balance de Vacaciones
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('leave_type_id')->constrained();
            $table->integer('year');
            $table->decimal('entitled_days', 5, 2);
            $table->decimal('taken_days', 5, 2)->default(0);
            $table->decimal('remaining_days', 5, 2);
            $table->decimal('carried_forward', 5, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['tenant_id', 'employee_id', 'leave_type_id', 'year']);
        });

        // Nómina
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('payroll_number')->unique();
            $table->integer('month');
            $table->integer('year');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->decimal('total_earnings', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'year', 'month']);
            $table->index(['tenant_id', 'status']);
        });

        // Detalles de Nómina
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('contract_id')->constrained('employment_contracts');
            
            // Ingresos
            $table->decimal('base_salary', 10, 2);
            $table->integer('worked_days');
            $table->integer('worked_hours');
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_amount', 10, 2)->default(0);
            $table->decimal('commissions', 10, 2)->default(0);
            $table->decimal('bonuses', 10, 2)->default(0);
            $table->decimal('other_earnings', 10, 2)->default(0);
            $table->decimal('total_earnings', 10, 2);
            
            // Deducciones
            $table->decimal('afp_amount', 10, 2)->default(0);
            $table->decimal('health_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2);
            
            // Neto
            $table->decimal('net_pay', 10, 2);
            
            $table->json('earnings_breakdown')->nullable();
            $table->json('deductions_breakdown')->nullable();
            
            $table->enum('payment_method', ['transfer', 'check', 'cash'])->default('transfer');
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['payroll_id', 'employee_id']);
        });

        // Préstamos a Empleados
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->string('loan_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->integer('installments');
            $table->decimal('installment_amount', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['active', 'paid', 'defaulted', 'cancelled'])->default('active');
            $table->timestamps();
            
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Documentos de Empleados
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->string('name');
            $table->enum('type', ['contract', 'id', 'certification', 'degree', 'other']);
            $table->string('file_path');
            $table->string('file_size');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'employee_id']);
        });

        // Capacitaciones
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('provider')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration_hours')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
        });

        // Empleados en Capacitaciones
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'failed', 'dropped'])->default('enrolled');
            $table->decimal('score', 5, 2)->nullable();
            $table->date('completion_date')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            
            $table->unique(['training_id', 'employee_id']);
        });

        // Evaluaciones de Desempeño
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('reviewer_id')->constrained('employees');
            $table->string('review_period');
            $table->date('review_date');
            $table->json('competencies')->nullable(); // Array de competencias evaluadas
            $table->decimal('overall_rating', 3, 2);
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('goals')->nullable();
            $table->text('employee_comments')->nullable();
            $table->enum('status', ['draft', 'submitted', 'acknowledged'])->default('draft');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('employee_trainings');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('payroll_details');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employment_contracts');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('employees');
    }
};