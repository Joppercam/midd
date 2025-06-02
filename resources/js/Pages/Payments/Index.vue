<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Pagos Recibidos
                </h2>
                <Link
                    :href="route('payments.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Registrar Pago
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Total Recibido</div>
                            <div class="mt-1 text-3xl font-semibold text-green-600">
                                {{ formatCurrency(statistics.total_amount) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Pendiente</div>
                            <div class="mt-1 text-3xl font-semibold text-yellow-600">
                                {{ formatCurrency(statistics.pending_amount) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Este Mes</div>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ formatCurrency(statistics.this_month) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Sin Asignar</div>
                            <div class="mt-1 text-3xl font-semibold text-red-600">
                                {{ formatCurrency(statistics.unallocated_amount) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-7 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                                <input
                                    type="text"
                                    id="search"
                                    v-model="form.search"
                                    placeholder="Número, referencia, cliente..."
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                />
                            </div>
                            <div>
                                <label for="customer_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                                <select
                                    id="customer_id"
                                    v-model="form.customer_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                        {{ customer.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="payment_method" class="block text-sm font-medium text-gray-700">Método</label>
                                <select
                                    id="payment_method"
                                    v-model="form.payment_method"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="cash">Efectivo</option>
                                    <option value="bank_transfer">Transferencia</option>
                                    <option value="check">Cheque</option>
                                    <option value="credit_card">Tarjeta Crédito</option>
                                    <option value="debit_card">Tarjeta Débito</option>
                                    <option value="electronic">Electrónico</option>
                                    <option value="other">Otro</option>
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
                                    <option value="pending">Pendiente</option>
                                    <option value="confirmed">Confirmado</option>
                                    <option value="rejected">Rechazado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </div>
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">Desde</label>
                                <input
                                    type="date"
                                    id="date_from"
                                    v-model="form.date_from"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                />
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">Hasta</label>
                                <input
                                    type="date"
                                    id="date_to"
                                    v-model="form.date_to"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                />
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

                <!-- Lista de Pagos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Número
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cliente
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Método
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Monto
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Sin Asignar
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
                                <tr v-for="payment in payments.data" :key="payment.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ payment.number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ payment.customer.name }}</div>
                                        <div class="text-sm text-gray-500">{{ payment.customer.rut }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(payment.payment_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ getPaymentMethodLabel(payment.payment_method) }}</div>
                                        <div v-if="payment.reference" class="text-sm text-gray-500">{{ payment.reference }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        {{ formatCurrency(payment.amount) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="payment.remaining_amount > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">
                                        {{ formatCurrency(payment.remaining_amount) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span :class="getStatusClass(payment.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                            {{ getStatusLabel(payment.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <Link
                                                :href="route('payments.show', payment.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Ver
                                            </Link>
                                            <Link
                                                :href="route('payments.edit', payment.id)"
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
                    <div v-if="payments.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <p class="text-sm text-gray-700">
                                    Mostrando {{ payments.from }} a {{ payments.to }} de {{ payments.total }} resultados
                                </p>
                            </div>
                            <Pagination :links="payments.links" />
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
    payments: Object,
    statistics: Object,
    customers: Array,
    filters: Object
});

const form = ref({
    search: props.filters.search || '',
    customer_id: props.filters.customer_id || '',
    payment_method: props.filters.payment_method || '',
    status: props.filters.status || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || ''
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL');
};

const getPaymentMethodLabel = (method) => {
    const labels = {
        'cash': 'Efectivo',
        'bank_transfer': 'Transferencia',
        'check': 'Cheque',
        'credit_card': 'T. Crédito',
        'debit_card': 'T. Débito',
        'electronic': 'Electrónico',
        'other': 'Otro'
    };
    return labels[method] || method;
};

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Pendiente',
        'confirmed': 'Confirmado',
        'rejected': 'Rechazado',
        'cancelled': 'Cancelado'
    };
    return labels[status] || status;
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'confirmed': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'cancelled': 'bg-gray-100 text-gray-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const applyFilters = () => {
    router.get(route('payments.index'), form.value);
};

const clearFilters = () => {
    form.value = {
        search: '',
        customer_id: '',
        payment_method: '',
        status: '',
        date_from: '',
        date_to: ''
    };
    router.get(route('payments.index'));
};
</script>