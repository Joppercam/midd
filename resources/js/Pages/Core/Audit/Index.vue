<template>
    <Head title="Auditoría del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Auditoría del Sistema
                </h2>
                <div class="flex space-x-3">
                    <Link
                        :href="route('audit.export')"
                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                    >
                        Exportar Logs
                    </Link>
                    <Link
                        :href="route('audit.settings')"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                    >
                        Configuración
                    </Link>
                </div>
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Eventos</p>
                                <p class="text-2xl font-bold text-gray-900">{{ logs.total || 0 }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Usuarios Activos</p>
                                <p class="text-2xl font-bold text-gray-900">{{ users.length }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Modelos Auditados</p>
                                <p class="text-2xl font-bold text-gray-900">{{ modelTypes.length }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Eventos Únicos</p>
                                <p class="text-2xl font-bold text-gray-900">{{ events.length }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Filtros de Búsqueda</h3>
                        
                        <form @submit.prevent="applyFilters" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Usuario
                                    </label>
                                    <select 
                                        v-model="form.user_id" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Todos los usuarios</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.name }} ({{ user.email }})
                                        </option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Modelo
                                    </label>
                                    <select 
                                        v-model="form.model_type" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Todos los modelos</option>
                                        <option v-for="model in modelTypes" :key="model.value" :value="model.value">
                                            {{ model.label }}
                                        </option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Evento
                                    </label>
                                    <select 
                                        v-model="form.event" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Todos los eventos</option>
                                        <option v-for="event in events" :key="event" :value="event">
                                            {{ formatEvent(event) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Fecha Desde
                                    </label>
                                    <input 
                                        v-model="form.date_from"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Fecha Hasta
                                    </label>
                                    <input 
                                        v-model="form.date_to"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Búsqueda
                                    </label>
                                    <input 
                                        v-model="form.search"
                                        type="text"
                                        placeholder="Buscar en logs..."
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button
                                    type="button"
                                    @click="resetFilters"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                                >
                                    Limpiar
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                                >
                                    Aplicar Filtros
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Logs de Auditoría -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Registro de Actividad</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha/Hora
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Usuario
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acción
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Modelo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Detalles
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        IP
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="!logs.data || logs.data.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No hay registros de auditoría disponibles
                                    </td>
                                </tr>
                                <tr v-for="log in logs.data || []" :key="log.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(log.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ log.user ? log.user.name : 'Sistema' }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ log.user ? log.user.email : '' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span 
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="getEventClass(log.event)"
                                        >
                                            {{ formatEvent(log.event) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>{{ getModelName(log.auditable_type) }}</div>
                                        <div class="text-gray-500 text-xs">ID: {{ log.auditable_id }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate">
                                            {{ getChangesSummary(log) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ log.ip_address || 'N/A' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="logs.links" class="px-6 py-3 border-t border-gray-200">
                        <Pagination :links="logs.links" />
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
    logs: Object,
    filters: Object,
    users: Array,
    modelTypes: Array,
    events: Array
});

const form = reactive({
    user_id: props.filters.user_id || '',
    model_type: props.filters.model_type || '',
    event: props.filters.event || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || ''
});

const applyFilters = () => {
    router.get(route('audit.index'), form, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.user_id = '';
    form.model_type = '';
    form.event = '';
    form.date_from = '';
    form.date_to = '';
    form.search = '';
    applyFilters();
};

const formatEvent = (event) => {
    const events = {
        'created': 'Creado',
        'updated': 'Actualizado',
        'deleted': 'Eliminado',
        'restored': 'Restaurado',
        'login': 'Inicio de Sesión',
        'logout': 'Cierre de Sesión',
        'view': 'Visualizado',
        'download': 'Descargado'
    };
    return events[event] || event;
};

const getEventClass = (event) => {
    const classes = {
        'created': 'bg-green-100 text-green-800',
        'updated': 'bg-blue-100 text-blue-800',
        'deleted': 'bg-red-100 text-red-800',
        'restored': 'bg-purple-100 text-purple-800',
        'login': 'bg-indigo-100 text-indigo-800',
        'logout': 'bg-gray-100 text-gray-800',
        'view': 'bg-yellow-100 text-yellow-800',
        'download': 'bg-orange-100 text-orange-800'
    };
    return classes[event] || 'bg-gray-100 text-gray-800';
};

const getModelName = (modelType) => {
    if (!modelType) return 'N/A';
    return modelType.split('\\').pop();
};

const getChangesSummary = (log) => {
    if (log.changed_fields) {
        try {
            const changedFields = typeof log.changed_fields === 'string' 
                ? JSON.parse(log.changed_fields) 
                : log.changed_fields;
            
            if (Array.isArray(changedFields)) {
                return `${changedFields.length} campo(s): ${changedFields.join(', ')}`;
            }
        } catch (e) {
            return log.changed_fields;
        }
    }
    
    if (log.old_values || log.new_values) {
        try {
            const oldValues = log.old_values ? (typeof log.old_values === 'string' ? JSON.parse(log.old_values) : log.old_values) : {};
            const newValues = log.new_values ? (typeof log.new_values === 'string' ? JSON.parse(log.new_values) : log.new_values) : {};
            
            const changes = Object.keys({...oldValues, ...newValues}).length;
            return `${changes} campo(s) modificado(s)`;
        } catch (e) {
            return 'Ver detalles';
        }
    }
    
    return 'Sin cambios registrados';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    
    return new Date(date).toLocaleString('es-CL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};
</script>