<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Types Configuration
    |--------------------------------------------------------------------------
    */
    'document_types' => [
        'factura_electronica' => [
            'code' => 33,
            'name' => 'Factura Electrónica',
            'requires_customer' => true,
            'requires_tax' => true,
            'sii_type' => 'invoice',
        ],
        'factura_exenta_electronica' => [
            'code' => 34,
            'name' => 'Factura Exenta Electrónica',
            'requires_customer' => true,
            'requires_tax' => false,
            'sii_type' => 'invoice',
        ],
        'boleta_electronica' => [
            'code' => 39,
            'name' => 'Boleta Electrónica',
            'requires_customer' => false,
            'requires_tax' => true,
            'sii_type' => 'receipt',
        ],
        'boleta_exenta_electronica' => [
            'code' => 41,
            'name' => 'Boleta Exenta Electrónica',
            'requires_customer' => false,
            'requires_tax' => false,
            'sii_type' => 'receipt',
        ],
        'nota_credito_electronica' => [
            'code' => 61,
            'name' => 'Nota de Crédito Electrónica',
            'requires_customer' => true,
            'requires_tax' => true,
            'sii_type' => 'credit_note',
        ],
        'nota_debito_electronica' => [
            'code' => 56,
            'name' => 'Nota de Débito Electrónica',
            'requires_customer' => true,
            'requires_tax' => true,
            'sii_type' => 'debit_note',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods Configuration
    |--------------------------------------------------------------------------
    */
    'payment_methods' => [
        'cash' => [
            'name' => 'Efectivo',
            'requires_bank_account' => false,
            'auto_reconcile' => false,
        ],
        'bank_transfer' => [
            'name' => 'Transferencia Bancaria',
            'requires_bank_account' => true,
            'auto_reconcile' => true,
        ],
        'check' => [
            'name' => 'Cheque',
            'requires_bank_account' => true,
            'auto_reconcile' => false,
        ],
        'credit_card' => [
            'name' => 'Tarjeta de Crédito',
            'requires_bank_account' => false,
            'auto_reconcile' => false,
        ],
        'debit_card' => [
            'name' => 'Tarjeta de Débito',
            'requires_bank_account' => true,
            'auto_reconcile' => true,
        ],
        'other' => [
            'name' => 'Otro',
            'requires_bank_account' => false,
            'auto_reconcile' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    */
    'taxes' => [
        'iva' => [
            'name' => 'IVA',
            'rate' => 19.0,
            'code' => 14,
            'applies_to_services' => true,
            'applies_to_products' => true,
        ],
        'exento' => [
            'name' => 'Exento',
            'rate' => 0.0,
            'code' => 0,
            'applies_to_services' => true,
            'applies_to_products' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering Configuration
    |--------------------------------------------------------------------------
    */
    'numbering' => [
        'format' => '{type}-{series}-{number}',
        'padding' => 8,
        'series' => [
            'factura_electronica' => 'F',
            'boleta_electronica' => 'B',
            'nota_credito_electronica' => 'NC',
            'nota_debito_electronica' => 'ND',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SII Integration Configuration
    |--------------------------------------------------------------------------
    */
    'sii' => [
        'environments' => [
            'certification' => [
                'name' => 'Certificación',
                'base_url' => 'https://maullin.sii.cl',
                'wsdl_url' => 'https://maullin.sii.cl/DTEWS/CrSeed.jws?WSDL',
            ],
            'production' => [
                'name' => 'Producción',
                'base_url' => 'https://palena.sii.cl',
                'wsdl_url' => 'https://palena.sii.cl/DTEWS/CrSeed.jws?WSDL',
            ],
        ],
        'default_environment' => 'certification',
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 2, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Templates Configuration
    |--------------------------------------------------------------------------
    */
    'email_templates' => [
        'invoice' => [
            'subject' => 'Factura #{number} - {company_name}',
            'template' => 'emails.invoice',
            'auto_send' => false,
        ],
        'payment_reminder' => [
            'subject' => 'Recordatorio de Pago - Factura #{number}',
            'template' => 'emails.payment-reminder',
            'auto_send' => false,
        ],
        'payment_received' => [
            'subject' => 'Pago Recibido - Factura #{number}',
            'template' => 'emails.payment-received',
            'auto_send' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    */
    'pdf' => [
        'format' => 'A4',
        'orientation' => 'portrait',
        'font' => 'arial',
        'font_size' => 9,
        'margin' => [
            'top' => 20,
            'right' => 15,
            'bottom' => 20,
            'left' => 15,
        ],
        'logo' => [
            'max_width' => 150,
            'max_height' => 80,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Terms Configuration
    |--------------------------------------------------------------------------
    */
    'payment_terms' => [
        0 => 'Contado',
        15 => '15 días',
        30 => '30 días',
        45 => '45 días',
        60 => '60 días',
        90 => '90 días',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_line_items' => 1000,
        'max_amount' => 999999999.99,
        'min_amount' => 0.01,
        'max_description_length' => 1000,
        'required_fields' => [
            'customer_id',
            'date',
            'type',
            'items',
        ],
    ],
];