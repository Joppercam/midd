<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CRM Module Configuration
    |--------------------------------------------------------------------------
    */

    'lead_scoring' => [
        'enabled' => env('CRM_LEAD_SCORING_ENABLED', true),
        'auto_assignment' => env('CRM_AUTO_ASSIGNMENT', true),
        'score_thresholds' => [
            'hot' => 80,
            'warm' => 60,
            'cold' => 40
        ],
        'scoring_factors' => [
            'company_size' => 20,
            'budget_range' => 25,
            'decision_timeframe' => 15,
            'engagement_level' => 20,
            'industry_match' => 10,
            'referral_source' => 10
        ]
    ],

    'opportunity_stages' => [
        'prospecting' => [
            'name' => 'Prospección',
            'probability' => 10,
            'color' => '#6B7280'
        ],
        'qualification' => [
            'name' => 'Calificación',
            'probability' => 25,
            'color' => '#F59E0B'
        ],
        'proposal' => [
            'name' => 'Propuesta',
            'probability' => 50,
            'color' => '#3B82F6'
        ],
        'negotiation' => [
            'name' => 'Negociación',
            'probability' => 75,
            'color' => '#8B5CF6'
        ],
        'closed_won' => [
            'name' => 'Ganada',
            'probability' => 100,
            'color' => '#10B981'
        ],
        'closed_lost' => [
            'name' => 'Perdida',
            'probability' => 0,
            'color' => '#EF4444'
        ]
    ],

    'customer_categories' => [
        'premium' => [
            'name' => 'Premium',
            'credit_limit' => 5000000,
            'payment_terms' => 60,
            'discount_rate' => 10
        ],
        'standard' => [
            'name' => 'Estándar',
            'credit_limit' => 2000000,
            'payment_terms' => 30,
            'discount_rate' => 5
        ],
        'basic' => [
            'name' => 'Básico',
            'credit_limit' => 500000,
            'payment_terms' => 15,
            'discount_rate' => 0
        ],
        'prospect' => [
            'name' => 'Prospecto',
            'credit_limit' => 0,
            'payment_terms' => 0,
            'discount_rate' => 0
        ]
    ],

    'communication_preferences' => [
        'email' => 'Correo Electrónico',
        'phone' => 'Teléfono',
        'whatsapp' => 'WhatsApp',
        'in_person' => 'Presencial'
    ],

    'customer_statement' => [
        'include_pending' => true,
        'include_overdue' => true,
        'group_by_invoice' => true,
        'show_payments' => true,
        'aging_periods' => [30, 60, 90, 120]
    ],

    'automation' => [
        'welcome_email' => env('CRM_WELCOME_EMAIL', true),
        'birthday_reminders' => env('CRM_BIRTHDAY_REMINDERS', true),
        'payment_reminders' => env('CRM_PAYMENT_REMINDERS', true),
        'follow_up_tasks' => env('CRM_FOLLOW_UP_TASKS', true)
    ],

    'integrations' => [
        'email_marketing' => env('CRM_EMAIL_MARKETING', false),
        'social_media' => env('CRM_SOCIAL_MEDIA', false),
        'accounting_sync' => env('CRM_ACCOUNTING_SYNC', true),
        'inventory_alerts' => env('CRM_INVENTORY_ALERTS', true)
    ]
];