<?php

namespace App\Modules\HRM;

use App\Core\BaseModule;

class Module extends BaseModule
{
    public function getCode(): string
    {
        return 'hrm';
    }

    public function getName(): string
    {
        return 'HRM';
    }

    public function getDescription(): string
    {
        return 'Human Resources Management - Gestión completa de recursos humanos, nómina, asistencia, vacaciones y evaluaciones de desempeño';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return ['core'];
    }

    public function getPermissions(): array
    {
        return [
            // Employee Management
            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.delete',
            'employees.documents',
            'employees.contracts',
            'employees.emergency_contacts',
            'employees.bank_accounts',
            'employees.deactivate',
            'employees.reactivate',
            
            // Attendance Management
            'attendance.view',
            'attendance.check_in',
            'attendance.check_out',
            'attendance.edit',
            'attendance.approve',
            'attendance.reports',
            'attendance.bulk_import',
            'attendance.overtime',
            
            // Leave Management
            'leaves.view',
            'leaves.request',
            'leaves.approve',
            'leaves.reject',
            'leaves.cancel',
            'leaves.balance',
            'leaves.calendar',
            'leaves.policies',
            
            // Payroll Management
            'payroll.view',
            'payroll.create',
            'payroll.edit',
            'payroll.approve',
            'payroll.process',
            'payroll.payments',
            'payroll.deductions',
            'payroll.bonuses',
            'payroll.reports',
            'payroll.export',
            
            // Benefits Management
            'benefits.view',
            'benefits.create',
            'benefits.edit',
            'benefits.assign',
            'benefits.health_insurance',
            'benefits.life_insurance',
            'benefits.retirement',
            
            // Performance Management
            'performance.view',
            'performance.create',
            'performance.evaluate',
            'performance.goals',
            'performance.reviews',
            'performance.feedback',
            'performance.reports',
            
            // Training & Development
            'training.view',
            'training.create',
            'training.assign',
            'training.complete',
            'training.certificates',
            'training.budget',
            
            // Department Management
            'departments.view',
            'departments.create',
            'departments.edit',
            'departments.delete',
            'departments.managers',
            
            // Position Management
            'positions.view',
            'positions.create',
            'positions.edit',
            'positions.delete',
            'positions.hierarchy',
            
            // HR Reports & Analytics
            'hr.reports',
            'hr.analytics',
            'hr.dashboard',
            'hr.compliance',
            'hr.audits',
            
            // HR Administration
            'hr.settings',
            'hr.policies',
            'hr.announcements',
            'hr.documents',
            'hr.templates',
        ];
    }

    public function getRoutes(): string
    {
        return __DIR__ . '/routes.php';
    }

    public function getConfig(): array
    {
        return [
            'company' => [
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'working_hours' => [
                    'start' => '09:00',
                    'end' => '18:00',
                    'break_start' => '13:00',
                    'break_end' => '14:00'
                ],
                'fiscal_year_start' => '01-01',
                'probation_period_days' => 90,
            ],
            
            'attendance' => [
                'check_in_tolerance_minutes' => 15,
                'late_penalty_enabled' => true,
                'overtime_enabled' => true,
                'overtime_rates' => [
                    'weekday' => 1.5,
                    'weekend' => 2.0,
                    'holiday' => 2.5
                ],
                'location_tracking' => false,
                'biometric_enabled' => false,
            ],
            
            'leaves' => [
                'annual_leave_days' => 15,
                'sick_leave_days' => 10,
                'personal_leave_days' => 5,
                'maternity_leave_days' => 84,
                'paternity_leave_days' => 5,
                'carry_forward_enabled' => true,
                'max_carry_forward_days' => 5,
                'advance_booking_days' => 30,
                'min_consecutive_days' => 1,
                'approval_levels' => 2,
            ],
            
            'payroll' => [
                'payment_frequency' => 'monthly', // weekly, biweekly, monthly
                'payment_day' => 28,
                'currency' => 'CLP',
                'minimum_wage' => 410000,
                'tax_brackets' => [
                    ['min' => 0, 'max' => 777834, 'rate' => 0],
                    ['min' => 777835, 'max' => 1729630, 'rate' => 4],
                    ['min' => 1729631, 'max' => 2883050, 'rate' => 8],
                    ['min' => 2883051, 'max' => 4036470, 'rate' => 13.5],
                    ['min' => 4036471, 'max' => 5189890, 'rate' => 23],
                    ['min' => 5189891, 'max' => 6919520, 'rate' => 30.4],
                    ['min' => 6919521, 'max' => 17974590, 'rate' => 35],
                    ['min' => 17974591, 'max' => null, 'rate' => 40],
                ],
                'social_security' => [
                    'pension' => 10.0,
                    'health' => 7.0,
                    'unemployment' => 0.6,
                ],
                'employer_contributions' => [
                    'accident_insurance' => 0.95,
                    'unemployment_employer' => 2.4,
                ],
            ],
            
            'benefits' => [
                'health_insurance_enabled' => true,
                'life_insurance_enabled' => true,
                'meal_vouchers_enabled' => true,
                'transport_allowance_enabled' => true,
                'education_assistance_enabled' => true,
                'gym_membership_enabled' => false,
            ],
            
            'performance' => [
                'review_frequency' => 'annual', // monthly, quarterly, semi-annual, annual
                'rating_scale' => 5,
                'self_evaluation_enabled' => true,
                'peer_review_enabled' => true,
                '360_feedback_enabled' => false,
                'goal_setting_enabled' => true,
                'competency_framework' => true,
            ],
            
            'documents' => [
                'required_documents' => [
                    'id_card',
                    'tax_id',
                    'bank_account',
                    'emergency_contact',
                    'education_certificates',
                ],
                'contract_templates' => [
                    'permanent',
                    'fixed_term',
                    'part_time',
                    'internship',
                ],
                'document_expiry_alerts' => 30, // days before expiry
            ],
            
            'compliance' => [
                'labor_law_version' => '2024',
                'data_retention_years' => 5,
                'audit_trail_enabled' => true,
                'anonymous_complaints_enabled' => true,
            ],
        ];
    }

    public function boot(): void
    {
        // Register HRM services
        app()->bind('EmployeeService', \App\Modules\HRM\Services\EmployeeService::class);
        app()->bind('AttendanceService', \App\Modules\HRM\Services\AttendanceService::class);
        app()->bind('PayrollService', \App\Modules\HRM\Services\PayrollService::class);
        app()->bind('LeaveService', \App\Modules\HRM\Services\LeaveService::class);
        app()->bind('PerformanceService', \App\Modules\HRM\Services\PerformanceService::class);
    }
}