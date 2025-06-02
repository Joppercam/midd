<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Proveedores
                </h2>
                <Link
                    :href="route('suppliers.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Proveedor
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Proveedores</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ statistics.total_suppliers }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                {{ statistics.active_suppliers }} activos
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Deuda Total</div>
                            <div class="mt-1 text-3xl font-semibold text-red-600">
                                {{ formatCurrency(statistics.total_debt) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                por pagar
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Gastos del Mes</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(statistics.this_month_expenses) }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                {{ new Date().toLocaleDateString('es-CL', { month: 'long' }) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Promedio de Pago</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ statistics.active_suppliers > 0 ? Math.round(statistics.total_debt / statistics.active_suppliers) : 0 }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                por proveedor
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                                <input
                                    type="text"
                                    id="search"
                                    v-model="form.search"
                                    placeholder="Nombre, RUT, email..."
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                />
                            </div>
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                                <select
                                    id="type"
                                    v-model="form.type"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="person">Persona Natural</option>
                                    <option value="company">Empresa</option>
                                </select>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="active">Activos</option>
                                    <option value="inactive">Inactivos</option>
                                </select>
                            </div>
                            <div class="flex items-end space-x-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                                >
                                    Filtrar
                                </button>
                                <button
                                    type="button"
                                    @click="clearFilters"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Proveedores -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Proveedor
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contacto
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Gastos
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Saldo Pendiente
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="relative px-6 py-3">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="supplier in suppliers.data" :key="supplier.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ supplier.name }}</div>
                                        <div class="text-sm text-gray-500">{{ supplier.rut }}</div>
                                        <div v-if="supplier.business_name" class="text-xs text-gray-400">{{ supplier.business_name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                              :class="supplier.type === 'company' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                            {{ supplier.type === 'company' ? 'Empresa' : 'Persona' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="supplier.email" class="text-sm text-gray-900">{{ supplier.email }}</div>
                                        <div v-if="supplier.phone" class="text-sm text-gray-500">{{ supplier.phone }}</div>
                                        <div v-if="!supplier.email && !supplier.phone" class="text-sm text-gray-400">Sin contacto</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        {{ formatCurrency(supplier.total_expenses) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="supplier.pending_balance > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">
                                        {{ formatCurrency(supplier.pending_balance) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="supplier.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                        >
                                            {{ supplier.is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <Link
                                                :href="route('suppliers.show', supplier.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Ver
                                            </Link>
                                            <Link
                                                :href="route('suppliers.edit', supplier.id)"
                                                class="text-yellow-600 hover:text-yellow-900"
                                            >
                                                Editar
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="suppliers.data && suppliers.data.length > 0 && suppliers.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <p class="text-sm text-gray-700">
                                    Mostrando {{ suppliers.from }} a {{ suppliers.to }} de {{ suppliers.total }} resultados
                                </p>
                            </div>
                            <Pagination :links="suppliers.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/UI/Pagination.vue';

const props = defineProps({
    suppliers: Object,
    statistics: Object,
    filters: Object
});

const form = ref({
    search: props.filters.search || '',
    type: props.filters.type || '',
    status: props.filters.status || ''
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const applyFilters = () => {
    router.get(route('suppliers.index'), form.value);
};

const clearFilters = () => {
    form.value = {
        search: '',
        type: '',
        status: ''
    };
    router.get(route('suppliers.index'));
};
</script>