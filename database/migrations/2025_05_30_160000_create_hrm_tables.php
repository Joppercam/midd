<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('manager_id')->nullable(); // Empleado que administra este departamento
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
        });

        // Positions/Job titles
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('department_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->json('requirements')->nullable(); // Habilidades, experiencia, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->index(['tenant_id', 'department_id']);
        });

        // Employees
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable(); // Vínculo al Usuario si tiene acceso al sistema
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('rut')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_number']);
        });

        // Agregar clave foránea a departamentos para el gerente
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
        });

        // Employment contracts
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('department_id');
            $table->uuid('position_id');
            $table->string('contract_number');
            $table->enum('contract_type', ['indefinite', 'fixed_term', 'part_time', 'temporary', 'internship']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('base_salary', 10, 2);
            $table->integer('work_hours_per_week')->default(45);
            $table->json('benefits')->nullable(); // Seguro de salud, transporte, etc.
            $table->json('allowances')->nullable(); // Asignación familiar, subsidio de transporte, etc.
            $table->text('terms_and_conditions')->nullable();
            $table->enum('status', ['active', 'terminated', 'suspended'])->default('active');
            $table->date('terminated_at')->nullable();
            $table->string('termination_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        // Attendance records
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('regular_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('break_hours', 5, 2)->default(0);
            $table->enum('status', ['present', 'absent', 'late', 'partial', 'holiday', 'sick_leave', 'vacation'])->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'date']);
            $table->index(['tenant_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        // Leave requests
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('approved_by')->nullable(); // Empleado que aprobó
            $table->enum('type', ['vacation', 'sick_leave', 'maternity', 'paternity', 'personal', 'bereavement', 'other']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 5, 1);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('approval_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('employees')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'start_date']);
        });

        // Payroll periods
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // "Enero 2025", "Semana 1 Enero 2025", etc.
            $table->enum('period_type', ['monthly', 'weekly', 'biweekly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'processing', 'calculated', 'approved', 'paid'])->default('draft');
            $table->decimal('total_gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net_pay', 15, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('employees')->onDelete('set null');
            $table->index(['tenant_id', 'period_type']);
            $table->index(['start_date', 'end_date']);
        });

        // Payslips (liquidaciones)
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('payroll_period_id');
            $table->string('payslip_number');
            $table->date('pay_date');
            
            // Información salarial básica
            $table->decimal('base_salary', 10, 2);
            $table->decimal('worked_days', 5, 1);
            $table->decimal('total_days', 5, 1);
            $table->decimal('regular_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            
            // Ingresos
            $table->decimal('basic_pay', 10, 2);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->decimal('family_allowance', 10, 2)->default(0);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('meal_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('total_earnings', 10, 2);
            
            // Deducciones
            $table->decimal('pension_deduction', 10, 2)->default(0);
            $table->decimal('health_deduction', 10, 2)->default(0);
            $table->decimal('unemployment_insurance', 10, 2)->default(0);
            $table->decimal('income_tax', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2);
            
            // Sueldo neto
            $table->decimal('net_pay', 10, 2);
            
            // Información adicional
            $table->json('detailed_earnings')->nullable(); // Breakdown of all earnings
            $table->json('detailed_deductions')->nullable(); // Breakdown of all deductions
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('payroll_period_id')->references('id')->on('payroll_periods')->onDelete('cascade');
            $table->unique(['employee_id', 'payroll_period_id']);
            $table->index(['tenant_id', 'pay_date']);
            $table->index(['payroll_period_id', 'status']);
        });

        // Employee benefits
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->string('benefit_type'); // seguro_salud, transporte, vales_comida, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->enum('amount_type', ['fixed', 'percentage'])->default('fixed');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Datos adicionales específicos del beneficio
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['tenant_id', 'benefit_type']);
            $table->index(['employee_id', 'is_active']);
        });

        // Configuraciones fiscales y legales por tenant
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->decimal('pension_rate', 5, 4)->default(0.1000); // 10%
            $table->decimal('health_rate', 5, 4)->default(0.0700); // 7%
            $table->decimal('unemployment_rate', 5, 4)->default(0.0060); // 0.6%
            $table->decimal('overtime_rate', 3, 2)->default(1.50); // 50% extra
            $table->decimal('family_allowance_amount', 10, 2)->default(0);
            $table->json('tax_brackets')->nullable(); // Tramos de impuesto a la renta
            $table->json('working_hours')->nullable(); // Horario laboral estándar
            $table->json('holiday_calendar')->nullable(); // Feriados de la empresa
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
        Schema::dropIfExists('employee_benefits');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employment_contracts');
        
        // Eliminar clave foránea antes de eliminar empleados
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};