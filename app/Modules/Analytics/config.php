<?php

return [
    'name' => 'Analytics',
    'version' => '1.0.0',
    'description' => 'Advanced analytics and reporting module',
    
    // Module settings
    'settings' => [
        'retention_days' => 365,
        'default_date_range' => 'last_30_days',
        'enable_realtime' => true,
        'export_formats' => ['pdf', 'excel', 'csv'],
        'cleanup_on_uninstall' => false,
    ],
    
    // Chart configurations
    'charts' => [
        'default_type' => 'line',
        'color_scheme' => 'default',
        'animation_duration' => 750,
        'responsive' => true,
    ],
    
    // Available metrics
    'metrics' => [
        'revenue' => [
            'name' => 'Revenue',
            'type' => 'currency',
            'aggregation' => 'sum',
            'source' => 'tax_documents',
        ],
        'orders' => [
            'name' => 'Orders',
            'type' => 'count',
            'aggregation' => 'count',
            'source' => 'tax_documents',
        ],
        'customers' => [
            'name' => 'Customers',
            'type' => 'count',
            'aggregation' => 'distinct',
            'source' => 'customers',
        ],
        'products' => [
            'name' => 'Products Sold',
            'type' => 'count',
            'aggregation' => 'sum',
            'source' => 'tax_document_items',
        ],
    ],
    
    // KPI templates
    'kpi_templates' => [
        'revenue_growth' => [
            'name' => 'Revenue Growth',
            'formula' => '((current_period - previous_period) / previous_period) * 100',
            'format' => 'percentage',
            'trend_positive' => 'increase',
        ],
        'average_order_value' => [
            'name' => 'Average Order Value',
            'formula' => 'revenue / orders',
            'format' => 'currency',
            'trend_positive' => 'increase',
        ],
        'customer_retention' => [
            'name' => 'Customer Retention Rate',
            'formula' => '(returning_customers / total_customers) * 100',
            'format' => 'percentage',
            'trend_positive' => 'increase',
        ],
    ],
    
    // Report templates
    'report_templates' => [
        'sales_summary' => [
            'name' => 'Sales Summary',
            'sections' => ['revenue', 'orders', 'top_products', 'top_customers'],
            'default_period' => 'monthly',
        ],
        'inventory_analysis' => [
            'name' => 'Inventory Analysis',
            'sections' => ['stock_levels', 'turnover_rate', 'low_stock_alerts'],
            'default_period' => 'weekly',
        ],
        'financial_overview' => [
            'name' => 'Financial Overview',
            'sections' => ['revenue', 'expenses', 'profit_margin', 'cash_flow'],
            'default_period' => 'quarterly',
        ],
    ],
];