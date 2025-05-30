<?php

namespace App\Modules\Inventory;

use App\Core\BaseModule;

class Module extends BaseModule
{
    public function getCode(): string
    {
        return 'inventory';
    }

    public function getName(): string
    {
        return 'Inventory';
    }

    public function getDescription(): string
    {
        return 'Gestión completa de inventarios, proveedores, órdenes de compra, control de stock y trazabilidad de productos';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return ['core'];
    }

    public function getPermissions(): array
    {
        return [
            // Product Management
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.import',
            'products.export',
            'products.update_stock',
            'products.update_prices',
            'products.view_costs',
            'products.barcode',
            
            // Supplier Management
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            'suppliers.statements',
            'suppliers.payment_terms',
            'suppliers.price_lists',
            'suppliers.evaluations',
            
            // Purchase Orders
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.edit',
            'purchase_orders.delete',
            'purchase_orders.approve',
            'purchase_orders.receive',
            'purchase_orders.cancel',
            'purchase_orders.export',
            
            // Inventory Control
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.count',
            'inventory.reconcile',
            'inventory.movements',
            'inventory.valuations',
            'inventory.alerts',
            
            // Stock Management
            'stock.view',
            'stock.minimum_levels',
            'stock.maximum_levels',
            'stock.reorder_points',
            'stock.locations',
            'stock.reservations',
            
            // Inventory Reports
            'inventory.reports',
            'inventory.analytics',
            'inventory.traceability',
            'inventory.aging',
            'inventory.turnover',
            
            // Warehouse Management
            'warehouse.view',
            'warehouse.locations',
            'warehouse.transfers',
            'warehouse.picking',
            'warehouse.packing',
        ];
    }

    public function getRoutes(): string
    {
        return __DIR__ . '/routes.php';
    }

    public function getModuleConfig(): array
    {
        return [
            'stock_control' => [
                'enable_negative_stock' => false,
                'enable_serial_numbers' => true,
                'enable_batch_tracking' => true,
                'enable_expiry_dates' => true,
                'auto_generate_barcodes' => true,
                'barcode_format' => 'EAN13',
            ],
            'valuation_methods' => [
                'default' => 'average', // average, fifo, lifo, specific
                'allow_change' => false,
            ],
            'reorder_rules' => [
                'enable_auto_reorder' => true,
                'safety_stock_percentage' => 20,
                'lead_time_buffer_days' => 3,
                'approval_required' => true,
            ],
            'warehouse_settings' => [
                'enable_multiple_locations' => true,
                'enable_bin_management' => true,
                'require_picking_confirmation' => true,
                'enable_cycle_counting' => true,
            ],
            'purchase_order_settings' => [
                'approval_thresholds' => [
                    'auto_approve' => 100000,
                    'supervisor_approval' => 500000,
                    'manager_approval' => 2000000,
                ],
                'default_payment_terms' => 30,
                'require_three_quotes' => true,
                'quote_threshold' => 500000,
            ],
            'inventory_alerts' => [
                'low_stock_enabled' => true,
                'overstock_enabled' => true,
                'expiry_warning_days' => 30,
                'slow_moving_days' => 90,
                'dead_stock_days' => 180,
            ],
        ];
    }

    public function boot(): void
    {
        // Register Inventory services
        app()->bind('InventoryService', \App\Modules\Inventory\Services\InventoryService::class);
        app()->bind('SupplierService', \App\Modules\Inventory\Services\SupplierService::class);
        app()->bind('PurchaseOrderService', \App\Modules\Inventory\Services\PurchaseOrderService::class);
        app()->bind('StockMovementService', \App\Modules\Inventory\Services\StockMovementService::class);
        app()->bind('ProductService', \App\Modules\Inventory\Services\ProductService::class);
    }
}