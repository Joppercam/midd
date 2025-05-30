<?php

return [
    'name' => 'Punto de Venta',
    'version' => '1.0.0',
    'description' => 'Sistema completo de punto de venta con gestión de cajas, ventas rápidas y facturación instantánea',
    
    // Configuración de terminales POS
    'terminals' => [
        'max_terminals' => 10,
        'auto_assign' => true,
        'require_pin' => false,
        'session_timeout' => 28800, // 8 horas en segundos
        'idle_timeout' => 1800, // 30 minutos
    ],

    // Configuración de cajas registradoras
    'cash_registers' => [
        'max_open_sessions' => 3,
        'require_start_amount' => true,
        'default_start_amount' => 50000, // CLP
        'auto_close_time' => '23:59',
        'require_count_on_close' => true,
        'max_variance_percentage' => 2.0,
    ],

    // Configuración de ventas
    'sales' => [
        'allow_negative_inventory' => false,
        'auto_print_receipt' => true,
        'require_customer' => false,
        'max_discount_percentage' => 50,
        'require_manager_approval_for_discount' => true,
        'manager_discount_threshold' => 20, // porcentaje
        'void_time_limit' => 300, // 5 minutos
        'max_items_per_sale' => 100,
        'tax_rate' => 0.19, // 19% IVA
        'tax_included' => true, // Precios incluyen IVA
    ],

    // Configuración de métodos de pago
    'payment_methods' => [
        'cash' => [
            'enabled' => true,
            'name' => 'Efectivo',
            'icon' => 'money-bill',
            'requires_change' => true,
            'allow_overpayment' => true,
            'opens_drawer' => true,
        ],
        'credit_card' => [
            'enabled' => true,
            'name' => 'Tarjeta de Crédito',
            'icon' => 'credit-card',
            'requires_pin' => true,
            'max_amount' => null,
            'requires_reference' => true,
        ],
        'debit_card' => [
            'enabled' => true,
            'name' => 'Tarjeta de Débito',
            'icon' => 'credit-card',
            'requires_pin' => true,
            'max_amount' => null,
            'requires_reference' => true,
        ],
        'check' => [
            'enabled' => false,
            'name' => 'Cheque',
            'icon' => 'money-check',
            'requires_approval' => true,
            'max_amount' => 1000000,
            'requires_reference' => true,
        ],
        'transfer' => [
            'enabled' => true,
            'name' => 'Transferencia',
            'icon' => 'exchange-alt',
            'requires_reference' => true,
            'max_amount' => null,
        ],
    ],

    // Configuración de impresoras
    'printers' => [
        'receipt' => [
            'enabled' => true,
            'type' => 'thermal',
            'width' => 80, // mm
            'copies' => 1,
            'auto_cut' => true,
            'logo' => true,
        ],
        'kitchen' => [
            'enabled' => false,
            'type' => 'thermal',
            'width' => 80,
            'copies' => 1,
            'filter_categories' => [], // Solo ciertos productos
        ],
        'fiscal' => [
            'enabled' => false,
            'brand' => 'epson', // epson, bixolon, etc
            'model' => 'tm-t20',
            'port' => 'COM1',
        ],
    ],

    // Configuración de tickets/recibos
    'receipts' => [
        'header' => [
            'show_logo' => true,
            'show_company_name' => true,
            'show_address' => true,
            'show_phone' => true,
            'show_rut' => true,
            'show_date_time' => true,
        ],
        'body' => [
            'show_product_code' => true,
            'show_product_description' => true,
            'show_quantity' => true,
            'show_unit_price' => true,
            'show_line_total' => true,
            'show_discounts' => true,
        ],
        'footer' => [
            'show_subtotal' => true,
            'show_tax_breakdown' => true,
            'show_total' => true,
            'show_payment_method' => true,
            'show_change' => true,
            'show_cashier' => true,
            'show_thank_you' => true,
            'custom_message' => 'Gracias por su compra',
        ],
    ],

    // Configuración de descuentos
    'discounts' => [
        'allow_percentage' => true,
        'allow_fixed_amount' => true,
        'allow_item_discount' => true,
        'allow_total_discount' => true,
        'max_discount_without_approval' => 10, // porcentaje
        'require_reason' => true,
        'predefined_reasons' => [
            'customer_loyalty' => 'Cliente frecuente',
            'promotion' => 'Promoción especial',
            'damaged_item' => 'Producto dañado',
            'employee_discount' => 'Descuento empleado',
            'manager_discretion' => 'Discreción gerencial',
        ],
    ],

    // Configuración offline
    'offline' => [
        'enabled' => true,
        'max_offline_sales' => 50,
        'auto_sync_when_online' => true,
        'sync_interval' => 300, // 5 minutos
        'offline_storage_days' => 7,
    ],

    // Configuración de clientes
    'customers' => [
        'quick_customer_enabled' => true,
        'quick_customer_name' => 'Cliente General',
        'quick_customer_rut' => '11111111-1',
        'require_rut' => true,
        'validate_rut' => true,
        'allow_credit' => false,
        'default_credit_limit' => 0,
    ],

    // Programa de lealtad
    'loyalty' => [
        'enabled' => true,
        'points_per_amount' => 1, // 1 punto por cada X pesos
        'amount_per_point' => 1000, // Monto para ganar 1 punto
        'redemption_rate' => 100, // 1 punto = X pesos de descuento
        'min_redemption_points' => 100, // Mínimo de puntos para canjear
        'tiers' => [
            'bronze' => ['min_points' => 0, 'benefits' => '2% descuento'],
            'silver' => ['min_points' => 1000, 'benefits' => '5% descuento'],
            'gold' => ['min_points' => 5000, 'benefits' => '8% descuento'],
            'platinum' => ['min_points' => 10000, 'benefits' => '10% descuento'],
        ],
    ],

    // Configuración de seguridad
    'security' => [
        'require_manager_pin_for_voids' => true,
        'require_manager_pin_for_refunds' => true,
        'require_manager_pin_for_discounts' => true,
        'log_all_transactions' => true,
        'session_tracking' => true,
        'audit_trail' => true,
        'supervisor_pin_actions' => [
            'void_transaction',
            'manual_discount',
            'price_override',
            'no_sale',
            'cash_movement',
        ],
    ],

    // Configuración de interfaz
    'interface' => [
        'theme' => 'light', // light, dark
        'product_grid_columns' => 4,
        'category_buttons' => true,
        'quick_access_products' => 12,
        'show_product_images' => true,
        'touch_mode' => true,
        'keyboard_shortcuts' => true,
    ],

    // Atajos de teclado
    'shortcuts' => [
        'new_sale' => 'F2',
        'search_product' => 'F3',
        'search_customer' => 'F4',
        'payment' => 'F5',
        'void_item' => 'Delete',
        'void_sale' => 'Shift+Delete',
        'open_drawer' => 'F9',
        'print_last' => 'F10',
        'help' => 'F1',
    ],

    // Denominaciones de billetes/monedas (Chile)
    'denominations' => [
        'bills' => [20000, 10000, 5000, 2000, 1000],
        'coins' => [500, 100, 50, 10],
    ],

    // Sonidos
    'sounds' => [
        'scan' => 'beep.mp3',
        'sale_complete' => 'success.mp3',
        'error' => 'error.mp3',
        'drawer_open' => 'drawer.mp3',
    ],
];