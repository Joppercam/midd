<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | Demo Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the demo environment including subdomain settings,
    | data management, and user experience customization.
    |
    */

    'enabled' => env('DEMO_ENABLED', true),
    
    'subdomain' => env('DEMO_SUBDOMAIN', 'demo'),
    
    'domain' => env('DEMO_DOMAIN', 'midd.com'),
    
    /*
    |--------------------------------------------------------------------------
    | Demo Session Settings
    |--------------------------------------------------------------------------
    */
    
    'session' => [
        'duration' => env('DEMO_SESSION_DURATION', 30), // minutes
        'warning_time' => env('DEMO_WARNING_TIME', 5), // minutes before expiration
        'extend_limit' => env('DEMO_EXTEND_LIMIT', 2), // max extensions allowed
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Demo Data Management
    |--------------------------------------------------------------------------
    */
    
    'data' => [
        'reset_frequency' => env('DEMO_RESET_FREQUENCY', 'daily'), // daily, hourly, weekly
        'cleanup_after_days' => env('DEMO_CLEANUP_DAYS', 7),
        'max_concurrent_users' => env('DEMO_MAX_USERS', 50),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Demo Content Customization
    |--------------------------------------------------------------------------
    */
    
    'content' => [
        'company_name' => 'Demo Empresa SPA',
        'company_rut' => '12.345.678-9',
        'sample_data_sets' => [
            'retail' => 'Retail/Comercio',
            'restaurant' => 'Restaurante',
            'services' => 'Servicios Profesionales',
            'manufacturing' => 'Manufactura',
            'construction' => 'Construcción',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Chatbot Configuration
    |--------------------------------------------------------------------------
    */
    
    'chatbot' => [
        'enabled' => env('DEMO_CHATBOT_ENABLED', true),
        'provider' => env('DEMO_CHATBOT_PROVIDER', 'internal'), // internal, openai, claude
        'avatar' => '/images/chatbot-avatar.svg',
        'name' => 'MIDD Assistant',
        'welcome_message' => '¡Hola! Soy tu asistente virtual. Te ayudaré a explorar todas las funcionalidades de MIDD. ¿Por dónde te gustaría empezar?',
        'auto_suggestions' => [
            '¿Cómo crear mi primera factura?',
            'Mostrarme el dashboard principal',
            '¿Cómo funciona la integración con SII?',
            'Ver reportes disponibles',
            'Configurar mi empresa',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Demo Tracking & Analytics
    |--------------------------------------------------------------------------
    */
    
    'tracking' => [
        'enabled' => env('DEMO_TRACKING_ENABLED', true),
        'events' => [
            'page_view',
            'feature_click',
            'form_interaction',
            'chatbot_interaction',
            'session_extension',
            'exit_intent',
        ],
        'heatmap_enabled' => env('DEMO_HEATMAP_ENABLED', false),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Demo Limitations
    |--------------------------------------------------------------------------
    */
    
    'limitations' => [
        'disable_email_sending' => true,
        'disable_sii_integration' => true,
        'disable_real_payments' => true,
        'disable_file_uploads' => false,
        'max_records_per_entity' => 100,
        'readonly_settings' => [
            'company_settings',
            'tax_configuration',
            'user_management',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Demo Watermarks & Branding
    |--------------------------------------------------------------------------
    */
    
    'branding' => [
        'show_demo_banner' => true,
        'banner_message' => 'Modo Demo - Explora todas las funcionalidades sin compromiso',
        'watermark_pdfs' => true,
        'watermark_text' => 'DEMO - No válido comercialmente',
        'show_upgrade_prompts' => true,
        'contact_info' => [
            'sales_email' => 'ventas@midd.com',
            'sales_phone' => '+56 9 1234 5678',
        ],
    ],
];