<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HRM Module Configuration
    |--------------------------------------------------------------------------
    */

    'company' => [
        'working_days' => env('HRM_WORKING_DAYS', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        'working_hours' => [
            'start' => env('HRM_WORK_START', '09:00'),
            'end' => env('HRM_WORK_END', '18:00'),
            'break_start' => env('HRM_BREAK_START', '13:00'),
            'break_end' => env('HRM_BREAK_END', '14:00')
        ],
        'fiscal_year_start' => env('HRM_FISCAL_YEAR_START', '01-01'),
        'probation_period_days' => env('HRM_PROBATION_DAYS', 90),
        'timezone' => env('HRM_TIMEZONE', 'America/Santiago'),
    ],
    
    'attendance' => [
        'check_in_tolerance_minutes' => env('HRM_CHECKIN_TOLERANCE', 15),
        'late_penalty_enabled' => env('HRM_LATE_PENALTY', true),
        'overtime_enabled' => env('HRM_OVERTIME_ENABLED', true),
        'overtime_rates' => [
            'weekday' => 1.5,
            'weekend' => 2.0,
            'holiday' => 2.5
        ],
        'location_tracking' => env('HRM_LOCATION_TRACKING', false),
        'biometric_enabled' => env('HRM_BIOMETRIC', false),
        'auto_check_out' => env('HRM_AUTO_CHECKOUT', true),
        'auto_check_out_time' => env('HRM_AUTO_CHECKOUT_TIME', '23:59'),
        'shift_types' => [
            'morning' => ['start' => '06:00', 'end' => '14:00'],
            'afternoon' => ['start' => '14:00', 'end' => '22:00'],
            'night' => ['start' => '22:00', 'end' => '06:00'],
            'regular' => ['start' => '09:00', 'end' => '18:00'],
        ],
    ],
    
    'leaves' => [
        'types' => [
            'annual' => [
                'name' => 'Vacaciones Anuales',
                'days' => 15,
                'color' => '#10B981',
                'paid' => true,
                'requires_approval' => true,
                'advance_notice_days' => 15
            ],
            'sick' => [
                'name' => 'Licencia Médica',
                'days' => 10,
                'color' => '#EF4444',
                'paid' => true,
                'requires_approval' => true,
                'requires_certificate' => true
            ],
            'personal' => [
                'name' => 'Permiso Personal',
                'days' => 5,
                'color' => '#3B82F6',
                'paid' => true,
                'requires_approval' => true,
                'advance_notice_days' => 2
            ],
            'maternity' => [
                'name' => 'Licencia Maternal',
                'days' => 84,
                'color' => '#EC4899',
                'paid' => true,
                'requires_approval' => false,
                'gender_specific' => 'female'
            ],
            'paternity' => [
                'name' => 'Licencia Paternal',
                'days' => 5,
                'color' => '#6366F1',
                'paid' => true,
                'requires_approval' => false,
                'gender_specific' => 'male'
            ],
            'bereavement' => [
                'name' => 'Duelo',
                'days' => 7,
                'color' => '#6B7280',
                'paid' => true,
                'requires_approval' => true
            ],
            'unpaid' => [
                'name' => 'Permiso sin Goce de Sueldo',
                'days' => null,
                'color' => '#F59E0B',
                'paid' => false,
                'requires_approval' => true,
                'advance_notice_days' => 7
            ],
        ],
        'carry_forward_enabled' => env('HRM_LEAVE_CARRY_FORWARD', true),
        'max_carry_forward_days' => env('HRM_MAX_CARRY_FORWARD', 5),
        'advance_booking_days' => env('HRM_ADVANCE_BOOKING', 30),
        'min_consecutive_days' => 1,
        'approval_levels' => 2,
        'blackout_dates' => [], // Dates when leaves cannot be taken
        'peak_season_restrictions' => false,
    ],
    
    'payroll' => [
        'payment_frequency' => env('HRM_PAYMENT_FREQUENCY', 'monthly'),
        'payment_day' => env('HRM_PAYMENT_DAY', 28),
        'currency' => env('HRM_CURRENCY', 'CLP'),
        'minimum_wage' => env('HRM_MINIMUM_WAGE', 410000),
        'uf_enabled' => true, // Unidad de Fomento for Chile
        
        // Chilean Tax Brackets 2024
        'tax_brackets' => [
            ['min' => 0, 'max' => 777834, 'rate' => 0, 'deduction' => 0],
            ['min' => 777835, 'max' => 1729630, 'rate' => 4, 'deduction' => 31113.36],
            ['min' => 1729631, 'max' => 2883050, 'rate' => 8, 'deduction' => 100398.56],
            ['min' => 2883051, 'max' => 4036470, 'rate' => 13.5, 'deduction' => 258955.81],
            ['min' => 4036471, 'max' => 5189890, 'rate' => 23, 'deduction' => 641935.96],
            ['min' => 5189891, 'max' => 6919520, 'rate' => 30.4, 'deduction' => 1025676.16],
            ['min' => 6919521, 'max' => 17974590, 'rate' => 35, 'deduction' => 1343606.56],
            ['min' => 17974591, 'max' => null, 'rate' => 40, 'deduction' => 2242096.66],
        ],
        
        // Chilean Social Security
        'social_security' => [
            'afp' => [
                'capital' => 11.44,
                'cuprum' => 11.44,
                'habitat' => 11.27,
                'planvital' => 11.16,
                'provida' => 11.45,
                'modelo' => 10.58,
                'uno' => 10.49,
            ],
            'health' => [
                'fonasa' => 7.0,
                'isapre_min' => 7.0,
            ],
            'unemployment' => 0.6,
            'sis' => 1.53, // Seguro de Invalidez y Sobrevivencia
        ],
        
        'employer_contributions' => [
            'accident_insurance' => 0.95,
            'unemployment_employer' => 2.4,
            'sis_employer' => 1.53,
        ],
        
        'deductions' => [
            'types' => [
                'loan' => 'Préstamo',
                'advance' => 'Anticipo',
                'cafeteria' => 'Casino',
                'other' => 'Otro'
            ]
        ],
        
        'bonuses' => [
            'types' => [
                'performance' => 'Bono por Desempeño',
                'attendance' => 'Bono por Asistencia',
                'productivity' => 'Bono por Productividad',
                'christmas' => 'Aguinaldo',
                'vacation' => 'Bono Vacacional',
                'special' => 'Bono Especial'
            ]
        ],
        
        'allowances' => [
            'meal' => env('HRM_MEAL_ALLOWANCE', 0),
            'transport' => env('HRM_TRANSPORT_ALLOWANCE', 0),
            'mobile' => env('HRM_MOBILE_ALLOWANCE', 0),
            'tool' => env('HRM_TOOL_ALLOWANCE', 0),
        ],
    ],
    
    'benefits' => [
        'health_insurance' => [
            'enabled' => env('HRM_HEALTH_INSURANCE', true),
            'employer_contribution' => 3.0, // Additional to legal 7%
            'family_coverage' => true,
        ],
        'life_insurance' => [
            'enabled' => env('HRM_LIFE_INSURANCE', true),
            'coverage_multiplier' => 12, // months of salary
        ],
        'meal_vouchers' => [
            'enabled' => env('HRM_MEAL_VOUCHERS', true),
            'daily_amount' => 5000,
            'working_days_only' => true,
        ],
        'transport_allowance' => [
            'enabled' => env('HRM_TRANSPORT_ALLOWANCE', true),
            'monthly_amount' => 40000,
            'distance_based' => false,
        ],
        'education_assistance' => [
            'enabled' => env('HRM_EDUCATION_ASSISTANCE', true),
            'annual_limit' => 500000,
            'requires_grade' => true,
            'min_grade' => 5.0,
        ],
        'gym_membership' => [
            'enabled' => env('HRM_GYM_MEMBERSHIP', false),
            'monthly_limit' => 30000,
        ],
        'childcare' => [
            'enabled' => env('HRM_CHILDCARE', true),
            'monthly_limit' => 200000,
            'max_age' => 5,
        ],
    ],
    
    'performance' => [
        'review_frequency' => env('HRM_REVIEW_FREQUENCY', 'annual'),
        'review_months' => [6, 12], // June and December
        'rating_scale' => 5,
        'rating_labels' => [
            1 => 'Necesita Mejorar',
            2 => 'Cumple Parcialmente',
            3 => 'Cumple Expectativas',
            4 => 'Supera Expectativas',
            5 => 'Excepcional'
        ],
        'self_evaluation_enabled' => true,
        'peer_review_enabled' => true,
        '360_feedback_enabled' => false,
        'goal_setting_enabled' => true,
        'competency_framework' => true,
        'min_performance_for_bonus' => 3,
        'probation_review_required' => true,
        'improvement_plan_threshold' => 2,
    ],
    
    'training' => [
        'annual_budget_per_employee' => 300000,
        'mandatory_courses' => [
            'safety' => 'Seguridad Laboral',
            'compliance' => 'Cumplimiento Normativo',
            'harassment' => 'Prevención de Acoso',
        ],
        'certification_tracking' => true,
        'expiry_alert_days' => 60,
        'online_platform_enabled' => true,
    ],
    
    'documents' => [
        'required_at_hiring' => [
            'id_card' => 'Cédula de Identidad',
            'tax_id' => 'RUT',
            'afp_certificate' => 'Certificado AFP',
            'health_certificate' => 'Certificado Salud',
            'bank_account' => 'Cuenta Bancaria',
            'emergency_contact' => 'Contacto de Emergencia',
            'education_certificates' => 'Certificados de Estudios',
            'criminal_record' => 'Certificado de Antecedentes',
        ],
        'contract_templates' => [
            'indefinite' => 'Contrato Indefinido',
            'fixed_term' => 'Contrato a Plazo Fijo',
            'per_project' => 'Contrato por Obra',
            'part_time' => 'Contrato Part-Time',
            'internship' => 'Práctica Profesional',
            'apprentice' => 'Contrato de Aprendizaje',
        ],
        'document_expiry_alerts' => 30,
        'digital_signatures_enabled' => true,
        'retention_policy_years' => 5,
    ],
    
    'compliance' => [
        'labor_law_version' => '2024',
        'union_enabled' => true,
        'collective_bargaining' => true,
        'data_retention_years' => 5,
        'audit_trail_enabled' => true,
        'anonymous_complaints_enabled' => true,
        'whistleblower_protection' => true,
        'required_policies' => [
            'code_of_conduct',
            'anti_harassment',
            'data_privacy',
            'safety_procedures',
            'remote_work',
        ],
    ],
    
    'notifications' => [
        'channels' => ['email', 'database'],
        'leave_request' => true,
        'leave_approved' => true,
        'payslip_ready' => true,
        'birthday_reminder' => true,
        'work_anniversary' => true,
        'document_expiry' => true,
        'performance_review' => true,
        'training_reminder' => true,
    ],
    
    'reports' => [
        'payroll_summary' => true,
        'attendance_summary' => true,
        'leave_balance' => true,
        'employee_turnover' => true,
        'headcount_analysis' => true,
        'compensation_analysis' => true,
        'diversity_metrics' => true,
        'training_metrics' => true,
    ],
];