<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import MiddLogo from '@/Components/MiddLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import PushNotifications from '@/Components/PushNotifications.vue';
import MobileBottomNavigation from '@/Components/MobileBottomNavigation.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const sidebarOpen = ref(false);
const page = usePage();

// Estado para controlar qué secciones están expandidas
const expandedSections = reactive({
    'Ventas & Marketing': false,
    'Operaciones': false,
    'Finanzas': false,
    'Recursos Humanos': false,
    'Punto de Venta (POS)': false,
    'Administración': false
});

// Función para alternar la expansión de una sección
const toggleSection = (sectionTitle) => {
    expandedSections[sectionTitle] = !expandedSections[sectionTitle];
};

// Función para expandir automáticamente las secciones con rutas activas
const expandActiveSections = () => {
    menuItems.value.forEach(section => {
        if (section.isDropdown && isSectionActive(section)) {
            expandedSections[section.title] = true;
        }
    });
};

// Llamar la función cuando el componente se monte
onMounted(() => {
    expandActiveSections();
});

// Función para verificar permisos del usuario
const hasPermission = (permission) => {
    if (!page.props?.auth?.user) {
        return false;
    }
    
    const user = page.props.auth.user;
    const userPermissions = user.permissions || [];
    const userRole = user.role;
    
    // Admin tiene acceso a todo
    if (userRole === 'admin' || userRole === 'super-admin' || userPermissions.includes('*')) {
        return true;
    }
    
    // Verificar permisos específicos
    return userPermissions.some(p => 
        p === permission || 
        (p.endsWith('.*') && permission.startsWith(p.slice(0, -2)))
    );
};

// Menú organizado por categorías con control de permisos
const menuItems = computed(() => {
    const baseItems = [
        {
            title: 'Dashboard',
            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            route: 'dashboard',
            isDropdown: false,
            permission: 'dashboard.view'
        },
        {
            title: 'Ventas & Marketing',
            icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
            items: [
                { name: 'Cotizaciones', route: 'quotes.index', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', permission: 'quotes.view' },
                { name: 'Facturas', route: 'invoices.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', permission: 'invoices.view' },
                { name: 'Clientes', route: 'customers.index', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', permission: 'customers.view' },
                { name: 'Pagos', route: 'payments.index', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', permission: 'payments.view' },
            ],
            isDropdown: true,
            permission: 'sales'
        },
        {
            title: 'Operaciones',
            icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
            items: [
                { name: 'Productos', route: 'products.index', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4', permission: 'products.view' },
                { name: 'Proveedores', route: 'suppliers.index', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', permission: 'suppliers.view' },
                { name: 'Órdenes de Compra', route: 'purchase-orders.index', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', permission: 'purchase-orders.view' },
                { name: 'Gastos', route: 'expenses.index', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', permission: 'expenses.view' },
            ],
            isDropdown: true,
            permission: 'operations'
        },
        {
            title: 'Finanzas',
            icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            items: [
                { name: 'Conciliación Bancaria', route: 'banking.index', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', permission: 'banking.view' },
                { name: 'Libro de Compras y Ventas', route: 'tax-books.index', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', permission: 'tax-books.view' },
                { name: 'Reportes', route: 'reports.index', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', permission: 'reports.view' },
            ],
            isDropdown: true,
            permission: 'finance'
        },
        {
            title: 'Recursos Humanos',
            icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            items: [
                { name: 'Empleados', route: 'hrm.employees.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', permission: 'hrm.employees.view' },
                { name: 'Nómina', route: 'hrm.payroll.index', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', permission: 'hrm.payroll.view' },
                { name: 'Asistencia', route: 'hrm.attendance.index', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', permission: 'hrm.attendance.view' },
                { name: 'Vacaciones', route: 'hrm.leaves.index', icon: 'M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-4 8v-3m0 0V8a2 2 0 012-2h2m-2 6a2 2 0 01-2-2V8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2z', permission: 'hrm.leaves.view' },
            ],
            isDropdown: true,
            permission: 'hrm'
        },
        {
            title: 'Punto de Venta (POS)',
            icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            items: [
                { name: 'Terminal POS', route: 'pos.terminal.index', icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2H9m12 0a2 2 0 012 2v10a2 2 0 01-2 2', permission: 'pos.terminal.access' },
                { name: 'Ventas POS', route: 'pos.sales.index', icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z', permission: 'pos.sales.view' },
                { name: 'Caja Registradora', route: 'pos.cash-register.index', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', permission: 'pos.cash-register.manage' },
            ],
            isDropdown: true,
            permission: 'pos'
        },
        {
            title: 'Administración',
            icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
            items: [
                { name: 'Usuarios', route: 'users.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', permission: 'users.manage' },
                { name: 'Roles', route: 'roles.index', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', permission: 'roles.manage' },
                { name: 'Configuración Empresa', route: 'company-settings.index', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', permission: 'settings.manage' },
                { name: 'Configuración SII', route: 'sii.configuration', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', permission: 'sii.manage' },
                { name: 'Gestión de Demos', route: 'admin.demo.index', icon: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', permission: 'admin' },
                { name: 'Notificaciones Email', route: 'emails.index', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', permission: 'emails.manage' },
                { name: 'Respaldos', route: 'backups.index', icon: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10', permission: 'admin' },
                { name: 'Auditoría', route: 'audit.index', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', permission: 'admin' },
                { name: 'API', route: 'api-management.index', icon: 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z', permission: 'admin' },
            ],
            isDropdown: true,
            permission: 'admin'
        }
    ];

    // Filtrar elementos según permisos
    return baseItems.filter(section => {
        if (!section.isDropdown) {
            return hasPermission(section.permission);
        }
        
        // Para secciones con dropdown, verificar si hay items válidos
        const filteredItems = section.items?.filter(item => hasPermission(item.permission)) || [];
        return filteredItems.length > 0;
    }).map(section => {
        if (section.isDropdown) {
            return {
                ...section,
                items: section.items?.filter(item => hasPermission(item.permission)) || []
            };
        }
        return section;
    });
});

const isActiveRoute = (routeName) => {
    return route().current(routeName + '*');
};

const isSectionActive = (section) => {
    if (!section.isDropdown) {
        return isActiveRoute(section.route);
    }
    return section.items?.some(item => isActiveRoute(item.route)) || false;
};
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile sidebar overlay -->
        <div 
            v-show="sidebarOpen" 
            class="fixed inset-0 z-40 lg:hidden"
            @click="sidebarOpen = false"
        >
            <div class="fixed inset-0 bg-slate-600 bg-opacity-75 transition-opacity"></div>
        </div>

        <!-- Sidebar -->
        <div 
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 transform transition-transform duration-300 ease-in-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="flex h-full flex-col bg-white shadow-xl">
                <!-- Logo -->
                <div class="flex h-16 items-center justify-between px-6 border-b border-slate-200">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <MiddLogo :show-text="true" :show-tagline="false" text-size="small" />
                    </Link>
                    <button 
                        @click="sidebarOpen = false"
                        class="lg:hidden p-1 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                    <!-- Dashboard -->
                    <Link
                        v-for="item in menuItems.filter(item => !item.isDropdown)"
                        :key="item.title"
                        :href="route(item.route)"
                        :class="[
                            'nav-item',
                            isActiveRoute(item.route) ? 'nav-item-active' : 'nav-item-inactive'
                        ]"
                    >
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                        </svg>
                        {{ item.title }}
                    </Link>

                    <!-- Dropdown Sections -->
                    <div 
                        v-for="section in menuItems.filter(item => item.isDropdown)"
                        :key="section.title"
                        class="space-y-1"
                    >
                        <button
                            @click="toggleSection(section.title)"
                            :class="[
                                'w-full nav-item justify-between',
                                isSectionActive(section) ? 'nav-item-active' : 'nav-item-inactive'
                            ]"
                        >
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="section.icon" />
                                </svg>
                                <span class="text-sm font-medium">{{ section.title }}</span>
                            </div>
                            <svg 
                                :class="[
                                    'w-4 h-4 transition-transform duration-200',
                                    expandedSections[section.title] ? 'rotate-180' : 'rotate-0'
                                ]" 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Submenu items -->
                        <div 
                            v-show="expandedSections[section.title]"
                            class="pl-8 space-y-1 animate-slide-down"
                        >
                            <Link
                                v-for="subItem in section.items"
                                :key="subItem.name"
                                :href="route(subItem.route)"
                                :class="[
                                    'flex items-center px-3 py-2 text-sm rounded-lg transition-all duration-200',
                                    isActiveRoute(subItem.route) 
                                        ? 'bg-brand-100 text-brand-900 font-medium border-l-2 border-brand-600' 
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                ]"
                            >
                                <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="subItem.icon" />
                                </svg>
                                {{ subItem.name }}
                            </Link>
                        </div>
                    </div>
                </nav>

                <!-- User info at bottom -->
                <div class="border-t border-slate-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-brand-100 rounded-lg flex items-center justify-center">
                                <span class="text-brand-600 font-semibold text-sm">
                                    {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                        </div>
                        <div class="ml-3 flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">
                                {{ page.props.auth.user.name }}
                            </p>
                            <p class="text-xs text-slate-500 truncate">
                                {{ page.props.auth.user.email }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="lg:pl-64 flex flex-col min-h-screen">
            <!-- Top navigation -->
            <div class="sticky top-0 z-30 bg-white border-b border-slate-200 shadow-sm">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center">
                        <!-- Mobile menu button -->
                        <button
                            @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden p-2 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <!-- Company name -->
                        <div class="hidden sm:block">
                            <div class="flex items-center">
                                <div class="text-sm font-medium text-slate-700">
                                    {{ page.props.auth.user.tenant?.company_name || 'MIDD' }}
                                </div>
                                <div class="ml-2 text-xs text-slate-500">
                                    Sistema de Gestión
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <PushNotifications />
                        
                        <!-- User dropdown -->
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center p-2 rounded-lg text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                                    <div class="w-8 h-8 bg-brand-100 rounded-lg flex items-center justify-center">
                                        <span class="text-brand-600 font-semibold text-sm">
                                            {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                                        </span>
                                    </div>
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="px-4 py-3 border-b border-slate-200">
                                    <p class="text-sm font-medium text-slate-900">{{ page.props.auth.user.name }}</p>
                                    <p class="text-sm text-slate-500">{{ page.props.auth.user.email }}</p>
                                </div>
                                <DropdownLink :href="route('profile.edit')">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Perfil
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Cerrar Sesión
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1">
                <slot />
            </main>
        </div>

        <!-- Mobile bottom navigation -->
        <MobileBottomNavigation class="lg:hidden" />
    </div>
</template>

<style scoped>
/* Smooth animations for dropdown menus */
.animate-slide-down {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>