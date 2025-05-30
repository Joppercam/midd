<template>
    <AuthenticatedLayout>
        <Head title="Órdenes de Compra" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header con estadísticas -->
                <div class="mb-6">
                    <h1 class="text-3xl font-semibold text-gray-900 dark:text-white mb-6">
                        Órdenes de Compra
                    </h1>
                    
                    <!-- Estadísticas -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <Stats
                            title="Total Órdenes"
                            :value="stats.total"
                            icon="document-text"
                            color="blue"
                        />
                        <Stats
                            title="Pendientes"
                            :value="stats.total_confirmed"
                            icon="clock"
                            color="yellow"
                        />
                        <Stats
                            title="Completadas"
                            :value="stats.total_completed"
                            icon="check-circle"
                            color="green"
                        />
                        <Stats
                            title="Monto Pendiente"
                            :value="formatCurrency(stats.pending_amount)"
                            icon="currency-dollar"
                            color="purple"
                        />
                    </div>
                </div>

                <!-- Filtros y acciones -->
                <Card class="mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Búsqueda -->
                            <div>
                                <InputLabel for="search" value="Buscar" />
                                <TextInput
                                    id="search"
                                    v-model="filters.search"
                                    type="text"
                                    placeholder="Número, referencia o proveedor..."
                                    @input="debounceSearch"
                                />
                            </div>

                            <!-- Estado -->
                            <div>
                                <InputLabel for="status" value="Estado" />
                                <select
                                    id="status"
                                    v-model="filters.status"
                                    @change="applyFilters"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="draft">Borrador</option>
                                    <option value="sent">Enviada</option>
                                    <option value="confirmed">Confirmada</option>
                                    <option value="partial">Recepción Parcial</option>
                                    <option value="completed">Completada</option>
                                    <option value="cancelled">Cancelada</option>
                                </select>
                            </div>

                            <!-- Proveedor -->
                            <div>
                                <InputLabel for="supplier" value="Proveedor" />
                                <select
                                    id="supplier"
                                    v-model="filters.supplier_id"
                                    @change="applyFilters"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option
                                        v-for="supplier in suppliers"
                                        :key="supplier.id"
                                        :value="supplier.id"
                                    >
                                        {{ supplier.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-end">
                                <Link
                                    :href="route('purchase-orders.create')"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Nueva Orden
                                </Link>
                            </div>
                        </div>

                        <!-- Filtros de fecha -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <InputLabel for="start_date" value="Fecha desde" />
                                <TextInput
                                    id="start_date"
                                    v-model="filters.start_date"
                                    type="date"
                                    @change="applyFilters"
                                />
                            </div>
                            <div>
                                <InputLabel for="end_date" value="Fecha hasta" />
                                <TextInput
                                    id="end_date"
                                    v-model="filters.end_date"
                                    type="date"
                                    @change="applyFilters"
                                />
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Tabla de órdenes -->
                <Card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Número
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Proveedor
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Items
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template v-if="orders.data.length > 0">
                                    <tr v-for="order in orders.data" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link
                                                :href="route('purchase-orders.show', order)"
                                                class="text-blue-600 hover:text-blue-900 font-medium"
                                            >
                                                {{ order.order_number }}
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ order.supplier.name }}</div>
                                            <div class="text-sm text-gray-500">{{ order.supplier.rut }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(order.order_date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Badge :variant="order.status_color">
                                                {{ order.status_label }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ order.items.length }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatCurrency(order.total) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <Link
                                                    :href="route('purchase-orders.show', order)"
                                                    class="text-blue-600 hover:text-blue-900"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </Link>
                                                <Link
                                                    v-if="order.status === 'draft'"
                                                    :href="route('purchase-orders.edit', order)"
                                                    class="text-yellow-600 hover:text-yellow-900"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </Link>
                                                <a
                                                    :href="route('purchase-orders.pdf', order)"
                                                    target="_blank"
                                                    class="text-gray-600 hover:text-gray-900"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-else>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No se encontraron órdenes de compra
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="orders.links.length > 3" class="px-6 py-4 border-t">
                        <Pagination :links="orders.links" />
                    </div>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Stats from '@/Components/UI/Stats.vue';
import Badge from '@/Components/UI/Badge.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useFormatters } from '@/composables/useFormatters';

const { formatCurrency, formatDate } = useFormatters();

const props = defineProps({
    orders: Object,
    stats: Object,
    suppliers: Array,
    filters: Object,
});

const filters = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    supplier_id: props.filters.supplier_id || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

let searchTimeout = null;

const debounceSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
};

const applyFilters = () => {
    router.get(route('purchase-orders.index'), filters, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>