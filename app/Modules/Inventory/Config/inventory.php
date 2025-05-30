<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inventory Module Configuration
    |--------------------------------------------------------------------------
    */

    'stock_control' => [
        'enable_negative_stock' => env('INVENTORY_NEGATIVE_STOCK', false),
        'enable_serial_numbers' => env('INVENTORY_SERIAL_NUMBERS', true),
        'enable_batch_tracking' => env('INVENTORY_BATCH_TRACKING', true),
        'enable_expiry_dates' => env('INVENTORY_EXPIRY_DATES', true),
        'auto_generate_barcodes' => env('INVENTORY_AUTO_BARCODES', true),
        'barcode_format' => env('INVENTORY_BARCODE_FORMAT', 'EAN13'),
        'decimal_places' => 2,
        'default_unit' => 'UNIDAD',
    ],

    'valuation_methods' => [
        'default' => env('INVENTORY_VALUATION_METHOD', 'average'),
        'available' => [
            'average' => 'Costo Promedio',
            'fifo' => 'FIFO (Primero en Entrar, Primero en Salir)',
            'lifo' => 'LIFO (Último en Entrar, Primero en Salir)',
            'specific' => 'Identificación Específica'
        ],
        'allow_change' => false,
    ],

    'reorder_rules' => [
        'enable_auto_reorder' => env('INVENTORY_AUTO_REORDER', true),
        'safety_stock_percentage' => 20,
        'lead_time_buffer_days' => 3,
        'approval_required' => true,
        'notification_emails' => env('INVENTORY_REORDER_EMAILS', ''),
        'check_frequency' => 'daily', // daily, weekly, monthly
    ],

    'warehouse_settings' => [
        'enable_multiple_locations' => env('INVENTORY_MULTIPLE_LOCATIONS', true),
        'enable_bin_management' => env('INVENTORY_BIN_MANAGEMENT', true),
        'require_picking_confirmation' => true,
        'enable_cycle_counting' => true,
        'default_location' => 'PRINCIPAL',
        'location_types' => [
            'warehouse' => 'Bodega',
            'store' => 'Tienda',
            'transit' => 'En Tránsito',
            'damaged' => 'Dañado',
            'returns' => 'Devoluciones'
        ],
    ],

    'purchase_order_settings' => [
        'approval_thresholds' => [
            'auto_approve' => 100000,
            'supervisor_approval' => 500000,
            'manager_approval' => 2000000,
            'director_approval' => 5000000,
        ],
        'default_payment_terms' => 30,
        'require_three_quotes' => true,
        'quote_threshold' => 500000,
        'status_workflow' => [
            'draft' => ['submitted'],
            'submitted' => ['approved', 'rejected'],
            'approved' => ['ordered', 'cancelled'],
            'ordered' => ['partial', 'received', 'cancelled'],
            'partial' => ['received', 'cancelled'],
            'received' => ['closed'],
            'rejected' => ['draft'],
            'cancelled' => [],
            'closed' => []
        ],
        'auto_close_after_days' => 7,
    ],

    'supplier_settings' => [
        'evaluation_criteria' => [
            'price' => 30,
            'quality' => 25,
            'delivery' => 20,
            'service' => 15,
            'payment_terms' => 10
        ],
        'rating_scale' => 5,
        'evaluation_frequency' => 'quarterly',
        'minimum_rating' => 3.0,
        'blacklist_threshold' => 2.0,
        'preferred_threshold' => 4.5,
    ],

    'inventory_alerts' => [
        'low_stock_enabled' => env('INVENTORY_LOW_STOCK_ALERTS', true),
        'overstock_enabled' => env('INVENTORY_OVERSTOCK_ALERTS', true),
        'expiry_warning_days' => 30,
        'slow_moving_days' => 90,
        'dead_stock_days' => 180,
        'notification_channels' => ['mail', 'database'],
        'alert_frequency' => 'daily',
        'consolidate_alerts' => true,
    ],

    'movement_types' => [
        'purchase' => [
            'name' => 'Compra',
            'affects_stock' => 'increase',
            'requires_document' => true
        ],
        'sale' => [
            'name' => 'Venta',
            'affects_stock' => 'decrease',
            'requires_document' => true
        ],
        'adjustment_positive' => [
            'name' => 'Ajuste Positivo',
            'affects_stock' => 'increase',
            'requires_approval' => true
        ],
        'adjustment_negative' => [
            'name' => 'Ajuste Negativo',
            'affects_stock' => 'decrease',
            'requires_approval' => true
        ],
        'transfer' => [
            'name' => 'Transferencia',
            'affects_stock' => 'transfer',
            'requires_approval' => false
        ],
        'return_supplier' => [
            'name' => 'Devolución a Proveedor',
            'affects_stock' => 'decrease',
            'requires_document' => true
        ],
        'return_customer' => [
            'name' => 'Devolución de Cliente',
            'affects_stock' => 'increase',
            'requires_document' => true
        ],
        'damaged' => [
            'name' => 'Producto Dañado',
            'affects_stock' => 'decrease',
            'requires_approval' => true
        ],
        'expired' => [
            'name' => 'Producto Vencido',
            'affects_stock' => 'decrease',
            'requires_approval' => true
        ],
        'production' => [
            'name' => 'Producción',
            'affects_stock' => 'both',
            'requires_bom' => true
        ],
    ],

    'reports' => [
        'stock_valuation' => [
            'enabled' => true,
            'include_zero_stock' => false,
            'group_by_category' => true,
        ],
        'movement_history' => [
            'default_days' => 30,
            'max_export_rows' => 10000,
        ],
        'aging_analysis' => [
            'periods' => [30, 60, 90, 120, 180],
            'include_projections' => true,
        ],
        'abc_analysis' => [
            'a_percentage' => 70,
            'b_percentage' => 20,
            'c_percentage' => 10,
            'criteria' => 'value', // value, quantity, frequency
        ],
    ],

    'integrations' => [
        'accounting_sync' => env('INVENTORY_ACCOUNTING_SYNC', true),
        'pos_sync' => env('INVENTORY_POS_SYNC', true),
        'ecommerce_sync' => env('INVENTORY_ECOMMERCE_SYNC', false),
        'barcode_scanner' => env('INVENTORY_BARCODE_SCANNER', true),
    ],

    'units_of_measure' => [
        'UNIDAD' => ['name' => 'Unidad', 'symbol' => 'UN', 'decimal' => false],
        'CAJA' => ['name' => 'Caja', 'symbol' => 'CJ', 'decimal' => false],
        'PAQUETE' => ['name' => 'Paquete', 'symbol' => 'PQ', 'decimal' => false],
        'KILOGRAMO' => ['name' => 'Kilogramo', 'symbol' => 'KG', 'decimal' => true],
        'GRAMO' => ['name' => 'Gramo', 'symbol' => 'G', 'decimal' => true],
        'LITRO' => ['name' => 'Litro', 'symbol' => 'L', 'decimal' => true],
        'METRO' => ['name' => 'Metro', 'symbol' => 'M', 'decimal' => true],
        'METRO2' => ['name' => 'Metro Cuadrado', 'symbol' => 'M2', 'decimal' => true],
        'METRO3' => ['name' => 'Metro Cúbico', 'symbol' => 'M3', 'decimal' => true],
    ],
];