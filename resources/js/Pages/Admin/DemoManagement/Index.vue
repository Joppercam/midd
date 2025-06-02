<template>
    <Head title="Gestión de Demos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestión de Solicitudes de Demo
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Pendientes</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.pending }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Demos Activos</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.demo_scheduled }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Convertidos</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.converted }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100">
                                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Esta Semana</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.this_week }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros y Búsqueda -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <input
                                    v-model="form.search"
                                    type="text"
                                    placeholder="Buscar por empresa, contacto o email..."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    @input="search"
                                />
                            </div>
                            <div class="sm:w-48">
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="search"
                                >
                                    <option value="">Todos los estados</option>
                                    <option v-for="(label, value) in statusOptions" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <button
                                @click="resetFilters"
                                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors"
                            >
                                Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Solicitudes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Empresa / Contacto
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo de Negocio
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="request in demoRequests.data" :key="request.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ request.company_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ request.contact_name }} • {{ request.email }}
                                            </div>
                                            <div v-if="request.phone" class="text-sm text-gray-500">
                                                📱 {{ request.phone }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex flex-col">
                                            <span v-if="request.business_type">{{ getBusinessTypeLabel(request.business_type) }}</span>
                                            <span v-if="request.employees" class="text-xs text-gray-500">{{ request.employees }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="getStatusClass(request.status)"
                                        >
                                            {{ statusOptions[request.status] || request.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(request.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <Link
                                            :href="`/admin/demo-management/${request.id}`"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Ver Detalle
                                        </Link>
                                        
                                        <button
                                            v-if="request.status === 'contacted'"
                                            @click="generateCredentials(request)"
                                            :disabled="isGenerating === request.id"
                                            class="text-green-600 hover:text-green-900 disabled:opacity-50"
                                        >
                                            {{ isGenerating === request.id ? 'Generando...' : 'Generar Demo' }}
                                        </button>
                                        
                                        <select
                                            :value="request.status"
                                            @change="updateStatus(request, $event.target.value)"
                                            class="text-xs border-gray-300 rounded"
                                        >
                                            <option v-for="(label, value) in statusOptions" :key="value" :value="value">
                                                {{ label }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="px-6 py-3 border-t border-gray-200">
                        <Pagination :links="demoRequests.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    demoRequests: Object,
    stats: Object,
    filters: Object,
    statusOptions: Object,
});

const form = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
});

const isGenerating = ref(null);

const search = () => {
    router.get('/admin/demo-management', form, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.search = '';
    form.status = '';
    search();
};

const updateStatus = (request, newStatus) => {
    router.put(`/admin/demo-management/${request.id}/status`, {
        status: newStatus
    }, {
        preserveScroll: true,
    });
};

const generateCredentials = (request) => {
    if (confirm('¿Estás seguro de que quieres generar credenciales para este demo?')) {
        isGenerating.value = request.id;
        
        router.post(`/admin/demo-management/${request.id}/generate-credentials`, {}, {
            onFinish: () => {
                isGenerating.value = null;
            }
        });
    }
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'contacted': 'bg-blue-100 text-blue-800',
        'demo_scheduled': 'bg-purple-100 text-purple-800',
        'demo_completed': 'bg-green-100 text-green-800',
        'converted': 'bg-emerald-100 text-emerald-800',
        'declined': 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getBusinessTypeLabel = (type) => {
    const labels = {
        'retail': 'Retail/Comercio',
        'restaurant': 'Restaurante',
        'services': 'Servicios',
        'manufacturing': 'Manufactura',
        'construction': 'Construcción',
        'healthcare': 'Salud',
        'education': 'Educación',
        'other': 'Otro'
    };
    return labels[type] || type;
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>