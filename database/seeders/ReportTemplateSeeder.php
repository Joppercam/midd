<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Reporte de Ventas',
                'slug' => 'sales-report',
                'description' => 'Reporte detallado de ventas por período con análisis de clientes y productos',
                'type' => 'sales',
                'format' => 'pdf',
                'query_class' => 'App\\Services\\Reports\\SalesReportQuery',
                'view_template' => 'reports.sales',
                'default_parameters' => [
                    'date_from' => now()->startOfMonth()->format('Y-m-d'),
                    'date_to' => now()->endOfMonth()->format('Y-m-d'),
                    'include_details' => true,
                    'group_by' => 'day',
                ],
                'available_parameters' => [
                    'date_from' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Desde',
                        'description' => 'Fecha de inicio del período de análisis'
                    ],
                    'date_to' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Hasta',
                        'description' => 'Fecha de fin del período de análisis'
                    ],
                    'customer_id' => [
                        'type' => 'integer',
                        'required' => false,
                        'label' => 'Cliente Específico',
                        'description' => 'Filtrar por un cliente en particular'
                    ],
                    'status' => [
                        'type' => 'string',
                        'required' => false,
                        'label' => 'Estado de Factura',
                        'options' => ['sent', 'paid', 'overdue', 'cancelled'],
                        'description' => 'Filtrar por estado de las facturas'
                    ],
                    'include_details' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Detalles de Productos',
                        'description' => 'Mostrar desglose de productos por factura'
                    ],
                    'group_by' => [
                        'type' => 'string',
                        'required' => false,
                        'label' => 'Agrupar Por',
                        'options' => ['day', 'week', 'month'],
                        'description' => 'Período de agrupación para análisis temporal'
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Estado Financiero',
                'slug' => 'financial-statement',
                'description' => 'Estado de resultados y balance general con análisis de ingresos y gastos',
                'type' => 'financial',
                'format' => 'pdf',
                'query_class' => 'App\\Services\\Reports\\FinancialReportQuery',
                'view_template' => 'reports.financial',
                'default_parameters' => [
                    'date_from' => now()->startOfYear()->format('Y-m-d'),
                    'date_to' => now()->endOfYear()->format('Y-m-d'),
                    'include_balance' => true,
                    'include_comparison' => false,
                ],
                'available_parameters' => [
                    'date_from' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Desde',
                    ],
                    'date_to' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Hasta',
                    ],
                    'include_balance' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Balance General',
                    ],
                    'include_comparison' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Comparación con Período Anterior',
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Inventario Actual',
                'slug' => 'inventory-report',
                'description' => 'Estado actual del inventario con análisis de stock y valorización',
                'type' => 'inventory',
                'format' => 'excel',
                'query_class' => 'App\\Services\\Reports\\InventoryReportQuery',
                'view_template' => 'reports.inventory',
                'default_parameters' => [
                    'include_zero_stock' => false,
                    'low_stock_only' => false,
                    'category_id' => null,
                    'include_valuation' => true,
                ],
                'available_parameters' => [
                    'category_id' => [
                        'type' => 'integer',
                        'required' => false,
                        'label' => 'Categoría Específica',
                    ],
                    'include_zero_stock' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Productos Sin Stock',
                    ],
                    'low_stock_only' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Solo Productos con Stock Bajo',
                    ],
                    'include_valuation' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Valorización',
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Análisis de Clientes',
                'slug' => 'customer-analysis',
                'description' => 'Análisis detallado de comportamiento y rentabilidad de clientes',
                'type' => 'customers',
                'format' => 'pdf',
                'query_class' => 'App\\Services\\Reports\\CustomerAnalysisQuery',
                'view_template' => 'reports.customers',
                'default_parameters' => [
                    'date_from' => now()->startOfYear()->format('Y-m-d'),
                    'date_to' => now()->endOfYear()->format('Y-m-d'),
                    'min_purchases' => 1,
                    'include_inactive' => false,
                ],
                'available_parameters' => [
                    'date_from' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Desde',
                    ],
                    'date_to' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Hasta',
                    ],
                    'min_purchases' => [
                        'type' => 'integer',
                        'required' => false,
                        'label' => 'Mínimo de Compras',
                        'description' => 'Número mínimo de compras para incluir al cliente'
                    ],
                    'include_inactive' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Clientes Inactivos',
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Reporte Tributario Mensual',
                'slug' => 'tax-monthly-report',
                'description' => 'Reporte mensual para declaración de impuestos con libro de ventas y compras',
                'type' => 'tax',
                'format' => 'pdf',
                'query_class' => 'App\\Services\\Reports\\TaxMonthlyQuery',
                'view_template' => 'reports.tax-monthly',
                'default_parameters' => [
                    'month' => now()->format('m'),
                    'year' => now()->format('Y'),
                    'include_exempt' => true,
                    'include_details' => true,
                ],
                'available_parameters' => [
                    'month' => [
                        'type' => 'integer',
                        'required' => true,
                        'label' => 'Mes',
                        'options' => range(1, 12),
                    ],
                    'year' => [
                        'type' => 'integer',
                        'required' => true,
                        'label' => 'Año',
                    ],
                    'include_exempt' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Operaciones Exentas',
                    ],
                    'include_details' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Detalles de Documentos',
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Análisis de Rentabilidad',
                'slug' => 'profitability-analysis',
                'description' => 'Análisis de rentabilidad por productos, categorías y períodos',
                'type' => 'analytics',
                'format' => 'excel',
                'query_class' => 'App\\Services\\Reports\\ProfitabilityQuery',
                'view_template' => 'reports.profitability',
                'default_parameters' => [
                    'date_from' => now()->startOfQuarter()->format('Y-m-d'),
                    'date_to' => now()->endOfQuarter()->format('Y-m-d'),
                    'group_by' => 'product',
                    'include_costs' => true,
                ],
                'available_parameters' => [
                    'date_from' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Desde',
                    ],
                    'date_to' => [
                        'type' => 'date',
                        'required' => true,
                        'label' => 'Fecha Hasta',
                    ],
                    'group_by' => [
                        'type' => 'string',
                        'required' => false,
                        'label' => 'Agrupar Por',
                        'options' => ['product', 'category', 'customer', 'period'],
                    ],
                    'include_costs' => [
                        'type' => 'boolean',
                        'required' => false,
                        'label' => 'Incluir Análisis de Costos',
                    ],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $templateData) {
            ReportTemplate::updateOrCreate(
                ['slug' => $templateData['slug']],
                $templateData
            );
        }

        $this->command->info('Report templates seeded successfully.');
    }
}