<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemModule;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class ModularSystemSeeder extends Seeder
{
    public function run(): void
    {
        // Crear módulos del sistema
        $modules = [
            // Módulos Core (no se pueden desactivar)
            [
                'code' => 'core',
                'name' => 'Núcleo del Sistema',
                'description' => 'Funcionalidades básicas: autenticación, usuarios, configuración',
                'version' => '1.0.0',
                'category' => 'core',
                'dependencies' => [],
                'is_core' => true,
                'is_active' => true,
                'base_price' => 0,
                'icon' => 'cog',
                'color' => '#6B7280',
                'sort_order' => 1,
                'features' => [
                    'Gestión de usuarios y roles',
                    'Configuración de empresa',
                    'Dashboard básico',
                    'Sistema de auditoría'
                ],
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                    'settings.view', 'settings.edit'
                ]
            ],
            [
                'code' => 'tenancy',
                'name' => 'Multi-tenancy',
                'description' => 'Sistema de múltiples empresas con aislamiento de datos',
                'version' => '1.0.0',
                'category' => 'core',
                'dependencies' => ['core'],
                'is_core' => true,
                'is_active' => true,
                'base_price' => 0,
                'icon' => 'building',
                'color' => '#6B7280',
                'sort_order' => 2,
                'features' => [
                    'Aislamiento de datos por empresa',
                    'Gestión de tenants',
                    'Configuración por empresa'
                ],
                'permissions' => [
                    'tenants.view', 'tenants.create', 'tenants.edit'
                ]
            ],

            // Módulos de Finanzas
            [
                'code' => 'invoicing',
                'name' => 'Facturación Electrónica',
                'description' => 'Facturación, boletas, notas de crédito/débito con integración SII',
                'version' => '1.0.0',
                'category' => 'finance',
                'dependencies' => ['core', 'tenancy'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 15000,
                'icon' => 'document-text',
                'color' => '#059669',
                'sort_order' => 10,
                'features' => [
                    'Facturación electrónica SII',
                    'Múltiples tipos de documento',
                    'Generación automática de PDF',
                    'Envío por email',
                    'Seguimiento de estados'
                ],
                'permissions' => [
                    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete',
                    'invoices.send', 'invoices.pdf'
                ]
            ],
            [
                'code' => 'payments',
                'name' => 'Gestión de Pagos',
                'description' => 'Registro y seguimiento de pagos, asignación a documentos',
                'version' => '1.0.0',
                'category' => 'finance',
                'dependencies' => ['core', 'invoicing'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 8000,
                'icon' => 'cash',
                'color' => '#059669',
                'sort_order' => 11,
                'features' => [
                    'Registro de pagos múltiples',
                    'Asignación automática',
                    'Estados de documentos',
                    'Reportes de cobranza'
                ],
                'permissions' => [
                    'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
                    'payments.allocate'
                ]
            ],
            [
                'code' => 'accounting',
                'name' => 'Contabilidad Completa',
                'description' => 'Plan de cuentas, asientos contables, balance y estado de resultados',
                'version' => '1.0.0',
                'category' => 'finance',
                'dependencies' => ['core', 'invoicing'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 25000,
                'icon' => 'calculator',
                'color' => '#059669',
                'sort_order' => 12,
                'features' => [
                    'Plan de cuentas configurable',
                    'Asientos contables automáticos',
                    'Balance General',
                    'Estado de Resultados',
                    'Centros de costo',
                    'F29 automático'
                ],
                'permissions' => [
                    'accounting.view', 'accounting.create', 'accounting.edit',
                    'chart_accounts.manage', 'journal_entries.view', 'reports.financial'
                ]
            ],
            [
                'code' => 'banking',
                'name' => 'Conciliación Bancaria',
                'description' => 'Importación de cartolas y conciliación automática',
                'version' => '1.0.0',
                'category' => 'finance',
                'dependencies' => ['core', 'payments'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 12000,
                'icon' => 'credit-card',
                'color' => '#059669',
                'sort_order' => 13,
                'features' => [
                    'Importación de cartolas',
                    'Matching automático',
                    'Conciliación manual',
                    'Reportes de conciliación'
                ],
                'permissions' => [
                    'banking.view', 'banking.import', 'banking.reconcile'
                ]
            ],

            // Módulos de Ventas
            [
                'code' => 'customers',
                'name' => 'Gestión de Clientes',
                'description' => 'CRM básico, clientes, cotizaciones y seguimiento',
                'version' => '1.0.0',
                'category' => 'sales',
                'dependencies' => ['core'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 10000,
                'icon' => 'users',
                'color' => '#2563EB',
                'sort_order' => 20,
                'features' => [
                    'Base de datos de clientes',
                    'Estados de cuenta',
                    'Historial de transacciones',
                    'Segmentación básica'
                ],
                'permissions' => [
                    'customers.view', 'customers.create', 'customers.edit', 'customers.delete'
                ]
            ],
            [
                'code' => 'crm',
                'name' => 'CRM Avanzado',
                'description' => 'Pipeline de ventas, leads, oportunidades y seguimiento avanzado',
                'version' => '1.0.0',
                'category' => 'sales',
                'dependencies' => ['core', 'customers'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 20000,
                'icon' => 'chart-bar',
                'color' => '#2563EB',
                'sort_order' => 21,
                'features' => [
                    'Pipeline de ventas',
                    'Gestión de leads',
                    'Seguimiento de oportunidades',
                    'Automatización de marketing',
                    'Análisis de conversión'
                ],
                'permissions' => [
                    'crm.view', 'crm.manage', 'leads.create', 'opportunities.manage',
                    'campaigns.create'
                ]
            ],
            [
                'code' => 'quotes',
                'name' => 'Cotizaciones',
                'description' => 'Creación y gestión de cotizaciones con conversión a factura',
                'version' => '1.0.0',
                'category' => 'sales',
                'dependencies' => ['core', 'customers'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 8000,
                'icon' => 'document-duplicate',
                'color' => '#2563EB',
                'sort_order' => 22,
                'features' => [
                    'Cotizaciones con PDF',
                    'Conversión a factura',
                    'Seguimiento de estados',
                    'Templates personalizables'
                ],
                'permissions' => [
                    'quotes.view', 'quotes.create', 'quotes.edit', 'quotes.convert'
                ]
            ],

            // Módulos de Operaciones
            [
                'code' => 'inventory',
                'name' => 'Gestión de Inventario',
                'description' => 'Control de stock, productos, movimientos y alertas',
                'version' => '1.0.0',
                'category' => 'operations',
                'dependencies' => ['core'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 12000,
                'icon' => 'cube',
                'color' => '#7C3AED',
                'sort_order' => 30,
                'features' => [
                    'Control de stock en tiempo real',
                    'Alertas de stock bajo',
                    'Movimientos de inventario',
                    'Categorización de productos',
                    'Reportes de inventario'
                ],
                'permissions' => [
                    'inventory.view', 'inventory.manage', 'products.create', 'products.edit',
                    'stock.adjust'
                ]
            ],
            [
                'code' => 'suppliers',
                'name' => 'Gestión de Proveedores',
                'description' => 'Proveedores, órdenes de compra, gastos y control de pagos',
                'version' => '1.0.0',
                'category' => 'operations',
                'dependencies' => ['core'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 8000,
                'icon' => 'truck',
                'color' => '#7C3AED',
                'sort_order' => 31,
                'features' => [
                    'Base de datos de proveedores',
                    'Órdenes de compra',
                    'Control de gastos',
                    'Seguimiento de pagos'
                ],
                'permissions' => [
                    'suppliers.view', 'suppliers.create', 'suppliers.edit',
                    'purchase_orders.create', 'expenses.manage'
                ]
            ],
            [
                'code' => 'pos',
                'name' => 'Punto de Venta (POS)',
                'description' => 'Terminal de venta, gestión de cajas y control de turnos',
                'version' => '1.0.0',
                'category' => 'operations',
                'dependencies' => ['core', 'inventory', 'invoicing'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 30000,
                'icon' => 'desktop-computer',
                'color' => '#7C3AED',
                'sort_order' => 32,
                'features' => [
                    'Terminal táctil',
                    'Gestión de cajas',
                    'Control de turnos',
                    'Integración con hardware',
                    'Modo offline'
                ],
                'permissions' => [
                    'pos.access', 'pos.sales', 'pos.cash_management'
                ]
            ],

            // Módulos de Recursos Humanos
            [
                'code' => 'hrm',
                'name' => 'Recursos Humanos',
                'description' => 'Empleados, contratos, liquidaciones y control de asistencia',
                'version' => '1.0.0',
                'category' => 'hr',
                'dependencies' => ['core'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 25000,
                'icon' => 'user-group',
                'color' => '#DC2626',
                'sort_order' => 40,
                'features' => [
                    'Gestión de empleados',
                    'Liquidaciones de sueldo',
                    'Control de asistencia',
                    'Gestión de vacaciones',
                    'Previred integrado'
                ],
                'permissions' => [
                    'hrm.view', 'hrm.manage', 'employees.create', 'payroll.process',
                    'attendance.manage'
                ]
            ],

            // Módulos de Análisis
            [
                'code' => 'analytics',
                'name' => 'Business Intelligence',
                'description' => 'Reportes avanzados, KPIs, análisis predictivo y dashboards',
                'version' => '1.0.0',
                'category' => 'analytics',
                'dependencies' => ['core'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 18000,
                'icon' => 'chart-pie',
                'color' => '#F59E0B',
                'sort_order' => 50,
                'features' => [
                    'Dashboards personalizables',
                    'KPIs en tiempo real',
                    'Análisis predictivo',
                    'Reportes ad-hoc',
                    'Exportación de datos'
                ],
                'permissions' => [
                    'analytics.view', 'analytics.create', 'reports.advanced'
                ]
            ],

            // Módulos de E-commerce
            [
                'code' => 'ecommerce',
                'name' => 'Tienda Online',
                'description' => 'E-commerce B2B/B2C con catálogo, carrito y pasarelas de pago',
                'version' => '1.0.0',
                'category' => 'sales',
                'dependencies' => ['core', 'inventory', 'customers'],
                'is_core' => false,
                'is_active' => true,
                'base_price' => 35000,
                'icon' => 'shopping-cart',
                'color' => '#2563EB',
                'sort_order' => 23,
                'features' => [
                    'Catálogo online',
                    'Carrito de compras',
                    'Pasarelas de pago',
                    'Gestión de órdenes',
                    'Portal B2B'
                ],
                'permissions' => [
                    'ecommerce.manage', 'catalog.edit', 'orders.process'
                ]
            ],
        ];

        foreach ($modules as $moduleData) {
            SystemModule::updateOrCreate(
                ['code' => $moduleData['code']],
                $moduleData
            );
        }

        // Crear planes de suscripción
        $plans = [
            [
                'name' => 'Starter',
                'code' => 'starter',
                'description' => 'Plan básico para empresas pequeñas',
                'monthly_price' => 39000,
                'annual_price' => 390000,
                'included_modules' => ['core', 'tenancy', 'invoicing', 'customers', 'inventory'],
                'limits' => [
                    'users' => 3,
                    'documents' => 500,
                    'products' => 100,
                    'storage' => '5GB',
                    'max_additional_modules' => 2
                ],
                'features' => [
                    'Soporte por email',
                    'Backup automático',
                    'SSL incluido'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 14,
                'sort_order' => 1
            ],
            [
                'name' => 'Professional',
                'code' => 'professional',
                'description' => 'Plan completo para empresas en crecimiento',
                'monthly_price' => 89000,
                'annual_price' => 890000,
                'included_modules' => [
                    'core', 'tenancy', 'invoicing', 'payments', 'customers', 
                    'inventory', 'quotes', 'banking', 'suppliers'
                ],
                'limits' => [
                    'users' => 10,
                    'documents' => 2000,
                    'products' => 500,
                    'storage' => '20GB',
                    'max_additional_modules' => 5
                ],
                'features' => [
                    'Soporte prioritario',
                    'Backup avanzado',
                    'Integraciones básicas',
                    'API access'
                ],
                'is_active' => true,
                'is_popular' => true,
                'trial_days' => 30,
                'sort_order' => 2
            ],
            [
                'name' => 'Enterprise',
                'code' => 'enterprise',
                'description' => 'Solución completa para empresas grandes',
                'monthly_price' => 189000,
                'annual_price' => 1890000,
                'included_modules' => [
                    'core', 'tenancy', 'invoicing', 'payments', 'accounting',
                    'customers', 'crm', 'inventory', 'quotes', 'banking',
                    'suppliers', 'analytics', 'hrm'
                ],
                'limits' => [
                    'users' => 50,
                    'documents' => 10000,
                    'products' => 2000,
                    'storage' => '100GB',
                    'max_additional_modules' => -1 // Ilimitado
                ],
                'features' => [
                    'Soporte 24/7',
                    'Backup enterprise',
                    'Todas las integraciones',
                    'API ilimitado',
                    'White label',
                    'Manager dedicado'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 30,
                'sort_order' => 3
            ]
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }

        // Crear configuración por tipo de negocio
        $businessTypes = [
            [
                'business_type' => 'retail',
                'business_size' => 'small',
                'recommended_modules' => ['core', 'tenancy', 'invoicing', 'inventory', 'pos', 'customers'],
                'essential_modules' => ['core', 'tenancy', 'invoicing', 'inventory'],
                'optional_modules' => ['pos', 'ecommerce', 'analytics']
            ],
            [
                'business_type' => 'services',
                'business_size' => 'small',
                'recommended_modules' => ['core', 'tenancy', 'invoicing', 'customers', 'quotes', 'crm'],
                'essential_modules' => ['core', 'tenancy', 'invoicing', 'customers'],
                'optional_modules' => ['hrm', 'analytics', 'accounting']
            ],
            [
                'business_type' => 'manufacturing',
                'business_size' => 'medium',
                'recommended_modules' => ['core', 'tenancy', 'invoicing', 'inventory', 'suppliers', 'accounting', 'hrm'],
                'essential_modules' => ['core', 'tenancy', 'invoicing', 'inventory', 'suppliers'],
                'optional_modules' => ['analytics', 'crm', 'pos']
            ],
            [
                'business_type' => 'wholesale',
                'business_size' => 'medium',
                'recommended_modules' => ['core', 'tenancy', 'invoicing', 'inventory', 'customers', 'suppliers', 'ecommerce'],
                'essential_modules' => ['core', 'tenancy', 'invoicing', 'inventory', 'customers'],
                'optional_modules' => ['analytics', 'accounting', 'crm']
            ]
        ];

        foreach ($businessTypes as $businessType) {
            DB::table('business_type_modules')->updateOrInsert(
                [
                    'business_type' => $businessType['business_type'],
                    'business_size' => $businessType['business_size']
                ],
                [
                    'business_type' => $businessType['business_type'],
                    'business_size' => $businessType['business_size'],
                    'recommended_modules' => json_encode($businessType['recommended_modules']),
                    'essential_modules' => json_encode($businessType['essential_modules']),
                    'optional_modules' => json_encode($businessType['optional_modules']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}