<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    // N+1 Query Detection
    'detect_n_plus_one' => env('PERFORMANCE_DETECT_N_PLUS_ONE', true),
    'slow_query_threshold' => env('PERFORMANCE_SLOW_QUERY_THRESHOLD', 100), // milliseconds
    'repeated_query_threshold' => env('PERFORMANCE_REPEATED_QUERY_THRESHOLD', 10),

    // Eager Loading Configuration
    'eager_loading_batch_size' => env('PERFORMANCE_EAGER_LOADING_BATCH_SIZE', 1000),
    
    // Relations that typically return many records
    'large_relations' => [
        'items',
        'details', 
        'activities',
        'logs',
        'movements',
        'entries',
        'transactions',
        'tax_document_items',
        'quote_items',
        'purchase_order_items',
        'inventory_movements',
        'analytics_events'
    ],

    // Relations that should use batching
    'batching_relations' => [
        'items',
        'details',
        'logs',
        'activities',
        'movements',
        'analytics_events',
        'audit_logs'
    ],

    // Pagination Configuration
    'default_page_size' => env('PERFORMANCE_DEFAULT_PAGE_SIZE', 25),
    'max_page_size' => env('PERFORMANCE_MAX_PAGE_SIZE', 100),
    'enable_cursor_pagination' => env('PERFORMANCE_ENABLE_CURSOR_PAGINATION', true),

    // Cache Configuration
    'cache_ttl' => [
        'dashboard_metrics' => 300,      // 5 minutes
        'user_permissions' => 3600,     // 1 hour
        'tenant_settings' => 1800,      // 30 minutes
        'product_inventory' => 600,     // 10 minutes
        'financial_reports' => 900,     // 15 minutes
        'customer_data' => 1200,        // 20 minutes
        'supplier_data' => 1200,        // 20 minutes
        'tax_calculations' => 3600,     // 1 hour
    ],

    // Cache Tags Configuration
    'cache_tags' => [
        'dashboard' => ['dashboard', 'metrics'],
        'reports' => ['reports', 'analytics'],
        'users' => ['users', 'permissions'],
        'customers' => ['customers', 'crm'],
        'products' => ['products', 'inventory'],
        'financial' => ['financial', 'accounting'],
        'system' => ['system', 'configuration']
    ],

    // Background Job Configuration
    'background_jobs' => [
        'enable_async_reports' => env('PERFORMANCE_ASYNC_REPORTS', true),
        'enable_async_exports' => env('PERFORMANCE_ASYNC_EXPORTS', true),
        'heavy_operation_threshold' => env('PERFORMANCE_HEAVY_OPERATION_THRESHOLD', 5), // seconds
        'queue_connection' => env('PERFORMANCE_QUEUE_CONNECTION', 'redis'),
    ],

    // Database Optimization
    'database' => [
        'enable_query_caching' => env('PERFORMANCE_ENABLE_QUERY_CACHING', true),
        'query_cache_ttl' => env('PERFORMANCE_QUERY_CACHE_TTL', 600),
        'enable_connection_pooling' => env('PERFORMANCE_ENABLE_CONNECTION_POOLING', false),
        'max_connections' => env('PERFORMANCE_MAX_DB_CONNECTIONS', 10),
    ],

    // Memory Management
    'memory' => [
        'large_dataset_threshold' => env('PERFORMANCE_LARGE_DATASET_THRESHOLD', 10000),
        'chunk_size' => env('PERFORMANCE_CHUNK_SIZE', 1000),
        'enable_memory_limit_monitoring' => env('PERFORMANCE_MEMORY_MONITORING', true),
        'memory_limit_warning_threshold' => env('PERFORMANCE_MEMORY_WARNING_THRESHOLD', 80), // percentage
    ],

    // API Response Optimization
    'api' => [
        'enable_compression' => env('PERFORMANCE_API_COMPRESSION', true),
        'compression_threshold' => env('PERFORMANCE_COMPRESSION_THRESHOLD', 1024), // bytes
        'enable_etag' => env('PERFORMANCE_ENABLE_ETAG', true),
        'enable_last_modified' => env('PERFORMANCE_ENABLE_LAST_MODIFIED', true),
    ],

    // Asset Optimization
    'assets' => [
        'enable_cdn_simulation' => env('PERFORMANCE_ENABLE_CDN_SIMULATION', false),
        'cdn_base_url' => env('PERFORMANCE_CDN_BASE_URL', 'https://cdn.crecepyme.cl'),
        'enable_asset_versioning' => env('PERFORMANCE_ENABLE_ASSET_VERSIONING', true),
        'enable_asset_minification' => env('PERFORMANCE_ENABLE_ASSET_MINIFICATION', true),
    ],

    // Frontend Performance
    'frontend' => [
        'enable_lazy_loading' => env('PERFORMANCE_ENABLE_LAZY_LOADING', true),
        'enable_code_splitting' => env('PERFORMANCE_ENABLE_CODE_SPLITTING', true),
        'enable_prefetching' => env('PERFORMANCE_ENABLE_PREFETCHING', true),
        'bundle_analysis' => env('PERFORMANCE_BUNDLE_ANALYSIS', false),
    ],

    // Monitoring and Alerting
    'monitoring' => [
        'enable_performance_monitoring' => env('PERFORMANCE_MONITORING_ENABLED', true),
        'slow_request_threshold' => env('PERFORMANCE_SLOW_REQUEST_THRESHOLD', 2000), // milliseconds
        'alert_on_slow_requests' => env('PERFORMANCE_ALERT_SLOW_REQUESTS', true),
        'performance_log_channel' => env('PERFORMANCE_LOG_CHANNEL', 'performance'),
    ],

    // Optimization Strategies
    'optimization' => [
        'auto_optimize_queries' => env('PERFORMANCE_AUTO_OPTIMIZE_QUERIES', true),
        'auto_cache_frequent_data' => env('PERFORMANCE_AUTO_CACHE_FREQUENT_DATA', true),
        'auto_adjust_cache_ttl' => env('PERFORMANCE_AUTO_ADJUST_CACHE_TTL', true),
        'optimization_run_frequency' => env('PERFORMANCE_OPTIMIZATION_FREQUENCY', 'daily'),
    ],

    // Model-specific eager loading configuration
    'model_eager_loads' => [
        'User' => [
            'global' => ['tenant', 'roles'],
            'conditional' => [
                [
                    'routes' => ['dashboard', 'profile'],
                    'relations' => ['permissions', 'tenant.modules']
                ],
                [
                    'parameters' => ['include_activity' => true],
                    'relations' => ['activities']
                ]
            ]
        ],
        'Customer' => [
            'global' => ['tenant'],
            'conditional' => [
                [
                    'routes' => ['customers.show', 'customers.statement'],
                    'relations' => ['taxDocuments', 'payments', 'quotes']
                ],
                [
                    'parameters' => ['include_analytics' => true],
                    'relations' => ['analyticsEvents']
                ]
            ]
        ],
        'TaxDocument' => [
            'global' => ['tenant', 'customer', 'user'],
            'conditional' => [
                [
                    'routes' => ['invoices.show', 'invoices.pdf'],
                    'relations' => ['items', 'payments.allocations']
                ],
                [
                    'parameters' => ['include_sii' => true],
                    'relations' => ['siiEventLogs']
                ]
            ]
        ],
        'Product' => [
            'global' => ['tenant', 'category'],
            'conditional' => [
                [
                    'routes' => ['products.show', 'inventory.dashboard'],
                    'relations' => ['inventoryMovements']
                ],
                [
                    'parameters' => ['include_statistics' => true],
                    'relations' => ['taxDocumentItems', 'quoteItems']
                ]
            ]
        ],
        'Payment' => [
            'global' => ['tenant', 'customer'],
            'conditional' => [
                [
                    'routes' => ['payments.show', 'banking.reconcile'],
                    'relations' => ['allocations', 'bankTransaction']
                ]
            ]
        ]
    ],

    // Performance Testing Configuration
    'testing' => [
        'enable_performance_tests' => env('PERFORMANCE_TESTING_ENABLED', false),
        'benchmark_iterations' => env('PERFORMANCE_BENCHMARK_ITERATIONS', 100),
        'load_test_concurrent_users' => env('PERFORMANCE_LOAD_TEST_USERS', 10),
        'response_time_targets' => [
            'dashboard' => 1000,    // milliseconds
            'list_pages' => 800,
            'detail_pages' => 600,
            'api_endpoints' => 400,
            'reports' => 3000,
        ]
    ]
];