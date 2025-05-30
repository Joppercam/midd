<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions by module
        $permissions = [
            // Dashboard
            'dashboard.view' => 'Ver dashboard',

            // Customers
            'customers.view' => 'Ver clientes',
            'customers.create' => 'Crear clientes',
            'customers.edit' => 'Editar clientes',
            'customers.delete' => 'Eliminar clientes',
            'customers.export' => 'Exportar clientes',

            // Products
            'products.view' => 'Ver productos',
            'products.create' => 'Crear productos',
            'products.edit' => 'Editar productos',
            'products.delete' => 'Eliminar productos',
            'products.export' => 'Exportar productos',
            'products.manage_inventory' => 'Gestionar inventario',

            // Invoices
            'invoices.view' => 'Ver facturas',
            'invoices.create' => 'Crear facturas',
            'invoices.edit' => 'Editar facturas',
            'invoices.delete' => 'Eliminar facturas',
            'invoices.export' => 'Exportar facturas',
            'invoices.send' => 'Enviar facturas por email',
            'invoices.download' => 'Descargar facturas PDF',

            // Payments
            'payments.view' => 'Ver pagos',
            'payments.create' => 'Registrar pagos',
            'payments.edit' => 'Editar pagos',
            'payments.delete' => 'Eliminar pagos',
            'payments.export' => 'Exportar pagos',

            // Suppliers
            'suppliers.view' => 'Ver proveedores',
            'suppliers.create' => 'Crear proveedores',
            'suppliers.edit' => 'Editar proveedores',
            'suppliers.delete' => 'Eliminar proveedores',

            // Expenses
            'expenses.view' => 'Ver gastos',
            'expenses.create' => 'Crear gastos',
            'expenses.edit' => 'Editar gastos',
            'expenses.delete' => 'Eliminar gastos',
            'expenses.approve' => 'Aprobar gastos',

            // Reports
            'reports.view' => 'Ver reportes',
            'reports.sales' => 'Ver reporte de ventas',
            'reports.taxes' => 'Ver reporte de impuestos',
            'reports.inventory' => 'Ver reporte de inventario',
            'reports.financial' => 'Ver reportes financieros',
            'reports.export' => 'Exportar reportes',

            // SII Integration
            'sii.view' => 'Ver integración SII',
            'sii.configure' => 'Configurar SII',
            'sii.send' => 'Enviar documentos al SII',
            'sii.manage_certificates' => 'Gestionar certificados',
            'sii.manage_schemas' => 'Gestionar esquemas XSD',

            // Bank Reconciliation
            'bank.view' => 'Ver conciliación bancaria',
            'bank.import' => 'Importar extractos bancarios',
            'bank.reconcile' => 'Conciliar transacciones',

            // API
            'api.access' => 'Acceso a API',
            'api.manage_tokens' => 'Gestionar tokens API',

            // Backups
            'backups.view' => 'Ver respaldos',
            'backups.create' => 'Crear respaldos',
            'backups.restore' => 'Restaurar respaldos',
            'backups.download' => 'Descargar respaldos',

            // Settings
            'settings.view' => 'Ver configuración',
            'settings.edit' => 'Editar configuración',

            // Users
            'users.view' => 'Ver usuarios',
            'users.create' => 'Crear usuarios',
            'users.edit' => 'Editar usuarios',
            'users.delete' => 'Eliminar usuarios',
            'users.manage_roles' => 'Gestionar roles de usuarios',

            // Audit
            'audit.view' => 'Ver logs de auditoría',
            'audit.export' => 'Exportar logs de auditoría',

            // Tax Books
            'tax_books.view' => 'Ver libros de compras y ventas',
            'tax_books.generate' => 'Generar libros de compras y ventas',
            'tax_books.finalize' => 'Finalizar libros de compras y ventas',
            'tax_books.export' => 'Exportar libros de compras y ventas',
            'tax_books.edit' => 'Editar libros de compras y ventas',
        ];

        // Create permissions
        foreach ($permissions as $name => $description) {
            Permission::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // Create roles and assign permissions
        $this->createRoles();
    }

    /**
     * Create roles with their permissions
     */
    private function createRoles(): void
    {
        // Super Admin - Full access
        $superAdmin = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - Almost full access (no user management)
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo([
            'dashboard.view',
            // Customers
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete', 'customers.export',
            // Products
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.export', 'products.manage_inventory',
            // Invoices
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.export', 'invoices.send', 'invoices.download',
            // Payments
            'payments.view', 'payments.create', 'payments.edit', 'payments.delete', 'payments.export',
            // Suppliers & Expenses
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete', 'expenses.approve',
            // Reports
            'reports.view', 'reports.sales', 'reports.taxes', 'reports.inventory', 'reports.financial', 'reports.export',
            // SII
            'sii.view', 'sii.configure', 'sii.send', 'sii.manage_certificates', 'sii.manage_schemas',
            // Bank
            'bank.view', 'bank.import', 'bank.reconcile',
            // API
            'api.access', 'api.manage_tokens',
            // Backups
            'backups.view', 'backups.create', 'backups.restore', 'backups.download',
            // Settings
            'settings.view', 'settings.edit',
            // Audit
            'audit.view', 'audit.export',
            // Tax Books
            'tax_books.view', 'tax_books.generate', 'tax_books.finalize', 'tax_books.export', 'tax_books.edit',
        ]);

        // Accountant - Financial operations
        $accountant = Role::create(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->givePermissionTo([
            'dashboard.view',
            // Customers (view only)
            'customers.view',
            // Products (view only)
            'products.view',
            // Invoices
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.export', 'invoices.send', 'invoices.download',
            // Payments
            'payments.view', 'payments.create', 'payments.edit', 'payments.export',
            // Suppliers & Expenses
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve',
            // Reports
            'reports.view', 'reports.sales', 'reports.taxes', 'reports.inventory', 'reports.financial', 'reports.export',
            // SII
            'sii.view', 'sii.send',
            // Bank
            'bank.view', 'bank.import', 'bank.reconcile',
            // Audit
            'audit.view',
            // Tax Books
            'tax_books.view', 'tax_books.generate', 'tax_books.finalize', 'tax_books.export', 'tax_books.edit',
        ]);

        // Sales - Sales operations
        $sales = Role::create(['name' => 'sales', 'guard_name' => 'web']);
        $sales->givePermissionTo([
            'dashboard.view',
            // Customers
            'customers.view', 'customers.create', 'customers.edit', 'customers.export',
            // Products (view only)
            'products.view',
            // Invoices
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.export', 'invoices.send', 'invoices.download',
            // Payments (view only)
            'payments.view',
            // Reports (limited)
            'reports.view', 'reports.sales',
        ]);

        // Viewer - Read only access
        $viewer = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo([
            'dashboard.view',
            'customers.view',
            'products.view',
            'invoices.view',
            'payments.view',
            'suppliers.view',
            'expenses.view',
            'reports.view',
        ]);
    }
}