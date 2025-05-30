<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chart of Accounts Configuration
    |--------------------------------------------------------------------------
    */
    'chart_of_accounts' => [
        'default_structure' => [
            '1' => [
                'code' => '1',
                'name' => 'ACTIVOS',
                'type' => 'asset',
                'level' => 1,
                'children' => [
                    '11' => [
                        'code' => '11',
                        'name' => 'ACTIVOS CORRIENTES',
                        'type' => 'asset',
                        'level' => 2,
                    ],
                    '12' => [
                        'code' => '12',
                        'name' => 'ACTIVOS NO CORRIENTES',
                        'type' => 'asset',
                        'level' => 2,
                    ],
                ],
            ],
            '2' => [
                'code' => '2',
                'name' => 'PASIVOS',
                'type' => 'liability',
                'level' => 1,
                'children' => [
                    '21' => [
                        'code' => '21',
                        'name' => 'PASIVOS CORRIENTES',
                        'type' => 'liability',
                        'level' => 2,
                    ],
                    '22' => [
                        'code' => '22',
                        'name' => 'PASIVOS NO CORRIENTES',
                        'type' => 'liability',
                        'level' => 2,
                    ],
                ],
            ],
            '3' => [
                'code' => '3',
                'name' => 'PATRIMONIO',
                'type' => 'equity',
                'level' => 1,
            ],
            '4' => [
                'code' => '4',
                'name' => 'INGRESOS',
                'type' => 'income',
                'level' => 1,
            ],
            '5' => [
                'code' => '5',
                'name' => 'GASTOS',
                'type' => 'expense',
                'level' => 1,
            ],
        ],
        'code_length' => 8,
        'auto_generate_codes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expense Categories Configuration
    |--------------------------------------------------------------------------
    */
    'expense_categories' => [
        'operational' => [
            'name' => 'Gastos Operacionales',
            'code' => '5100',
            'subcategories' => [
                'office_supplies' => 'Materiales de Oficina',
                'utilities' => 'Servicios Básicos',
                'rent' => 'Arriendos',
                'insurance' => 'Seguros',
                'maintenance' => 'Mantención',
                'professional_services' => 'Servicios Profesionales',
            ],
        ],
        'administrative' => [
            'name' => 'Gastos Administrativos',
            'code' => '5200',
            'subcategories' => [
                'salaries' => 'Sueldos y Salarios',
                'benefits' => 'Beneficios',
                'training' => 'Capacitación',
                'travel' => 'Viáticos y Traslados',
                'legal' => 'Gastos Legales',
            ],
        ],
        'financial' => [
            'name' => 'Gastos Financieros',
            'code' => '5300',
            'subcategories' => [
                'interest' => 'Intereses',
                'bank_fees' => 'Comisiones Bancarias',
                'exchange_loss' => 'Pérdida por Diferencia de Cambio',
            ],
        ],
        'other' => [
            'name' => 'Otros Gastos',
            'code' => '5900',
            'subcategories' => [
                'donations' => 'Donaciones',
                'fines' => 'Multas',
                'extraordinary' => 'Gastos Extraordinarios',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Types for Expenses
    |--------------------------------------------------------------------------
    */
    'expense_document_types' => [
        'factura' => [
            'name' => 'Factura',
            'code' => 33,
            'affects_tax' => true,
            'requires_folio' => true,
        ],
        'factura_exenta' => [
            'name' => 'Factura Exenta',
            'code' => 34,
            'affects_tax' => false,
            'requires_folio' => true,
        ],
        'boleta' => [
            'name' => 'Boleta',
            'code' => 39,
            'affects_tax' => true,
            'requires_folio' => false,
        ],
        'nota_credito' => [
            'name' => 'Nota de Crédito',
            'code' => 61,
            'affects_tax' => true,
            'requires_folio' => true,
        ],
        'ticket' => [
            'name' => 'Ticket/Comprobante',
            'code' => 0,
            'affects_tax' => false,
            'requires_folio' => false,
        ],
        'recibo' => [
            'name' => 'Recibo',
            'code' => 0,
            'affects_tax' => false,
            'requires_folio' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval Workflow Configuration
    |--------------------------------------------------------------------------
    */
    'approval_workflow' => [
        'enabled' => true,
        'thresholds' => [
            'auto_approve' => 50000, // CLP
            'manager_approval' => 500000, // CLP
            'director_approval' => 2000000, // CLP
        ],
        'roles' => [
            'manager' => 'Manager',
            'director' => 'Director',
            'admin' => 'Administrador',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal Entry Configuration
    |--------------------------------------------------------------------------
    */
    'journal_entries' => [
        'numbering_format' => 'JE-{year}-{month}-{sequence}',
        'auto_post' => false,
        'require_approval' => true,
        'reversible_period_days' => 30,
        'types' => [
            'standard' => 'Asiento Estándar',
            'adjusting' => 'Asiento de Ajuste',
            'closing' => 'Asiento de Cierre',
            'opening' => 'Asiento de Apertura',
            'reversing' => 'Asiento de Reversión',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Financial Reports Configuration
    |--------------------------------------------------------------------------
    */
    'financial_reports' => [
        'balance_sheet' => [
            'name' => 'Balance General',
            'template' => 'reports.balance_sheet',
            'frequency' => ['monthly', 'quarterly', 'yearly'],
        ],
        'income_statement' => [
            'name' => 'Estado de Resultados',
            'template' => 'reports.income_statement',
            'frequency' => ['monthly', 'quarterly', 'yearly'],
        ],
        'cash_flow' => [
            'name' => 'Flujo de Efectivo',
            'template' => 'reports.cash_flow',
            'frequency' => ['monthly', 'quarterly', 'yearly'],
        ],
        'trial_balance' => [
            'name' => 'Balance de Comprobación',
            'template' => 'reports.trial_balance',
            'frequency' => ['monthly'],
        ],
        'general_ledger' => [
            'name' => 'Libro Mayor',
            'template' => 'reports.general_ledger',
            'frequency' => ['monthly', 'yearly'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Formats Configuration
    |--------------------------------------------------------------------------
    */
    'export_formats' => [
        'contpaq' => [
            'name' => 'CONTPAQi',
            'extension' => 'txt',
            'delimiter' => '|',
            'encoding' => 'UTF-8',
        ],
        'monica' => [
            'name' => 'Mónica',
            'extension' => 'txt',
            'delimiter' => ';',
            'encoding' => 'ISO-8859-1',
        ],
        'tango' => [
            'name' => 'Tango Gestión',
            'extension' => 'csv',
            'delimiter' => ',',
            'encoding' => 'UTF-8',
        ],
        'sii' => [
            'name' => 'SII Chile',
            'extension' => 'xml',
            'encoding' => 'UTF-8',
        ],
        'excel' => [
            'name' => 'Microsoft Excel',
            'extension' => 'xlsx',
            'encoding' => 'UTF-8',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget Configuration
    |--------------------------------------------------------------------------
    */
    'budgets' => [
        'periods' => [
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            'yearly' => 'Anual',
        ],
        'variance_threshold' => 10, // Percentage
        'alert_threshold' => 80, // Percentage of budget used
        'auto_rollover' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    */
    'taxes' => [
        'iva_rate' => 19.0,
        'retention_rates' => [
            'honorarios' => 10.75,
            'servicios' => 10.75,
            'arriendo' => 10.75,
        ],
        'tax_periods' => [
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_expense_amount' => 999999999.99,
        'min_expense_amount' => 0.01,
        'max_description_length' => 1000,
        'required_fields' => [
            'date',
            'amount',
            'supplier_id',
            'category',
            'document_type',
        ],
        'file_upload' => [
            'max_size' => 10240, // KB
            'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'gif'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'banking_module' => true,
        'invoicing_module' => true,
        'inventory_module' => true,
        'auto_create_journal_entries' => true,
        'sync_with_bank_transactions' => true,
    ],
];