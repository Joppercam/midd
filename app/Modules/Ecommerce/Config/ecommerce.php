<?php

return [
    'name' => 'E-commerce B2B/B2C',
    'version' => '1.0.0',
    
    // Configuración general
    'general' => [
        'multi_store' => false, // Permitir múltiples tiendas por tenant
        'multi_currency' => false, // Soporte para múltiples monedas
        'multi_language' => false, // Soporte para múltiples idiomas
        'guest_checkout' => true, // Permitir compras sin registro
        'tax_included' => true, // Precios incluyen IVA
        'stock_management' => true, // Gestión de inventario
        'reviews_enabled' => true, // Sistema de reviews
        'wishlist_enabled' => true, // Lista de deseos
    ],
    
    // Configuración del catálogo
    'catalog' => [
        'products_per_page' => 12,
        'max_images_per_product' => 10,
        'image_sizes' => [
            'thumbnail' => [150, 150],
            'small' => [300, 300],
            'medium' => [600, 600],
            'large' => [1200, 1200],
        ],
        'allowed_image_types' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_image_size' => 5120, // 5MB en KB
        'enable_variants' => true,
        'enable_related_products' => true,
        'enable_product_comparison' => true,
    ],
    
    // Configuración del carrito
    'cart' => [
        'session_lifetime' => 7200, // 2 horas en segundos
        'abandoned_threshold' => 7200, // 2 horas para marcar como abandonado
        'max_quantity_per_item' => 999,
        'merge_on_login' => true, // Fusionar carrito de sesión al iniciar sesión
        'restore_abandoned' => true, // Permitir restaurar carritos abandonados
    ],
    
    // Configuración de checkout
    'checkout' => [
        'steps' => ['customer', 'shipping', 'payment', 'review'],
        'require_terms' => true,
        'enable_order_notes' => true,
        'enable_gift_message' => false,
        'min_order_amount' => 0,
        'max_order_amount' => 99999999,
    ],
    
    // Configuración de envíos
    'shipping' => [
        'default_country' => 'CL',
        'allowed_countries' => ['CL'],
        'calculate_by' => 'weight', // weight, price, quantity
        'free_shipping_threshold' => 50000, // Envío gratis sobre este monto
        'packaging_weight' => 0.1, // Peso del empaque en kg
    ],
    
    // Configuración de pagos
    'payments' => [
        'currency' => 'CLP',
        'decimal_places' => 0,
        'thousand_separator' => '.',
        'decimal_separator' => ',',
        'currency_symbol' => '$',
        'test_mode' => env('ECOMMERCE_PAYMENT_TEST_MODE', true),
    ],
    
    // Configuración de notificaciones
    'notifications' => [
        'order_confirmation' => true,
        'order_shipped' => true,
        'order_delivered' => true,
        'abandoned_cart_reminder' => true,
        'abandoned_cart_delay' => 24, // Horas antes de enviar recordatorio
        'review_request' => true,
        'review_request_delay' => 7, // Días después de entrega
        'low_stock_alert' => true,
        'low_stock_threshold' => 10,
    ],
    
    // Configuración de cupones
    'coupons' => [
        'auto_apply_best' => true, // Aplicar automáticamente el mejor cupón
        'allow_multiple' => false, // Permitir múltiples cupones
        'case_sensitive' => false, // Códigos sensibles a mayúsculas
    ],
    
    // Configuración de reviews
    'reviews' => [
        'require_purchase' => true, // Solo clientes que compraron pueden dejar review
        'auto_approve' => false, // Aprobar reviews automáticamente
        'min_rating' => 1,
        'max_rating' => 5,
        'enable_pros_cons' => true,
        'enable_images' => false,
    ],
    
    // Configuración B2B
    'b2b' => [
        'enabled' => true,
        'require_approval' => true, // Aprobar cuentas B2B manualmente
        'show_prices_without_login' => false,
        'minimum_order_quantity' => true,
        'volume_discounts' => true,
        'custom_pricing' => true, // Precios personalizados por cliente
        'credit_limit' => true, // Límite de crédito para clientes B2B
        'net_payment_terms' => [15, 30, 45, 60], // Días de plazo de pago
    ],
    
    // SEO
    'seo' => [
        'enable_sitemap' => true,
        'enable_rich_snippets' => true,
        'enable_canonical_urls' => true,
        'product_url_pattern' => 'product/{slug}',
        'category_url_pattern' => 'category/{slug}',
    ],
    
    // Integraciones
    'integrations' => [
        'google_analytics' => env('ECOMMERCE_GA_ID'),
        'google_tag_manager' => env('ECOMMERCE_GTM_ID'),
        'facebook_pixel' => env('ECOMMERCE_FB_PIXEL_ID'),
        'mailchimp' => env('ECOMMERCE_MAILCHIMP_API_KEY'),
    ],
    
    // Gateways de pago
    'payment_gateways' => [
        'webpay' => [
            'class' => \App\Modules\Ecommerce\Services\PaymentGateways\WebpayGateway::class,
            'test_mode' => env('WEBPAY_TEST_MODE', true),
            'commerce_code' => env('WEBPAY_COMMERCE_CODE'),
            'api_key' => env('WEBPAY_API_KEY'),
        ],
        'mercadopago' => [
            'class' => \App\Modules\Ecommerce\Services\PaymentGateways\MercadoPagoGateway::class,
            'public_key' => env('MP_PUBLIC_KEY'),
            'access_token' => env('MP_ACCESS_TOKEN'),
        ],
        'bank_transfer' => [
            'class' => \App\Modules\Ecommerce\Services\PaymentGateways\BankTransferGateway::class,
            'instructions' => 'Transferir a Cuenta Corriente Banco Estado: 12345678',
        ],
    ],
];