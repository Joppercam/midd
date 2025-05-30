<?php

return [
    'name' => 'Conciliación Bancaria',
    'description' => 'Gestión de cuentas bancarias y conciliación automática',
    'version' => '1.0.0',
    'author' => 'CrecePyme Team',
    'price' => 12000, // $12.000 CLP/mes
    
    /**
     * Configuración de parsers de extractos bancarios
     */
    'parsers' => [
        'banco_estado' => [
            'name' => 'Banco Estado',
            'class' => \App\Modules\Banking\Services\Parsers\BancoEstadoParser::class,
            'formats' => ['csv', 'txt'],
        ],
        'santander' => [
            'name' => 'Santander',
            'class' => \App\Modules\Banking\Services\Parsers\SantanderParser::class,
            'formats' => ['csv', 'xlsx'],
        ],
        'bci' => [
            'name' => 'BCI',
            'class' => \App\Modules\Banking\Services\Parsers\BCIParser::class,
            'formats' => ['csv', 'xlsx'],
        ],
        'scotiabank' => [
            'name' => 'Scotiabank',
            'class' => \App\Modules\Banking\Services\Parsers\ScotiabankParser::class,
            'formats' => ['csv'],
        ],
        'generic' => [
            'name' => 'Genérico',
            'class' => \App\Modules\Banking\Services\Parsers\GenericParser::class,
            'formats' => ['csv', 'ofx'],
        ],
    ],
    
    /**
     * Configuración de matching automático
     */
    'matching' => [
        'score_threshold' => 0.8, // 80% de confianza mínima
        'methods' => [
            'exact_amount' => ['weight' => 0.4],
            'reference' => ['weight' => 0.3],
            'date_proximity' => ['weight' => 0.2],
            'description' => ['weight' => 0.1],
        ],
        'tolerance' => [
            'amount' => 0.01, // Tolerancia de $0.01
            'days' => 3, // ±3 días
        ],
    ],
    
    /**
     * Categorías automáticas de transacciones
     */
    'categories' => [
        'income' => [
            'sales' => 'Ventas',
            'services' => 'Servicios',
            'interests' => 'Intereses',
            'other_income' => 'Otros ingresos',
        ],
        'expenses' => [
            'suppliers' => 'Proveedores',
            'salaries' => 'Sueldos',
            'taxes' => 'Impuestos',
            'utilities' => 'Servicios básicos',
            'rent' => 'Arriendo',
            'bank_fees' => 'Comisiones bancarias',
            'other_expenses' => 'Otros gastos',
        ],
    ],
    
    /**
     * Reglas de categorización automática
     */
    'categorization_rules' => [
        ['pattern' => '/transferencia.*cliente/i', 'category' => 'sales'],
        ['pattern' => '/pago.*factura/i', 'category' => 'sales'],
        ['pattern' => '/sueldo|remuneracion/i', 'category' => 'salaries'],
        ['pattern' => '/sii|impuesto|tesoreria/i', 'category' => 'taxes'],
        ['pattern' => '/luz|agua|gas|telefono|internet/i', 'category' => 'utilities'],
        ['pattern' => '/arriendo|renta/i', 'category' => 'rent'],
        ['pattern' => '/comision|mantencion/i', 'category' => 'bank_fees'],
    ],
    
    /**
     * Configuración de exportación
     */
    'export' => [
        'formats' => ['pdf', 'excel', 'csv'],
        'include_matched' => true,
        'include_unmatched' => true,
        'include_adjustments' => true,
    ],
    
    /**
     * Límites del módulo
     */
    'limits' => [
        'max_accounts_per_tenant' => 10,
        'max_transactions_per_import' => 5000,
        'max_file_size' => 10 * 1024 * 1024, // 10MB
    ],
];