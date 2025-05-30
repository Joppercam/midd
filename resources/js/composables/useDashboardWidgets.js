import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useDashboardWidgets(metrics) {
    const page = usePage();
    
    // Función para verificar permisos del usuario
    const hasPermission = (permission) => {
        const userPermissions = page.props.auth.user.permissions || [];
        const userRole = page.props.auth.user.role;
        
        // Admin tiene acceso a todo
        if (userRole === 'admin' || userPermissions.includes('*')) {
            return true;
        }
        
        // Verificar permisos específicos
        return userPermissions.some(p => 
            p === permission || 
            p.endsWith('.*') && permission.startsWith(p.slice(0, -2))
        );
    };

    // Configuración de widgets según roles
    const allWidgets = {
        // Widgets para administradores
        admin: [
            {
                id: 'monthly_revenue',
                title: 'Ventas del Mes',
                getValue: () => metrics.value.monthly_revenue,
                format: 'currency',
                color: 'blue',
                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                link: 'invoices.index',
                trend: '+12%',
                permission: 'invoices.view'
            },
            {
                id: 'pending_invoices',
                title: 'Facturas Pendientes',
                getValue: () => metrics.value.pending_invoices,
                format: 'number',
                color: 'yellow',
                icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                link: 'invoices.index',
                alert: () => metrics.value.pending_invoices > 5,
                permission: 'invoices.view'
            },
            {
                id: 'total_customers',
                title: 'Clientes Activos',
                getValue: () => metrics.value.total_customers,
                format: 'number',
                color: 'green',
                icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                link: 'customers.index',
                subtitle: () => `+${metrics.value.new_customers_month || 0} este mes`,
                permission: 'customers.view'
            },
            {
                id: 'low_stock_products',
                title: 'Productos Bajo Stock',
                getValue: () => metrics.value.low_stock_products,
                format: 'number',
                color: () => metrics.value.low_stock_products > 0 ? 'red' : 'gray',
                icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                link: 'products.index',
                alert: () => metrics.value.low_stock_products > 0,
                permission: 'products.view'
            },
            {
                id: 'total_expenses_month',
                title: 'Gastos del Mes',
                getValue: () => metrics.value.total_expenses_month,
                format: 'currency',
                color: 'purple',
                icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                link: 'expenses.index',
                permission: 'expenses.view'
            },
            {
                id: 'cash_flow',
                title: 'Flujo de Caja',
                getValue: () => metrics.value.cash_flow,
                format: 'currency',
                color: () => metrics.value.cash_flow >= 0 ? 'green' : 'red',
                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                link: 'reports.index',
                permission: 'reports.view'
            },
            {
                id: 'pending_reconciliations',
                title: 'Conciliaciones Pendientes',
                getValue: () => metrics.value.pending_reconciliations,
                format: 'number',
                color: 'indigo',
                icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                link: 'banking.index',
                permission: 'banking.view'
            },
            {
                id: 'users_count',
                title: 'Usuarios Activos',
                getValue: () => metrics.value.users_count || 0,
                format: 'number',
                color: 'cyan',
                icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                link: 'users.index',
                permission: 'users.manage'
            }
        ],

        // Widgets para roles de ventas
        sales: [
            {
                id: 'monthly_revenue',
                title: 'Mis Ventas del Mes',
                getValue: () => metrics.value.monthly_revenue,
                format: 'currency',
                color: 'blue',
                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                link: 'invoices.index',
                permission: 'invoices.view'
            },
            {
                id: 'pending_invoices',
                title: 'Facturas Pendientes',
                getValue: () => metrics.value.pending_invoices,
                format: 'number',
                color: 'yellow',
                icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                link: 'invoices.index',
                permission: 'invoices.view'
            },
            {
                id: 'total_customers',
                title: 'Mis Clientes',
                getValue: () => metrics.value.total_customers,
                format: 'number',
                color: 'green',
                icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                link: 'customers.index',
                subtitle: () => `+${metrics.value.new_customers_month || 0} este mes`,
                permission: 'customers.view'
            },
            {
                id: 'quotes_pending',
                title: 'Cotizaciones Pendientes',
                getValue: () => metrics.value.quotes_pending || 0,
                format: 'number',
                color: 'orange',
                icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                link: 'quotes.index',
                permission: 'quotes.view'
            }
        ],

        // Widgets para contadores
        accountant: [
            {
                id: 'cash_flow',
                title: 'Flujo de Caja',
                getValue: () => metrics.value.cash_flow,
                format: 'currency',
                color: () => metrics.value.cash_flow >= 0 ? 'green' : 'red',
                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                link: 'reports.index',
                permission: 'reports.view'
            },
            {
                id: 'total_expenses_month',
                title: 'Gastos del Mes',
                getValue: () => metrics.value.total_expenses_month,
                format: 'currency',
                color: 'purple',
                icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                link: 'expenses.index',
                permission: 'expenses.view'
            },
            {
                id: 'pending_reconciliations',
                title: 'Conciliaciones Pendientes',
                getValue: () => metrics.value.pending_reconciliations,
                format: 'number',
                color: 'indigo',
                icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                link: 'banking.index',
                permission: 'banking.view'
            },
            {
                id: 'tax_documents_pending',
                title: 'Documentos SII Pendientes',
                getValue: () => metrics.value.tax_documents_pending || 0,
                format: 'number',
                color: 'red',
                icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                link: 'tax-books.index',
                permission: 'tax-books.view'
            },
            {
                id: 'monthly_revenue',
                title: 'Ingresos del Mes',
                getValue: () => metrics.value.monthly_revenue,
                format: 'currency',
                color: 'blue',
                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                link: 'reports.index',
                permission: 'reports.view'
            }
        ],

        // Widgets para operaciones/inventario
        operations: [
            {
                id: 'low_stock_products',
                title: 'Productos Bajo Stock',
                getValue: () => metrics.value.low_stock_products,
                format: 'number',
                color: () => metrics.value.low_stock_products > 0 ? 'red' : 'gray',
                icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                link: 'products.index',
                alert: () => metrics.value.low_stock_products > 0,
                permission: 'products.view'
            },
            {
                id: 'total_products',
                title: 'Total Productos',
                getValue: () => metrics.value.total_products,
                format: 'number',
                color: 'blue',
                icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
                link: 'products.index',
                permission: 'products.view'
            },
            {
                id: 'purchase_orders_pending',
                title: 'Órdenes de Compra Pendientes',
                getValue: () => metrics.value.purchase_orders_pending || 0,
                format: 'number',
                color: 'yellow',
                icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
                link: 'purchase-orders.index',
                permission: 'purchase-orders.view'
            },
            {
                id: 'total_suppliers',
                title: 'Proveedores Activos',
                getValue: () => metrics.value.total_suppliers || 0,
                format: 'number',
                color: 'green',
                icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                link: 'suppliers.index',
                permission: 'suppliers.view'
            }
        ]
    };

    // Widgets disponibles según el rol del usuario
    const availableWidgets = computed(() => {
        const userRole = page.props.auth.user.role;
        
        // Obtener widgets base según el rol
        let roleWidgets = [];
        if (userRole === 'admin') {
            roleWidgets = allWidgets.admin;
        } else if (userRole === 'sales') {
            roleWidgets = allWidgets.sales;
        } else if (userRole === 'accountant') {
            roleWidgets = allWidgets.accountant;
        } else if (userRole === 'operations') {
            roleWidgets = allWidgets.operations;
        } else {
            // Rol por defecto - mostrar widgets básicos
            roleWidgets = allWidgets.sales;
        }

        // Filtrar widgets según permisos específicos
        return roleWidgets.filter(widget => {
            return hasPermission(widget.permission);
        }).map(widget => ({
            ...widget,
            color: typeof widget.color === 'function' ? widget.color() : widget.color,
            alert: typeof widget.alert === 'function' ? widget.alert() : widget.alert,
            subtitle: typeof widget.subtitle === 'function' ? widget.subtitle() : widget.subtitle
        }));
    });

    // Acciones rápidas según el rol
    const quickActions = computed(() => {
        const userRole = page.props.auth.user.role;
        const baseActions = [];

        if (hasPermission('invoices.create')) {
            baseActions.push({
                title: 'Nueva Factura',
                route: 'invoices.create',
                icon: 'M12 6v6m0 0v6m0-6h6m-6 0H6',
                color: 'blue'
            });
        }

        if (hasPermission('quotes.create')) {
            baseActions.push({
                title: 'Nueva Cotización',
                route: 'quotes.create',
                icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                color: 'orange'
            });
        }

        if (hasPermission('payments.create')) {
            baseActions.push({
                title: 'Registrar Pago',
                route: 'payments.create',
                icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                color: 'green'
            });
        }

        if (hasPermission('expenses.create')) {
            baseActions.push({
                title: 'Nuevo Gasto',
                route: 'expenses.create',
                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                color: 'purple'
            });
        }

        if (hasPermission('products.create')) {
            baseActions.push({
                title: 'Nuevo Producto',
                route: 'products.create',
                icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
                color: 'indigo'
            });
        }

        if (hasPermission('customers.create')) {
            baseActions.push({
                title: 'Nuevo Cliente',
                route: 'customers.create',
                icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                color: 'teal'
            });
        }

        if (hasPermission('reports.view')) {
            baseActions.push({
                title: 'Ver Reportes',
                route: 'reports.index',
                icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                color: 'gray'
            });
        }

        return baseActions.slice(0, 6); // Máximo 6 acciones rápidas
    });

    // Utilidades para formateo
    const formatValue = (value, format) => {
        if (format === 'currency') {
            return new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: 'CLP'
            }).format(value);
        }
        if (format === 'number') {
            return new Intl.NumberFormat('es-CL').format(value);
        }
        return value;
    };

    const getWidgetColorClasses = (color) => {
        const colors = {
            blue: 'bg-blue-100 text-blue-600',
            green: 'bg-green-100 text-green-600',
            yellow: 'bg-yellow-100 text-yellow-600',
            orange: 'bg-orange-100 text-orange-600',
            red: 'bg-red-100 text-red-600',
            purple: 'bg-purple-100 text-purple-600',
            indigo: 'bg-indigo-100 text-indigo-600',
            teal: 'bg-teal-100 text-teal-600',
            cyan: 'bg-cyan-100 text-cyan-600',
            gray: 'bg-gray-100 text-gray-600'
        };
        return colors[color] || colors.gray;
    };

    const getActionColorClasses = (color) => {
        const colors = {
            blue: 'text-blue-600',
            green: 'text-green-600',
            yellow: 'text-yellow-600',
            orange: 'text-orange-600',
            red: 'text-red-600',
            purple: 'text-purple-600',
            indigo: 'text-indigo-600',
            teal: 'text-teal-600',
            cyan: 'text-cyan-600',
            gray: 'text-gray-600'
        };
        return colors[color] || colors.gray;
    };

    return {
        availableWidgets,
        quickActions,
        formatValue,
        getWidgetColorClasses,
        getActionColorClasses,
        hasPermission
    };
}