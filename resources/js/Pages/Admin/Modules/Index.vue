<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gestión de Módulos del Sistema
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Módulos Activos</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ stats.active_modules }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Tenants Totales</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ stats.total_tenants }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Ingresos Mensuales</p>
                                <p class="text-2xl font-semibold text-gray-900">${{ formatCurrency(stats.monthly_revenue) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Solicitudes Pendientes</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ stats.pending_requests }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white shadow rounded-lg">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    activeTab === tab.id
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                ]"
                            >
                                {{ tab.name }}
                                <span
                                    v-if="tab.count"
                                    :class="[
                                        activeTab === tab.id
                                            ? 'bg-indigo-100 text-indigo-600'
                                            : 'bg-gray-100 text-gray-900',
                                        'ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium'
                                    ]"
                                >
                                    {{ tab.count }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Contenido de tabs -->
                    <div class="p-6">
                        <!-- Tab: Catálogo de Módulos -->
                        <div v-if="activeTab === 'catalog'" class="space-y-6">
                            <!-- Filtros -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <select
                                        v-model="filters.category"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">Todas las categorías</option>
                                        <option value="finance">Finanzas</option>
                                        <option value="sales">Ventas</option>
                                        <option value="operations">Operaciones</option>
                                        <option value="hr">Recursos Humanos</option>
                                        <option value="analytics">Análisis</option>
                                    </select>

                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="filters.onlyFree"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <label class="ml-2 text-sm text-gray-700">Solo gratuitos</label>
                                    </div>
                                </div>

                                <button
                                    @click="showAddModule = true"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Agregar Módulo
                                </button>
                            </div>

                            <!-- Grid de módulos -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div
                                    v-for="module in filteredModules"
                                    :key="module.id"
                                    class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow"
                                >
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div
                                                class="w-12 h-12 rounded-lg flex items-center justify-center"
                                                :style="{ backgroundColor: module.color + '20' }"
                                            >
                                                <svg
                                                    class="w-6 h-6"
                                                    :style="{ color: module.color }"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <span
                                                v-if="module.is_core"
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                            >
                                                Core
                                            </span>
                                            <span
                                                v-else-if="module.base_price > 0"
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                                            >
                                                ${{ formatCurrency(module.base_price) }}/mes
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                            >
                                                Gratis
                                            </span>
                                        </div>

                                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ module.name }}</h3>
                                        <p class="text-sm text-gray-500 mb-4">{{ module.description }}</p>

                                        <div class="space-y-2 mb-4">
                                            <div
                                                v-for="(feature, idx) in module.features?.slice(0, 3)"
                                                :key="idx"
                                                class="flex items-start"
                                            >
                                                <svg class="flex-shrink-0 h-4 w-4 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span class="ml-2 text-sm text-gray-600">{{ feature }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                            <span class="text-xs text-gray-500">
                                                {{ module.active_tenants_count || 0 }} tenants activos
                                            </span>
                                            <button
                                                @click="viewModuleDetails(module)"
                                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                            >
                                                Ver detalles →
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Tenants y Módulos -->
                        <div v-if="activeTab === 'tenants'" class="space-y-6">
                            <!-- Búsqueda de tenant -->
                            <div class="flex items-center space-x-4">
                                <div class="flex-1">
                                    <input
                                        v-model="tenantSearch"
                                        type="text"
                                        placeholder="Buscar por nombre de empresa o RUT..."
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                                <button
                                    @click="searchTenants"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    Buscar
                                </button>
                            </div>

                            <!-- Lista de tenants -->
                            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                                <ul class="divide-y divide-gray-200">
                                    <li v-for="tenant in tenants" :key="tenant.id">
                                        <div class="px-4 py-4 sm:px-6">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0">
                                                        <img
                                                            v-if="tenant.logo_url"
                                                            :src="tenant.logo_url"
                                                            :alt="tenant.name"
                                                            class="h-10 w-10 rounded-full"
                                                        />
                                                        <div
                                                            v-else
                                                            class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center"
                                                        >
                                                            <span class="text-gray-600 font-medium">
                                                                {{ tenant.name.charAt(0).toUpperCase() }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            {{ tenant.name }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            RUT: {{ tenant.tax_id }} | Plan: {{ tenant.subscription?.plan?.name || 'Sin plan' }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm text-gray-500">
                                                        {{ tenant.active_modules_count }} módulos activos
                                                    </span>
                                                    <button
                                                        @click="manageTenantModules(tenant)"
                                                        class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                                    >
                                                        Gestionar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Tab: Solicitudes -->
                        <div v-if="activeTab === 'requests'" class="space-y-6">
                            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                                <ul class="divide-y divide-gray-200">
                                    <li v-for="request in moduleRequests" :key="request.id">
                                        <div class="px-4 py-4 sm:px-6">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="flex items-center">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            {{ request.tenant.name }} solicita {{ request.module.name }}
                                                        </p>
                                                        <span
                                                            :class="[
                                                                'ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                                request.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                                request.status === 'approved' ? 'bg-green-100 text-green-800' :
                                                                'bg-red-100 text-red-800'
                                                            ]"
                                                        >
                                                            {{ request.status }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-2 text-sm text-gray-500">
                                                        <p>Solicitado por: {{ request.requested_by.name }}</p>
                                                        <p>Fecha: {{ formatDate(request.created_at) }}</p>
                                                        <p v-if="request.reason" class="mt-1">Razón: {{ request.reason }}</p>
                                                    </div>
                                                </div>
                                                <div v-if="request.status === 'pending'" class="flex items-center space-x-2">
                                                    <button
                                                        @click="approveRequest(request)"
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                    >
                                                        Aprobar
                                                    </button>
                                                    <button
                                                        @click="rejectRequest(request)"
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                    >
                                                        Rechazar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Tab: Configuración -->
                        <div v-if="activeTab === 'config'" class="space-y-6">
                            <div class="bg-white shadow sm:rounded-lg">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Configuración de Módulos
                                    </h3>
                                    <div class="mt-6 space-y-6">
                                        <!-- Configuración de planes -->
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900 mb-4">Planes y Paquetes</h4>
                                            <button
                                                @click="managePlans"
                                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                            >
                                                Gestionar Planes
                                            </button>
                                        </div>

                                        <!-- Configuración de permisos -->
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900 mb-4">Permisos por Módulo</h4>
                                            <button
                                                @click="managePermissions"
                                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                            >
                                                Configurar Permisos
                                            </button>
                                        </div>

                                        <!-- Configuración de integraciones -->
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900 mb-4">Integraciones</h4>
                                            <button
                                                @click="manageIntegrations"
                                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                            >
                                                Configurar Integraciones
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para gestionar módulos de un tenant -->
        <Modal :show="showTenantModules" @close="showTenantModules = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Gestionar Módulos - {{ selectedTenant?.name }}
                </h3>

                <div class="space-y-4">
                    <div
                        v-for="module in availableModules"
                        :key="module.id"
                        class="flex items-center justify-between p-4 border border-gray-200 rounded-lg"
                    >
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                :checked="isTenantModuleActive(module.id)"
                                @change="toggleTenantModule(module)"
                                :disabled="module.is_core"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <div class="ml-3">
                                <label class="text-sm font-medium text-gray-900">
                                    {{ module.name }}
                                    <span v-if="module.is_core" class="text-xs text-gray-500">(Core)</span>
                                </label>
                                <p class="text-xs text-gray-500">{{ module.description }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900">
                                {{ module.base_price > 0 ? `$${formatCurrency(module.base_price)}/mes` : 'Gratis' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showTenantModules = false"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="saveTenantModules"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    modules: Array,
    stats: Object,
    tenants: Array,
    moduleRequests: Array,
});

const activeTab = ref('catalog');
const filters = ref({
    category: '',
    onlyFree: false,
});
const tenantSearch = ref('');
const showAddModule = ref(false);
const showTenantModules = ref(false);
const selectedTenant = ref(null);
const availableModules = ref([]);
const tenantModules = ref([]);

const tabs = [
    { id: 'catalog', name: 'Catálogo de Módulos', count: props.modules?.length },
    { id: 'tenants', name: 'Tenants y Módulos' },
    { id: 'requests', name: 'Solicitudes', count: props.moduleRequests?.filter(r => r.status === 'pending').length },
    { id: 'config', name: 'Configuración' },
];

const filteredModules = computed(() => {
    let modules = props.modules || [];

    if (filters.value.category) {
        modules = modules.filter(m => m.category === filters.value.category);
    }

    if (filters.value.onlyFree) {
        modules = modules.filter(m => m.base_price === 0);
    }

    return modules;
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const viewModuleDetails = (module) => {
    router.visit(`/admin/modules/${module.id}`);
};

const searchTenants = () => {
    router.get('/admin/modules', {
        search: tenantSearch.value,
        tab: 'tenants',
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const manageTenantModules = async (tenant) => {
    selectedTenant.value = tenant;
    
    // Cargar módulos del tenant
    const response = await fetch(`/admin/tenants/${tenant.id}/modules`);
    const data = await response.json();
    
    availableModules.value = data.available_modules;
    tenantModules.value = data.tenant_modules;
    
    showTenantModules.value = true;
};

const isTenantModuleActive = (moduleId) => {
    return tenantModules.value.some(tm => tm.module_id === moduleId && tm.is_enabled);
};

const toggleTenantModule = (module) => {
    const index = tenantModules.value.findIndex(tm => tm.module_id === module.id);
    
    if (index >= 0) {
        tenantModules.value[index].is_enabled = !tenantModules.value[index].is_enabled;
    } else {
        tenantModules.value.push({
            module_id: module.id,
            is_enabled: true,
        });
    }
};

const saveTenantModules = () => {
    router.post(`/admin/tenants/${selectedTenant.value.id}/modules`, {
        modules: tenantModules.value,
    }, {
        onSuccess: () => {
            showTenantModules.value = false;
        },
    });
};

const approveRequest = (request) => {
    if (confirm('¿Aprobar esta solicitud de módulo?')) {
        router.post(`/admin/module-requests/${request.id}/approve`);
    }
};

const rejectRequest = (request) => {
    const reason = prompt('Razón del rechazo:');
    if (reason) {
        router.post(`/admin/module-requests/${request.id}/reject`, {
            reason: reason,
        });
    }
};

const managePlans = () => {
    router.visit('/admin/subscription-plans');
};

const managePermissions = () => {
    router.visit('/admin/module-permissions');
};

const manageIntegrations = () => {
    router.visit('/admin/integrations');
};
</script>